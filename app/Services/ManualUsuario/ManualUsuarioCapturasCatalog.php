<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            ->with(['pagina', 'parent'])
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
                    'nombre' => null,
                    'derived_nombre' => null,
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
            $snapNombre = trim((string) ($snapshot['nombre'] ?? ''));
            if ($snapNombre !== '' && empty($groups[$bucket]['nombre'])) {
                $groups[$bucket]['nombre'] = $snapNombre;
            }
            if (empty($groups[$bucket]['derived_nombre'])) {
                $groups[$bucket]['derived_nombre'] = ManualUsuarioCapturaNombre::fromSnapshot(
                    $snapshot,
                    (string) $block->titulo,
                    $block->pagina ? (string) $block->pagina->titulo : null
                );
            }
            if ($mediaId && isset($media[$mediaId]) && $groups[$bucket]['media_id'] === null) {
                $row = $media[$mediaId];
                $groups[$bucket]['media_id'] = (int) $row->id;
                $groups[$bucket]['alt'] = $row->alt;
                $groups[$bucket]['url'] = $this->publicUrl((string) $row->path, (int) $row->id);
                $mediaNombre = trim((string) $row->nombre);
                if ($mediaNombre !== '') {
                    $groups[$bucket]['nombre'] = $mediaNombre;
                }
            }
        }

        $items = array_values($groups);
        usort($items, function (array $a, array $b) {
            return [$b['usage'], (string) $a['capture_key']] <=> [$a['usage'], (string) $b['capture_key']];
        });

        return array_map(function (array $item) {
            $item['nombre'] = ManualUsuarioCapturaNombre::resolve(
                $item['nombre'] ?? null,
                ['nombre' => $item['derived_nombre'] ?? '']
            );
            unset($item['derived_nombre']);
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
            $mediaNombre = trim((string) $media->nombre);
            if ($mediaNombre !== '') {
                $payload['snapshot']['nombre'] = $mediaNombre;
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

    /**
     * Actualiza nombre y/o archivo de la media compartida y propaga a bloques con la misma clave.
     *
     * @param  array{media_id?:int|null, capture_key?:string|null, block_id?:int|null, nombre?:string|null, role_slug?:string|null}  $data
     * @return array{item: array<string, mixed>|null, media: array<string, mixed>|null, updated: int}
     */
    public function updateShared(
        array $data,
        ?UploadedFile $file,
        ?int $uploadedBy,
        ManualUsuarioAdminService $admin
    ): array {
        return DB::transaction(function () use ($data, $file, $uploadedBy, $admin) {
            $block = $this->resolveBlock($data);
            $identity = $this->resolveIdentity($data, $block);
            $media = $this->resolveMedia($data, $block, $identity);
            $nombreInput = array_key_exists('nombre', $data) ? trim((string) $data['nombre']) : null;
            $roleSlug = isset($data['role_slug']) ? trim((string) $data['role_slug']) : null;
            if ($roleSlug === '') {
                $roleSlug = $block && $block->pagina ? (string) $block->pagina->role_slug : null;
            }

            if ($file) {
                if ($media) {
                    $admin->replaceMediaFile($media, $file, $roleSlug);
                    $media->refresh();
                } else {
                    $derived = $nombreInput !== null && $nombreInput !== ''
                        ? $nombreInput
                        : ($block
                            ? ManualUsuarioCapturaNombre::fromSnapshot(
                                is_array(data_get($block->payload, 'snapshot', []))
                                    ? data_get($block->payload, 'snapshot', [])
                                    : [],
                                (string) $block->titulo,
                                $block->pagina ? (string) $block->pagina->titulo : null
                            )
                            : null);
                    $media = $admin->storeMediaFile(
                        $file,
                        $roleSlug,
                        $uploadedBy,
                        $derived,
                        $derived
                    );
                }
            } elseif (!$media && $block === null && $identity === null) {
                throw new InvalidArgumentException('No hay imagen para actualizar.');
            }

            if ($media && $nombreInput !== null) {
                $nextNombre = $nombreInput !== '' ? $nombreInput : null;
                if ((string) $media->nombre !== (string) $nextNombre) {
                    $media->nombre = $nextNombre;
                    $media->save();
                }
            }

            $target = $block;
            if ($target === null && $identity) {
                $target = $this->firstBlockForIdentity($identity);
            }
            if ($target === null && $media) {
                $target = ManualBloque::query()
                    ->where('tipo', ManualBloque::TIPO_MEDIA)
                    ->where('payload->snapshot->media_id', (int) $media->id)
                    ->orderBy('id')
                    ->first();
            }

            $updated = 0;
            if ($target) {
                $payload = is_array($target->payload) ? $target->payload : [];
                if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                    $payload['snapshot'] = [];
                }
                $snapshot = $payload['snapshot'];
                $nextMediaId = $media ? (int) $media->id : (isset($snapshot['media_id']) ? (int) $snapshot['media_id'] : null);
                $nextUrl = $media
                    ? $this->publicUrl((string) $media->path, (int) $media->id)
                    : ($snapshot['url'] ?? null);
                $nextNombre = $nombreInput !== null
                    ? ($nombreInput !== '' ? $nombreInput : null)
                    : (isset($snapshot['nombre']) ? (string) $snapshot['nombre'] : ($media?->nombre));

                $same = (int) ($snapshot['media_id'] ?? 0) === (int) ($nextMediaId ?? 0)
                    && ($snapshot['url'] ?? null) === $nextUrl
                    && (string) ($snapshot['nombre'] ?? '') === (string) ($nextNombre ?? '');

                if (!$same) {
                    if ($nextMediaId) {
                        $snapshot['media_id'] = $nextMediaId;
                        $snapshot['url'] = $nextUrl;
                    }
                    if ($nextNombre !== null && $nextNombre !== '') {
                        $snapshot['nombre'] = $nextNombre;
                        if (empty($snapshot['alt'])) {
                            $snapshot['alt'] = $nextNombre;
                        }
                    } elseif ($nombreInput !== null && $nombreInput === '') {
                        unset($snapshot['nombre']);
                    }
                    $payload['snapshot'] = $snapshot;
                    $target->payload = $payload;
                    $target->save();
                    $updated = 1;
                }

                $updated += $this->propagate($target);
            } elseif ($media) {
                $updated = $this->propagateByMedia((int) $media->id, $nombreInput);
            }

            $item = $this->findListItem($identity, $media?->id, $target?->id);

            return [
                'item' => $item,
                'media' => $media ? $admin->mapMedia($media) : null,
                'updated' => $updated,
            ];
        });
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
            return $this->propagateByMedia(
                !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null,
                isset($snapshot['nombre']) ? (string) $snapshot['nombre'] : null,
                (int) $block->id,
                $snapshot['url'] ?? null
            );
        }
        $mediaId = array_key_exists('media_id', $snapshot)
            ? ($snapshot['media_id'] !== null ? (int) $snapshot['media_id'] : null)
            : null;
        $url = $snapshot['url'] ?? null;
        $nombre = isset($snapshot['nombre']) ? trim((string) $snapshot['nombre']) : '';
        $updated = 0;

        if ($mediaId && $nombre !== '') {
            $media = ManualMedia::query()->find($mediaId);
            if ($media && (string) $media->nombre !== $nombre) {
                $media->nombre = $nombre;
                $media->save();
            }
        }

        $siblings = ManualBloque::query()
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->where('id', '!=', $block->id)
            ->where(function ($q) use ($identity, $mediaId) {
                $q->where('payload->snapshot->capture_key', $identity)
                    ->orWhere('payload->snapshot->capture_alias_of', $identity);
                if ($mediaId) {
                    $q->orWhere('payload->snapshot->media_id', $mediaId);
                }
            })
            ->get();

        foreach ($siblings as $sibling) {
            $payload = is_array($sibling->payload) ? $sibling->payload : [];
            if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                $payload['snapshot'] = [];
            }
            $currentId = $payload['snapshot']['media_id'] ?? null;
            $currentUrl = $payload['snapshot']['url'] ?? null;
            $currentNombre = (string) ($payload['snapshot']['nombre'] ?? '');
            if (
                (int) $currentId === (int) ($mediaId ?? 0)
                && $currentUrl === $url
                && $currentNombre === $nombre
            ) {
                continue;
            }
            $payload['snapshot']['media_id'] = $mediaId;
            $payload['snapshot']['url'] = $url;
            $payload['snapshot']['capture_output'] = ManualUsuarioCaptureKey::output($identity);
            if ($nombre !== '') {
                $payload['snapshot']['nombre'] = $nombre;
            }
            $sibling->payload = $payload;
            $sibling->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array{media_id?:int|null, capture_key?:string|null, block_id?:int|null}  $data
     */
    private function resolveBlock(array $data): ?ManualBloque
    {
        $blockId = isset($data['block_id']) ? (int) $data['block_id'] : 0;
        if ($blockId < 1) {
            return null;
        }
        $block = ManualBloque::query()->with('pagina')->findOrFail($blockId);
        if ($block->tipo !== ManualBloque::TIPO_MEDIA) {
            throw new InvalidArgumentException('Solo los bloques de imagen pueden usar el catálogo.');
        }

        return $block;
    }

    /**
     * @param  array{media_id?:int|null, capture_key?:string|null}  $data
     */
    private function resolveIdentity(array $data, ?ManualBloque $block): ?string
    {
        $chosenKey = isset($data['capture_key']) ? trim((string) $data['capture_key']) : '';
        if ($chosenKey !== '') {
            try {
                return ManualUsuarioCaptureKey::validate($chosenKey);
            } catch (\InvalidArgumentException $e) {
                return $chosenKey;
            }
        }
        if ($block) {
            $snapshot = data_get($block->payload, 'snapshot', []);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            try {
                return ManualUsuarioCaptureKey::identityFromSnapshot($snapshot);
            } catch (\InvalidArgumentException $e) {
                return !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
            }
        }
        $mediaId = isset($data['media_id']) ? (int) $data['media_id'] : 0;
        if ($mediaId < 1) {
            return null;
        }
        $fromMedia = ManualBloque::query()
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->where('payload->snapshot->media_id', $mediaId)
            ->orderBy('id')
            ->first();
        if (!$fromMedia) {
            return null;
        }
        $snapshot = data_get($fromMedia->payload, 'snapshot', []);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        try {
            return ManualUsuarioCaptureKey::identityFromSnapshot($snapshot);
        } catch (\InvalidArgumentException $e) {
            return !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
        }
    }

    /**
     * @param  array{media_id?:int|null}  $data
     */
    private function resolveMedia(array $data, ?ManualBloque $block, ?string $identity): ?ManualMedia
    {
        $mediaId = isset($data['media_id']) ? (int) $data['media_id'] : 0;
        if ($mediaId < 1 && $block) {
            $mediaId = (int) data_get($block->payload, 'snapshot.media_id', 0);
        }
        if ($mediaId > 0) {
            return ManualMedia::query()->findOrFail($mediaId);
        }
        if ($identity) {
            $fromKey = $this->firstBlockForIdentity($identity);
            $fromId = $fromKey ? (int) data_get($fromKey->payload, 'snapshot.media_id', 0) : 0;
            if ($fromId > 0) {
                return ManualMedia::query()->find($fromId);
            }
        }

        return null;
    }

    private function firstBlockForIdentity(string $identity): ?ManualBloque
    {
        return ManualBloque::query()
            ->with('pagina')
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->where(function ($q) use ($identity) {
                $q->where('payload->snapshot->capture_key', $identity)
                    ->orWhere('payload->snapshot->capture_alias_of', $identity);
            })
            ->orderBy('id')
            ->first();
    }

    private function propagateByMedia(
        ?int $mediaId,
        ?string $nombre,
        ?int $exceptBlockId = null,
        mixed $url = null
    ): int {
        if (!$mediaId) {
            return 0;
        }
        $nombre = $nombre !== null ? trim($nombre) : '';
        $updated = 0;
        $siblings = ManualBloque::query()
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->where('payload->snapshot->media_id', $mediaId)
            ->when($exceptBlockId, function ($q) use ($exceptBlockId) {
                $q->where('id', '!=', $exceptBlockId);
            })
            ->get();

        foreach ($siblings as $sibling) {
            $payload = is_array($sibling->payload) ? $sibling->payload : [];
            if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                $payload['snapshot'] = [];
            }
            $changed = false;
            if ($url !== null && ($payload['snapshot']['url'] ?? null) !== $url) {
                $payload['snapshot']['url'] = $url;
                $changed = true;
            }
            if ($nombre !== '' && (string) ($payload['snapshot']['nombre'] ?? '') !== $nombre) {
                $payload['snapshot']['nombre'] = $nombre;
                $changed = true;
            }
            if (!$changed) {
                continue;
            }
            $sibling->payload = $payload;
            $sibling->save();
            $updated++;
        }

        return $updated;
    }

    private function findListItem(?string $identity, ?int $mediaId, ?int $blockId): ?array
    {
        foreach ($this->list() as $item) {
            if ($identity && ($item['capture_key'] ?? null) === $identity) {
                return $item;
            }
            if ($mediaId && (int) ($item['media_id'] ?? 0) === $mediaId) {
                return $item;
            }
            if ($blockId && in_array($blockId, $item['block_ids'] ?? [], true)) {
                return $item;
            }
        }

        return null;
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
        $nombre = trim((string) ($item['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Imagen del manual';
        }
        $usage = (int) $item['usage'];
        $hojas = $usage === 1 ? '1 hoja' : $usage . ' hojas';

        return $nombre . ' · ' . $hojas;
    }
}
