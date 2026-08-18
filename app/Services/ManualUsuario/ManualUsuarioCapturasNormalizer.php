<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;

/**
 * Actualiza snapshots de bloques media para compartir capture_key/output.
 * No borra copy ni media_id.
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

        $blocks = ManualBloque::query()
            ->with('pagina')
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->orderBy('id')
            ->get();

        foreach ($blocks as $block) {
            $payload = is_array($block->payload) ? $block->payload : [];
            $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot'])
                ? $payload['snapshot']
                : [];
            $key = !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
            $aliasOf = !empty($snapshot['capture_alias_of']) ? (string) $snapshot['capture_alias_of'] : null;
            if ($key === null && $aliasOf === null) {
                $unchanged++;
                continue;
            }

            try {
                $identity = ManualUsuarioCaptureKey::identity($key, $aliasOf);
            } catch (\InvalidArgumentException $e) {
                $invalid++;
                continue;
            }

            $canonicalOutput = ManualUsuarioCaptureKey::output($identity);
            $changed = false;
            if (($snapshot['capture_output'] ?? null) !== $canonicalOutput) {
                $snapshot['capture_output'] = $canonicalOutput;
                $changed = true;
            }
            if ($key && $key !== $identity && ($snapshot['capture_alias_of'] ?? null) !== $identity) {
                $snapshot['capture_alias_of'] = $identity;
                $changed = true;
            }

            $groups[$identity][] = [
                'block_id' => (int) $block->id,
                'role' => $block->pagina ? (string) $block->pagina->role_slug : null,
                'modulo' => $block->pagina ? (string) $block->pagina->modulo_key : null,
                'media_id' => !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null,
            ];

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
        ];
    }
}
