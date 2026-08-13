<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualPagina;
use Illuminate\Support\Str;

class ManualUsuarioDbService
{
    public function hasPublishedPages(string $roleSlug): bool
    {
        return ManualPagina::query()
            ->where('role_slug', $roleSlug)
            ->where('publicado', true)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pagesForRole(string $roleSlug): array
    {
        $pages = ManualPagina::query()
            ->with(['bloques' => function ($q) {
                $q->whereNull('parent_id')->orderBy('orden')->orderBy('id');
            }, 'bloques.children' => function ($q) {
                $q->orderBy('orden')->orderBy('id');
            }, 'bloques.children.children' => function ($q) {
                $q->orderBy('orden')->orderBy('id');
            }, 'bloques.children.children.children' => function ($q) {
                $q->orderBy('orden')->orderBy('id');
            }])
            ->where('role_slug', $roleSlug)
            ->where('publicado', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return $pages->map(function (ManualPagina $page) {
            return $this->mapPage($page);
        })->values()->all();
    }

    /**
     * Páginas publicadas de todos los roles (para PDF global).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function pagesGroupedByRole(): array
    {
        $pages = ManualPagina::query()
            ->with(['bloques' => function ($q) {
                $q->whereNull('parent_id')->orderBy('orden')->orderBy('id');
            }, 'bloques.children.children.children'])
            ->where('publicado', true)
            ->orderBy('role_slug')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($pages as $page) {
            $grouped[$page->role_slug][] = $this->mapPage($page);
        }

        return $grouped;
    }

    /**
     * Ruta absoluta de un media CMS (para PDF / assets).
     */
    public function absoluteMediaPathForPdf(int $id): ?string
    {
        $media = \App\Models\ManualUsuario\ManualMedia::query()->find($id);
        if (!$media || !$media->path) {
            return null;
        }

        $disk = config('manual_usuario.storage_disk', 'local');
        if (!\Illuminate\Support\Facades\Storage::disk($disk)->exists($media->path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk($disk)->path($media->path);
    }

    /**
     * HTML simple de una página CMS para DomPDF.
     */
    public function pageToHtml(array $page): string
    {
        $parts = [];
        $parts[] = '<h3>' . e($page['titulo'] ?? '') . '</h3>';
        if (!empty($page['descripcion'])) {
            $parts[] = '<p>' . e($page['descripcion']) . '</p>';
        }

        foreach ($page['blocks'] ?? [] as $block) {
            $parts[] = $this->blockTreeToHtml($block);
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockTreeToHtml(array $block): string
    {
        $html = $this->blockToHtml($block);
        foreach ($block['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $html .= "\n" . $this->blockTreeToHtml($child);
        }

        return $html;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapPageAdmin(ManualPagina $page): array
    {
        $base = $this->mapPage($page);
        $base['role_slug'] = $page->role_slug;
        $base['id_grupo'] = $page->id_grupo;
        $base['publicado'] = (bool) $page->publicado;
        $base['created_at'] = optional($page->created_at)?->toIso8601String();
        $base['updated_at'] = optional($page->updated_at)?->toIso8601String();

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapBlockAdmin(ManualBloque $bloque): array
    {
        return $this->mapBlock($bloque);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPage(ManualPagina $page): array
    {
        $roots = $page->relationLoaded('bloques')
            ? $page->bloques
            : $page->bloques()->whereNull('parent_id')->with(['children.children.children'])->orderBy('orden')->orderBy('id')->get();

        return [
            'id' => $page->id,
            'modulo_key' => $page->modulo_key,
            'titulo' => $page->titulo,
            'descripcion' => $page->descripcion,
            'orden' => $page->orden,
            'blocks' => $roots->map(fn (ManualBloque $bloque) => $this->mapBlock($bloque))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBlock(ManualBloque $bloque): array
    {
        $tipo = ManualBloque::normalizeTipo((string) $bloque->tipo);
        $payload = $this->normalizePayload($tipo, is_array($bloque->payload) ? $bloque->payload : []);

        $snap = $payload['snapshot'] ?? [];
        if ($tipo === ManualBloque::TIPO_MEDIA && !empty($snap['media_id']) && empty($snap['url'])) {
            $snap['url'] = url('/api/manual-usuario/media/' . (int) $snap['media_id']);
            $payload['snapshot'] = $snap;
        }

        $children = [];
        if ($bloque->relationLoaded('children')) {
            $children = $bloque->children->map(fn (ManualBloque $child) => $this->mapBlock($child))->values()->all();
        }

        return [
            'id' => $bloque->id,
            'pagina_id' => $bloque->pagina_id,
            'parent_id' => $bloque->parent_id,
            'tipo' => $tipo,
            'titulo' => $bloque->titulo,
            'clave' => $bloque->clave,
            'payload' => $payload,
            'orden' => $bloque->orden,
            'children' => $children,
        ];
    }

    /**
     * Unifica payload legacy → { subtitulo, source?, snapshot }.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(string $tipo, array $payload): array
    {
        if (isset($payload['snapshot']) && is_array($payload['snapshot'])) {
            return [
                'subtitulo' => $payload['subtitulo'] ?? null,
                'source' => $payload['source'] ?? null,
                'snapshot' => $payload['snapshot'],
            ];
        }

        // Legacy flat payloads
        $snapshot = $payload;
        unset($snapshot['subtitulo'], $snapshot['source']);

        if ($tipo === ManualBloque::TIPO_TEXTO && isset($payload['body'])) {
            $snapshot = ['body' => $payload['body']];
        }
        if ($tipo === ManualBloque::TIPO_CALLOUT) {
            $snapshot = [
                'tone' => $payload['tone'] ?? 'info',
                'title' => $payload['title'] ?? '',
                'body' => $payload['body'] ?? '',
            ];
        }
        if ($tipo === ManualBloque::TIPO_MEDIA) {
            $snapshot = [
                'media_id' => $payload['media_id'] ?? null,
                'alt' => $payload['alt'] ?? '',
                'caption' => $payload['caption'] ?? '',
                'url' => $payload['url'] ?? null,
            ];
        }
        if ($tipo === ManualBloque::TIPO_FLOW) {
            $snapshot = [
                'hint' => $payload['hint'] ?? null,
                'steps' => $payload['steps'] ?? [],
            ];
        }
        if ($tipo === ManualBloque::TIPO_EMBED) {
            $snapshot = [
                'catalog_key' => $payload['catalog_key'] ?? null,
                'label' => $payload['label'] ?? '',
                'html' => $payload['html'] ?? '',
                'css' => $payload['css'] ?? '',
            ];
        }
        if ($tipo === ManualBloque::TIPO_TABLA) {
            $snapshot = [
                'columns' => $payload['columns'] ?? $payload['headers'] ?? [],
                'filters' => $payload['filters'] ?? [],
                'rows' => $payload['rows'] ?? [],
            ];
        }
        if ($tipo === ManualBloque::TIPO_GRUPO) {
            $snapshot = [];
        }
        if ($tipo === ManualBloque::TIPO_FILTROS) {
            $snapshot = [
                'fields' => $payload['fields'] ?? [],
                'hint' => $payload['hint'] ?? null,
            ];
        }
        if ($tipo === ManualBloque::TIPO_TABS) {
            $snapshot = [
                'active' => $payload['active'] ?? null,
                'tabs' => $payload['tabs'] ?? [],
                'hint' => $payload['hint'] ?? null,
            ];
        }
        if ($tipo === ManualBloque::TIPO_TOOLBAR) {
            $snapshot = [
                'buttons' => $payload['buttons'] ?? [],
                'hint' => $payload['hint'] ?? null,
            ];
        }

        return [
            'subtitulo' => $payload['subtitulo'] ?? null,
            'source' => $payload['source'] ?? null,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockToHtml(array $block): string
    {
        $titulo = trim((string) ($block['titulo'] ?? ''));
        $payload = is_array($block['payload'] ?? null) ? $block['payload'] : [];
        $tipo = ManualBloque::normalizeTipo((string) ($block['tipo'] ?? ''));
        $snap = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : $payload;
        $out = [];

        if ($titulo !== '') {
            $out[] = '<h4>' . e($titulo) . '</h4>';
        }
        if (!empty($payload['subtitulo'])) {
            $out[] = '<p><em>' . e((string) $payload['subtitulo']) . '</em></p>';
        }

        switch ($tipo) {
            case ManualBloque::TIPO_TEXTO:
                $out[] = '<p>' . nl2br(e((string) ($snap['body'] ?? ''))) . '</p>';
                break;

            case ManualBloque::TIPO_TOOLBAR:
                $buttons = collect($snap['buttons'] ?? [])->pluck('label')->filter()->implode(', ');
                if ($buttons !== '') {
                    $out[] = '<p><strong>Botones:</strong> ' . e($buttons) . '</p>';
                }
                break;

            case ManualBloque::TIPO_FILTROS:
                $fields = collect($snap['fields'] ?? [])->map(function ($f) {
                    $label = (string) ($f['label'] ?? '');
                    $value = (string) ($f['value'] ?? '');

                    return $label . ($value !== '' ? ': ' . $value : '');
                })->filter()->implode('; ');
                if ($fields !== '') {
                    $out[] = '<p><strong>Filtros:</strong> ' . e($fields) . '</p>';
                }
                break;

            case ManualBloque::TIPO_TABS:
                $tabs = collect($snap['tabs'] ?? [])->pluck('label')->filter()->implode(', ');
                if ($tabs !== '') {
                    $out[] = '<p><strong>Pestañas:</strong> ' . e($tabs) . '</p>';
                }
                break;

            case ManualBloque::TIPO_TABLA:
                $cols = $snap['columns'] ?? [];
                if (is_array($cols) && count($cols) > 0) {
                    $out[] = '<p><strong>' . e(implode(' | ', array_map('strval', $cols))) . '</strong></p>';
                }
                foreach ((array) ($snap['rows'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $out[] = '<p>' . e(implode(' | ', array_map('strval', $row))) . '</p>';
                }
                break;

            case ManualBloque::TIPO_CALLOUT:
                $tone = (string) ($snap['tone'] ?? 'info');
                $text = (string) ($snap['body'] ?? '');
                $out[] = '<p><strong>[' . e(Str::upper($tone)) . ']</strong> ' . nl2br(e($text)) . '</p>';
                break;

            case ManualBloque::TIPO_MEDIA:
                $caption = (string) ($snap['caption'] ?? $snap['alt'] ?? 'Captura');
                $url = (string) ($snap['url'] ?? '');
                if ($url !== '') {
                    $out[] = '<p><img src="' . e($url) . '" alt="' . e($caption) . '"></p>';
                }
                $out[] = '<p><em>Captura: ' . e($caption) . '</em></p>';
                break;

            case ManualBloque::TIPO_EMBED:
                $label = (string) ($snap['label'] ?? 'UI');
                $html = (string) ($snap['html'] ?? '');
                $out[] = '<div>' . $html . '</div>';
                $out[] = '<p><em>Componente UI: ' . e($label) . '</em></p>';
                break;

            case ManualBloque::TIPO_FLOW:
                $n = 1;
                foreach ((array) ($snap['steps'] ?? []) as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $out[] = '<p><strong>' . $n . '. ' . e((string) ($step['title'] ?? '')) . '</strong><br>'
                        . nl2br(e((string) ($step['body'] ?? ''))) . '</p>';
                    $n++;
                }
                break;

            case ManualBloque::TIPO_GRUPO:
                if (!empty($block['clave'])) {
                    $out[] = '<p><code>' . e((string) $block['clave']) . '</code></p>';
                }
                break;

            default:
                if (!empty($snap['body'])) {
                    $out[] = '<p>' . nl2br(e((string) $snap['body'])) . '</p>';
                }
                break;
        }

        return implode("\n", $out);
    }
}
