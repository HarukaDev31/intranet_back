<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualPagina;
use Illuminate\Support\Facades\DB;

/**
 * Migra páginas CMS existentes a la plantilla Alumnos sin borrar capturas (media_id intacto).
 */
class ManualUsuarioCmsPlantillaMigrator
{
    /**
     * @return array{pages:int, qa_reordered:int, para_que_formatted:int, flow_bodies:int, text_bodies:int, cuando_removed:int}
     */
    public function migrate(?string $roleSlug = null, ?string $moduloKey = null, bool $dropCuando = true): array
    {
        $stats = [
            'pages' => 0,
            'qa_reordered' => 0,
            'para_que_formatted' => 0,
            'flow_bodies' => 0,
            'text_bodies' => 0,
            'cuando_removed' => 0,
        ];

        $query = ManualPagina::query()->orderBy('role_slug')->orderBy('orden')->orderBy('id');
        if ($roleSlug) {
            $query->where('role_slug', $roleSlug);
        }
        if ($moduloKey) {
            $query->where('modulo_key', $moduloKey);
        }

        foreach ($query->cursor() as $page) {
            DB::transaction(function () use ($page, $dropCuando, &$stats) {
                $root = ManualBloque::query()
                    ->where('pagina_id', $page->id)
                    ->whereNull('parent_id')
                    ->where('tipo', ManualBloque::TIPO_GRUPO)
                    ->orderBy('orden')
                    ->first();

                if (!$root) {
                    return;
                }

                $stats['pages']++;
                $children = ManualBloque::query()
                    ->where('pagina_id', $page->id)
                    ->where('parent_id', $root->id)
                    ->orderBy('orden')
                    ->orderBy('id')
                    ->get();

                $qaBlocks = [];
                $otherBlocks = [];
                $cuandoBlock = null;

                foreach ($children as $block) {
                    if ($this->isQaBlock($block)) {
                        $key = $this->qaKey((string) $block->titulo);
                        if ($key === 'cuando') {
                            $cuandoBlock = $block;
                            continue;
                        }
                        $qaBlocks[$key] = $block;
                        continue;
                    }
                    $otherBlocks[] = $block;
                }

                $nextOrden = 1;
                foreach (['¿qué es?', '¿quién lo utiliza?', '¿para qué sirve?'] as $title) {
                    $key = $this->qaKey($title);
                    if (!isset($qaBlocks[$key])) {
                        continue;
                    }
                    $block = $qaBlocks[$key];
                    if ((int) $block->orden !== $nextOrden) {
                        $block->orden = $nextOrden;
                        $stats['qa_reordered']++;
                    }

                    $payload = (array) ($block->payload ?? []);
                    $snapshot = (array) ($payload['snapshot'] ?? []);
                    $body = (string) ($snapshot['body'] ?? '');
                    $formatted = ManualUsuarioPlantillaTextFormatter::formatQaBlock((string) $block->titulo, $body);
                    if ($formatted !== '' && $formatted !== $body) {
                        $snapshot['body'] = $formatted;
                        $payload['snapshot'] = $snapshot;
                        $block->payload = $payload;
                        if ($key === '¿para qué sirve?') {
                            $stats['para_que_formatted']++;
                        } else {
                            $stats['text_bodies']++;
                        }
                    }

                    $block->save();
                    $nextOrden++;
                }

                if ($dropCuando && $cuandoBlock) {
                    $cuandoBlock->delete();
                    $stats['cuando_removed']++;
                } elseif ($cuandoBlock) {
                    $cuandoBlock->orden = $nextOrden;
                    $cuandoBlock->save();
                    $nextOrden++;
                }

                foreach ($otherBlocks as $block) {
                    if ((int) $block->orden !== $nextOrden) {
                        $block->orden = $nextOrden;
                        $block->save();
                    }
                    $this->formatBlockTree($block, $stats);
                    $nextOrden++;
                }
            });
        }

        return $stats;
    }

