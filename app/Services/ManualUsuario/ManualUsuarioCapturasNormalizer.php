<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;

/**
 * Normaliza claves/nombres de capturas y desagrupa pasos que no deben compartir media.
 * No borra el copy de los bloques.
 */
class ManualUsuarioCapturasNormalizer
{
    /**
     * @param  array{dry_run?:bool}  $options
     * @return array<string, mixed>
     */
    public function normalize(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $updated = 0;
        $unchanged = 0;
        $invalid = 0;
        $groups = [];
        $planRows = [];

        $blocks = ManualBloque::query()
            ->with(['pagina', 'parent'])
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->orderBy('id')
            ->get();

        $mediaAlts = [];
        $mediaIds = $blocks->map(function (ManualBloque $block) {
            return (int) data_get($block->payload, 'snapshot.media_id', 0);
        })->filter()->unique()->values();
        if ($mediaIds->isNotEmpty()) {
            $mediaAlts = ManualMedia::query()
                ->whereIn('id', $mediaIds)
                ->pluck('alt', 'id')
                ->all();
        }

        foreach ($blocks as $block) {
            $payload = is_array($block->payload) ? $block->payload : [];
            $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot'])
                ? $payload['snapshot']
                : [];
            $page = $block->pagina;
            $parent = $block->parent;
            $modulo = $page ? (string) $page->modulo_key : '';
            $flow = $parent ? (string) $parent->titulo : '';
            $identity = ManualUsuarioCapturaShare::currentIdentity($snapshot);
            try {
                $expected = ManualUsuarioCapturaShare::expectedKeyFromSnapshot(
                    $snapshot,
                    $modulo,
                    !empty($snapshot['capture_flow']) ? (string) $snapshot['capture_flow'] : $flow,
                    (string) $block->titulo,
                    (int) $block->orden
                );
            } catch (\InvalidArgumentException $e) {
                $invalid++;
                $expected = $identity ?: '';
            }
            $derived = ManualUsuarioCapturaNombre::fromSnapshot(
                $snapshot,
                (string) $block->titulo,
                $page ? (string) $page->titulo : null
            );
            $mediaId = !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null;
            $planRows[] = [
                'block_id' => (int) $block->id,
                'identity' => $identity,
                'expected' => $expected,
                'media_id' => $mediaId,
                'media_alt' => $mediaId && isset($mediaAlts[$mediaId]) ? (string) $mediaAlts[$mediaId] : '',
                'nombre' => isset($snapshot['nombre']) ? (string) $snapshot['nombre'] : '',
                'derived' => $derived,
                'step_title' => isset($snapshot['capture_step']['title'])
                    ? (string) $snapshot['capture_step']['title']
                    : ManualUsuarioCapturaNombre::stripFotoPrefix((string) $block->titulo),
                'page_titulo' => $page ? (string) $page->titulo : '',
                'flow' => ManualUsuarioCapturaNombre::stripPasosPrefix(
                    !empty($snapshot['capture_flow']) ? (string) $snapshot['capture_flow'] : $flow
                ),
                'modulo' => $modulo,
            ];
        }

        $plan = ManualUsuarioCapturaShare::planUngroup($planRows);
        $rekey = $plan['rekey'];
        $unlink = array_flip($plan['unlink_media']);
        $rename = $plan['rename'];

        foreach ($blocks as $block) {
            $payload = is_array($block->payload) ? $block->payload : [];
            $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot'])
                ? $payload['snapshot']
                : [];
            $blockId = (int) $block->id;
            $changed = false;

            if (isset($rekey[$blockId])) {
                $snapshot['capture_key'] = $rekey[$blockId];
                unset($snapshot['capture_alias_of']);
                $changed = true;
            }

            $identity = ManualUsuarioCapturaShare::currentIdentity($snapshot);
            if ($identity === null) {
                $groups['_sin-clave'][] = [
                    'block_id' => $blockId,
                    'role' => $block->pagina ? (string) $block->pagina->role_slug : null,
                    'modulo' => $block->pagina ? (string) $block->pagina->modulo_key : null,
                    'media_id' => !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null,
                ];
                if (!$changed) {
                    $unchanged++;
                    continue;
                }
            } else {
                try {
                    $canonicalOutput = ManualUsuarioCaptureKey::output($identity);
                } catch (\InvalidArgumentException $e) {
                    $invalid++;
                    continue;
                }
                if (($snapshot['capture_output'] ?? null) !== $canonicalOutput) {
                    $snapshot['capture_output'] = $canonicalOutput;
                    $changed = true;
                }
                $key = !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
                if ($key && $key !== $identity && ($snapshot['capture_alias_of'] ?? null) !== $identity) {
                    $snapshot['capture_alias_of'] = $identity;
                    $changed = true;
                }
                $groups[$identity][] = [
                    'block_id' => $blockId,
                    'role' => $block->pagina ? (string) $block->pagina->role_slug : null,
                    'modulo' => $block->pagina ? (string) $block->pagina->modulo_key : null,
                    'media_id' => !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null,
                ];
            }

            if (isset($unlink[$blockId])) {
                $snapshot['media_id'] = null;
                $snapshot['url'] = null;
                $changed = true;
            }
            if (isset($rename[$blockId]) && $rename[$blockId] !== '') {
                $snapshot['nombre'] = $rename[$blockId];
                $changed = true;
            }

            $page = $block->pagina;
            $parent = $block->parent;
            if (empty($snapshot['capture_flow']) && $parent && $parent->titulo) {
                $snapshot['capture_flow'] = ManualUsuarioCapturaNombre::stripPasosPrefix((string) $parent->titulo);
                $changed = true;
            }
            if (empty($snapshot['capture_modulo']) && $page && $page->modulo_key) {
                $snapshot['capture_modulo'] = (string) $page->modulo_key;
                $changed = true;
            }
            $stepTitle = isset($snapshot['capture_step']['title'])
                ? trim((string) $snapshot['capture_step']['title'])
                : '';
            if ($stepTitle === '') {
                $snapshot['capture_step'] = [
                    'number' => max(1, (int) $block->orden),
                    'title' => ManualUsuarioCapturaNombre::stripFotoPrefix((string) $block->titulo),
                ];
                $changed = true;
            }

            if (!$changed) {
                $unchanged++;
                continue;
            }

            $updated++;
            if ($dryRun) {
                continue;
            }
            $payload['snapshot'] = $snapshot;
            $block->payload = $payload;
            $block->save();
        }

        $shared = [];
        foreach ($groups as $identity => $members) {
            if (count($members) < 2) {
                continue;
            }
            $shared[] = [
                'capture_key' => $identity,
                'blocks' => count($members),
                'roles' => array_values(array_unique(array_filter(array_column($members, 'role')))),
                'block_ids' => array_column($members, 'block_id'),
            ];
        }
        usort($shared, fn (array $a, array $b) => $b['blocks'] <=> $a['blocks']);

        $sharedBlocks = array_sum(array_column($shared, 'blocks'));
        $aliasBlocks = max(0, $sharedBlocks - count($shared));

        return [
            'dry_run' => $dryRun,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'invalid' => $invalid,
            'shared_keys' => count($shared),
            'shared_blocks' => $sharedBlocks,
            'alias_blocks' => $aliasBlocks,
            'groups' => $shared,
            'rekeyed' => count($rekey),
            'unlinked' => count($plan['unlink_media']),
            'renamed' => count($rename),
            'split_flows' => $plan['flows'],
        ];
    }
}
