<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Traits\UsesObjectStorage;
use InvalidArgumentException;

/**
 * Catálogo de imágenes del manual agrupado por capture_key.
 */
class ManualUsuarioCapturasCatalog
{
    use UsesObjectStorage;

    public function __construct(
        private ManualUsuarioDbService $db
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $blocks = ManualBloque::query()
            ->with('pagina')
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->orderBy('id')
            ->get();

        $mediaIds = $blocks->map(function (ManualBloque $block) {
            return (int) data_get($block->payload, 'snapshot.media_id', 0);
        })->filter()->unique()->values();
        $media = $mediaIds->isEmpty()
            ? collect()
            : ManualMedia::query()->whereIn('id', $mediaIds)->get()->keyBy('id');

        $groups = [];
        foreach ($blocks as $block) {
            $snapshot = data_get($block->payload, 'snapshot', []);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            try {
                $identity = ManualUsuarioCaptureKey::identityFromSnapshot($snapshot);
            } catch (\InvalidArgumentException $e) {
                $identity = !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
            }
            $mediaId = !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null;
            $bucket = $identity ?: ($mediaId ? 'imagen-' . $mediaId : 'sin-clave-' . $block->id);
            if (!isset($groups[$bucket])) {
                $groups[$bucket] = [
                    'capture_key' => $identity,
                    'media_id' => null,
                    'url' => null,
                    'alt' => null,
                    'usage' => 0,
                    'roles' => [],
                    'pages' => [],
                    'block_ids' => [],
                ];
            }
            $groups[$bucket]['usage']++;
            $groups[$bucket]['block_ids'][] = (int) $block->id;
            $role = $block->pagina ? (string) $block->pagina->role_slug : null;
            if ($role && !in_array($role, $groups[$bucket]['roles'], true)) {
                $groups[$bucket]['roles'][] = $role;
            }
            if ($block->pagina) {
                $pageId = (int) $block->pagina->id;
                $already = false;
                foreach ($groups[$bucket]['pages'] as $page) {
                    if ((int) $page['id'] === $pageId) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $groups[$bucket]['pages'][] = [
                        'id' => $pageId,
                        'titulo' => (string) $block->pagina->titulo,
                        'role_slug' => (string) $block->pagina->role_slug,
                    ];
                }
            }
            if ($mediaId && isset($media[$mediaId]) && $groups[$bucket]['media_id'] === null) {
                $row = $media[$mediaId];
                $groups[$bucket]['media_id'] = (int) $row->id;
                $groups[$bucket]['alt'] = $row->alt;
                $groups[$bucket]['url'] = $this->publicUrl((string) $row->path, (int) $row->id);
            }
        }

        $items = array_values($groups);
        usort($items, function (array $a, array $b) {
            return [$b['usage'], (string) $a['capture_key']] <=> [$a['usage'], (string) $b['capture_key']];
        });

        return array_map(function (array $item) {
            $item['id'] = $item['media_id'] ?: $item['capture_key'];
            $item['label'] = $this->label($item);

            return $item;
        }, $items);
    }

    /**
     * Asigna una imagen a un bloque y a todos los que comparten su capture_key.
     *
     * @param  array{media_id?:int|null, capture_key?:string|null}  $data
     * @return array{block: array<string, mixed>, updated: int}
     */
    public function assignToBlock(int $blockId, array $data): array
    {
        $block = ManualBloque::query()->findOrFail($blockId);
        if ($block->tipo !== ManualBloque::TIPO_MEDIA) {
            throw new InvalidArgumentException('Solo los bloques de imagen pueden usar el catálogo.');
        }

        $mediaId = array_key_exists('media_id', $data) ? $data['media_id'] : false;
        $chosenKey = isset($data['capture_key']) ? trim((string) $data['capture_key']) : '';
        $payload = is_array($block->payload) ? $block->payload : [];
        if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
            $payload['snapshot'] = [];
        }

        if ($chosenKey !== '') {
            $payload['snapshot']['capture_key'] = ManualUsuarioCaptureKey::validate($chosenKey);
            $payload['snapshot']['capture_output'] = ManualUsuarioCaptureKey::output($chosenKey);
            unset($payload['snapshot']['capture_alias_of']);
        }
        if (is_int($mediaId) || (is_numeric($mediaId) && (int) $mediaId > 0)) {
            $media = ManualMedia::query()->findOrFail((int) $mediaId);
            $payload['snapshot']['media_id'] = (int) $media->id;
            $payload['snapshot']['url'] = $this->publicUrl((string) $media->path, (int) $media->id);
            if (empty($payload['snapshot']['alt']) && $media->alt) {
                $payload['snapshot']['alt'] = $media->alt;
            }
        } elseif ($mediaId === null || $mediaId === 0) {
            $payload['snapshot']['media_id'] = null;
            $payload['snapshot']['url'] = null;
        }

        $block->payload = $payload;
        $block->save();
        $updated = $this->propagate($block);
        $block->load(['children.children.children']);

        return [
            'block' => $this->db->mapBlockAdmin($block),
            'updated' => $updated + 1,
        ];
    }

    public function propagate(ManualBloque $block): int
    {
        $snapshot = data_get($block->payload, 'snapshot', []);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        try {
            $identity = ManualUsuarioCaptureKey::identityFromSnapshot($snapshot);
        } catch (\InvalidArgumentException $e) {
            return 0;
        }
        if ($identity === null) {
            return 0;
        }
        $mediaId = array_key_exists('media_id', $snapshot)
            ? ($snapshot['media_id'] !== null ? (int) $snapshot['media_id'] : null)
            : null;
        $url = $snapshot['url'] ?? null;
        $updated = 0;

        $siblings = ManualBloque::query()
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->where('id', '!=', $block->id)
            ->where(function ($q) use ($identity) {
                $q->where('payload->snapshot->capture_key', $identity)
                    ->orWhere('payload->snapshot->capture_alias_of', $identity);
            })
            ->get();

        foreach ($siblings as $sibling) {
            $payload = is_array($sibling->payload) ? $sibling->payload : [];
            if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                $payload['snapshot'] = [];
            }
            $currentId = $payload['snapshot']['media_id'] ?? null;
            $currentUrl = $payload['snapshot']['url'] ?? null;
            if ((int) $currentId === (int) ($mediaId ?? 0) && $currentUrl === $url) {
                continue;
            }
            $payload['snapshot']['media_id'] = $mediaId;
            $payload['snapshot']['url'] = $url;
            $payload['snapshot']['capture_output'] = ManualUsuarioCaptureKey::output($identity);
            $sibling->payload = $payload;
            $sibling->save();
            $updated++;
        }

        return $updated;
    }

    private function publicUrl(string $path, int $mediaId): string
    {
        $uploadPath = $this->storageUploadPathFromDb($path) ?: ltrim(str_replace('\\', '/', $path), '/');
        try {
            $url = $this->objectStorage()->url($uploadPath);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        } catch (\Throwable $e) {
            // fallback autenticado
        }

        return url('/api/manual-usuario/media/' . $mediaId);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function label(array $item): string
    {
        $key = $item['capture_key'] ?: ('imagen-' . ($item['media_id'] ?: '?'));
        $usage = (int) $item['usage'];
        $hojas = $usage === 1 ? '1 hoja' : $usage . ' hojas';

        return $key . ' · ' . $hojas;
    }
}