    /**
     * @param array{flow_bodies:int, text_bodies:int} $stats
     */
    private function formatBlockTree(ManualBloque $block, array &$stats): void
    {
        if ($block->tipo === ManualBloque::TIPO_FLOW) {
            $stats['flow_bodies'] += $this->formatFlowBlock($block);

            return;
        }

        if ($block->tipo === ManualBloque::TIPO_TEXTO) {
            $stats['text_bodies'] += $this->formatTextBlock($block);

            return;
        }

        if ($block->tipo === ManualBloque::TIPO_CALLOUT) {
            $stats['text_bodies'] += $this->formatCalloutBlock($block);

            return;
        }

        if ($block->tipo === ManualBloque::TIPO_GRUPO) {
            $children = ManualBloque::query()
                ->where('parent_id', $block->id)
                ->orderBy('orden')
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                $this->formatBlockTree($child, $stats);
            }
        }
    }

    private function formatFlowBlock(ManualBloque $block): int
    {
        $payload = (array) ($block->payload ?? []);
        $snapshot = (array) ($payload['snapshot'] ?? []);
        $steps = isset($snapshot['steps']) && is_array($snapshot['steps']) ? $snapshot['steps'] : [];
        $changed = 0;

        foreach ($steps as $i => $step) {
            if (!is_array($step)) {
                continue;
            }
            $body = (string) ($step['body'] ?? '');
            $formatted = ManualUsuarioPlantillaTextFormatter::formatFlowBody($body);
            if ($formatted !== $body && $formatted !== '') {
                $steps[$i]['body'] = $formatted;
                $changed++;
            }
        }

        if ($changed > 0) {
            $snapshot['steps'] = $steps;
            $payload['snapshot'] = $snapshot;
            $block->payload = $payload;
            $block->save();
        }

        return $changed;
    }

    private function formatTextBlock(ManualBloque $block): int
    {
        $payload = (array) ($block->payload ?? []);
        $snapshot = (array) ($payload['snapshot'] ?? []);
        $body = (string) ($snapshot['body'] ?? '');
        if ($body === '') {
            return 0;
        }

        $titulo = (string) ($block->titulo ?? '');
        $formatted = !empty($snapshot['qa'])
            ? ManualUsuarioPlantillaTextFormatter::formatQaBlock($titulo, $body)
            : ManualUsuarioPlantillaTextFormatter::formatNumberedSteps($body);

        if ($formatted === $body || $formatted === '') {
            return 0;
        }

        $snapshot['body'] = $formatted;
        $payload['snapshot'] = $snapshot;
        $block->payload = $payload;
        $block->save();

        return 1;
    }

    private function formatCalloutBlock(ManualBloque $block): int
    {
        $payload = (array) ($block->payload ?? []);
        $snapshot = (array) ($payload['snapshot'] ?? []);
        $body = (string) ($snapshot['body'] ?? '');
        if ($body === '') {
            return 0;
        }

        $formatted = ManualUsuarioPlantillaTextFormatter::formatNumberedSteps($body);
        if ($formatted === $body || $formatted === '') {
            return 0;
        }

        $snapshot['body'] = $formatted;
        $payload['snapshot'] = $snapshot;
        $block->payload = $payload;
        $block->save();

        return 1;
    }

    private function isQaBlock(ManualBloque $block): bool
    {
        if ($block->tipo !== ManualBloque::TIPO_TEXTO) {
            return false;
        }
        $payload = (array) ($block->payload ?? []);
        $snapshot = (array) ($payload['snapshot'] ?? []);

        return !empty($snapshot['qa']);
    }

    private function qaKey(string $titulo): string
    {
        $key = mb_strtolower(trim($titulo));
        if (str_contains($key, 'cuándo') || str_contains($key, 'cuando')) {
            return 'cuando';
        }
        if (str_contains($key, 'qué es')) {
            return '¿qué es?';
        }
        if (str_contains($key, 'quién') || str_contains($key, 'quien')) {
            return '¿quién lo utiliza?';
        }
        if (str_contains($key, 'para qué') || str_contains($key, 'para que')) {
            return '¿para qué sirve?';
        }

        return $key;
    }
}
