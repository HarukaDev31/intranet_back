<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Models\ManualUsuario\ManualPagina;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Clona capturas de Carga consolidada Abiertos → Completados.
 * Descarga bytes desde host de producción (CDN). No pisa bloques ni media de Abiertos.
 */
class ManualUsuarioCloneAbiertosCompletados
{
    use UsesObjectStorage;

    public const DEFAULT_SOURCE = 'cargaconsolidada/abiertos';
    public const DEFAULT_TARGET = 'cargaconsolidada/completados';
    public const DEFAULT_CDN = 'https://cdn.probusiness.pe';

    /**
     * @param  array{
     *     dry_run?: bool,
     *     source?: string,
     *     target?: string,
     *     cdn?: string,
     *     force?: bool
     * }  $options
     * @return array<string, mixed>
     */
    public function clone(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $sourceModulo = (string) ($options['source'] ?? self::DEFAULT_SOURCE);
        $targetModulo = (string) ($options['target'] ?? self::DEFAULT_TARGET);
        $cdn = rtrim((string) ($options['cdn'] ?? self::DEFAULT_CDN), '/');
        $force = (bool) ($options['force'] ?? false);

        if ($sourceModulo === '' || $targetModulo === '' || $sourceModulo === $targetModulo) {
            throw new RuntimeException('Origen y destino deben ser módulos distintos.');
        }
        $this->assertProdCdn($cdn);

        $sourceRows = $this->mediaRows($sourceModulo);
        $targetRows = $this->mediaRows($targetModulo);
        $plan = ManualUsuarioCloneAbiertosCompletadosMapper::plan($sourceRows, $targetRows);

        $downloaded = 0;
        $linked = 0;
        $skippedExisting = 0;
        $failed = [];
        $links = [];
        $originUrls = [];

        foreach ($plan['links'] as $link) {
            $targetBlocks = $this->blocksByIds($link['target_block_ids']);
            if ($targetBlocks->isEmpty()) {
                $failed[] = $link + ['reason' => 'missing_target_blocks'];
                continue;
            }

            $already = $this->existingCloneMedia((string) $link['target_key']);
            $needsDownload = $already === null || $force;
            if (!$needsDownload && !$this->anyBlockNeedsMedia($targetBlocks, (int) $already->id, $force)) {
                $skippedExisting++;
                $links[] = $link + [
                    'status' => 'unchanged',
                    'target_media_id' => (int) $already->id,
                    'origin_url' => null,
                ];
                continue;
            }

            $bytes = null;
            $originUrl = null;
            $mime = 'image/png';
            if ($needsDownload || $already === null) {
                try {
                    [$bytes, $originUrl, $mime] = $this->downloadFromProd($link, $cdn);
                } catch (RuntimeException $e) {
                    $failed[] = $link + ['reason' => $e->getMessage()];
                    continue;
                }
                $downloaded++;
                $originUrls[] = $originUrl;
            }

            if ($dryRun) {
                $wouldLink = 0;
                foreach ($targetBlocks as $block) {
                    $current = (int) data_get($block->payload, 'snapshot.media_id', 0);
                    if ($force || $current < 1 || ($already && $current === (int) $already->id)) {
                        $wouldLink++;
                    }
                }
                $links[] = $link + [
                    'status' => 'would_link',
                    'origin_url' => $originUrl,
                    'target_media_id' => $already ? (int) $already->id : null,
                    'would_link' => $wouldLink,
                ];
                continue;
            }

            $media = $this->storeClone(
                (string) $link['target_key'],
                (string) $link['target_title'],
                $bytes,
                $mime,
                $already
            );
            $attached = $this->attachTargetBlocks($targetBlocks, $media, $force);
            $linked += $attached;
            $links[] = $link + [
                'status' => $attached > 0 ? 'linked' : 'unchanged',
                'origin_url' => $originUrl,
                'target_media_id' => (int) $media->id,
                'linked_blocks' => $attached,
            ];
        }

        return [
            'dry_run' => $dryRun,
            'source' => $sourceModulo,
            'target' => $targetModulo,
            'cdn' => $cdn,
            'source_blocks' => count($sourceRows),
            'target_blocks' => count($targetRows),
            'mapped' => count($plan['links']),
            'skipped_no_equivalent' => count($plan['skipped']),
            'skipped_existing' => $skippedExisting,
            'downloaded' => $downloaded,
            'linked' => $linked,
            'failed' => count($failed),
            'origin_urls' => array_values(array_unique(array_filter($originUrls))),
            'links' => $links,
            'skipped' => $plan['skipped'],
            'errors' => $failed,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediaRows(string $modulo): array
    {
        $pages = ManualPagina::query()
            ->where('modulo_key', $modulo)
            ->get(['id', 'role_slug', 'modulo_key', 'titulo']);
        if ($pages->isEmpty()) {
            return [];
        }

        $blocks = ManualBloque::query()
            ->with('parent')
            ->whereIn('pagina_id', $pages->pluck('id'))
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->orderBy('id')
            ->get();
        $mediaIds = $blocks->map(fn (ManualBloque $block) => (int) data_get($block->payload, 'snapshot.media_id', 0))
            ->filter()
            ->unique()
            ->values();
        $media = $mediaIds->isEmpty()
            ? collect()
            : ManualMedia::query()->whereIn('id', $mediaIds)->get()->keyBy('id');
        $pagesById = $pages->keyBy('id');

        $rows = [];
        foreach ($blocks as $block) {
            $page = $pagesById->get($block->pagina_id);
            if (!$page) {
                continue;
            }
            $snapshot = data_get($block->payload, 'snapshot', []);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $step = isset($snapshot['capture_step']) && is_array($snapshot['capture_step'])
                ? $snapshot['capture_step']
                : [];
            $mediaId = !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null;
            $row = $mediaId ? $media->get($mediaId) : null;
            $flow = ManualUsuarioCapturaNombre::stripPasosPrefix(
                (string) ($snapshot['capture_flow'] ?? ($block->parent?->titulo ?? ''))
            );
            $rows[] = [
                'block_id' => (int) $block->id,
                'role' => (string) $page->role_slug,
                'modulo' => (string) $page->modulo_key,
                'flow' => $flow,
                'step_number' => (int) ($step['number'] ?? 0),
                'step_title' => ManualUsuarioCapturaNombre::stripFotoPrefix((string) ($step['title'] ?? $block->titulo)),
                'capture_key' => (string) ($snapshot['capture_key'] ?? ''),
                'capture_output' => (string) ($snapshot['capture_output'] ?? ''),
                'media_id' => $mediaId,
                'media_path' => $row ? (string) $row->path : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array{0:string,1:string,2:string}
     */
    private function downloadFromProd(array $link, string $cdn): array
    {
        $relative = ltrim(str_replace('\\', '/', (string) ($link['source_path'] ?? '')), '/');
        $cdnOrigin = $relative !== ''
            ? $cdn . '/' . $relative
            : null;

        if ($relative !== '') {
            $fromS3 = $this->readProdObjectStorage($relative);
            if ($fromS3 !== null) {
                return [$fromS3[0], $cdnOrigin ?: $fromS3[1], $fromS3[2]];
            }
        }

        $candidates = [];
        if ($relative !== '') {
            try {
                $storageUrl = $this->objectStorage()->url($relative);
                if (is_string($storageUrl) && $storageUrl !== '') {
                    array_unshift($candidates, $storageUrl);
                }
            } catch (\Throwable $e) {
                // sigue con CDN
            }
            try {
                $signed = $this->objectStorage()->temporaryUrl($relative, 15);
                if (is_string($signed) && $signed !== '') {
                    array_unshift($candidates, $signed);
                }
            } catch (\Throwable $e) {
                // sigue con CDN
            }
            $candidates[] = $cdn . '/' . $relative;
        }
        $output = ltrim(str_replace('\\', '/', (string) ($link['source_output'] ?? '')), '/');
        if ($output !== '') {
            $candidates[] = $cdn . '/manual/capturas/' . $output;
            $candidates[] = $cdn . '/manual/capturas/' . basename($output);
        }
        $key = (string) ($link['source_key'] ?? '');
        if ($key !== '') {
            $candidates[] = $cdn . '/manual/capturas/' . $key . '.png';
        }

        $tried = [];
        foreach (array_unique($candidates) as $url) {
            $tried[] = $url;
            if (!$this->isAllowedProdUrl($url)) {
                continue;
            }
            $response = Http::timeout(90)
                ->withHeaders(['Accept' => 'image/png,image/jpeg,image/*,*/*'])
                ->get($url);
            if (!$response->successful()) {
                continue;
            }
            $bytes = $response->body();
            $mime = $this->imageMime($bytes);
            if ($mime === null) {
                continue;
            }

            return [$bytes, $url, $mime];
        }

        throw new RuntimeException(
            'No se pudo bajar la captura de producción. URLs: ' . implode(' | ', $tried)
        );
    }

    /**
     * Lee el objeto en el bucket S3 de producción. No usa PNG locales de resources/ ni disco local.
     *
     * @return array{0:string,1:string,2:string}|null
     */
    private function readProdObjectStorage(string $relative): ?array
    {
        try {
            if ($this->objectStorage()->uploadDisk() !== 's3') {
                return null;
            }
            $disk = $this->objectStorage()->diskForPath($relative);
            if ($disk !== 's3') {
                return null;
            }
            $stream = $this->objectStorage()->readStream($relative);
            if ($stream === false) {
                return null;
            }
            $bytes = stream_get_contents($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (!is_string($bytes) || $bytes === '') {
                return null;
            }
            $mime = $this->imageMime($bytes);

            return $mime ? [$bytes, 's3://' . $relative, $mime] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function storeClone(string $targetKey, string $stepTitle, ?string $bytes, string $mime, ?ManualMedia $existing): ManualMedia
    {
        if ($existing && $bytes === null) {
            return $existing;
        }
        if ($bytes === null || $bytes === '') {
            throw new RuntimeException('Sin bytes para clonar ' . $targetKey);
        }

        $ext = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $relative = 'manual/capturas/' . $targetKey . '.' . $ext;
        $localFile = storage_path('app/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $localDir = dirname($localFile);
        if (!is_dir($localDir)) {
            mkdir($localDir, 0775, true);
        }
        file_put_contents($localFile, $bytes);

        $dbPath = $relative;
        try {
            $dbPath = $this->storagePutContentsForCdn($relative, $bytes);
        } catch (\Throwable $e) {
            $dbPath = $relative;
        }

        $nombre = 'Completados — ' . ManualUsuarioCapturaNombre::stripFotoPrefix($stepTitle);
        $attrs = [
            'path' => $dbPath,
            'alt' => $targetKey,
            'mime' => $mime,
        ];
        if (Schema::hasColumn('manual_media', 'nombre')) {
            $attrs['nombre'] = $nombre;
        }

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();

            return $existing;
        }

        $found = ManualMedia::query()->where('alt', $targetKey)->orderByDesc('id')->first();
        if ($found) {
            $found->fill($attrs);
            $found->save();

            return $found;
        }

        return ManualMedia::query()->create($attrs);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ManualBloque>  $blocks
     */
    private function attachTargetBlocks($blocks, ManualMedia $media, bool $force): int
    {
        $linked = 0;
        foreach ($blocks as $block) {
            $payload = is_array($block->payload) ? $block->payload : [];
            if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                $payload['snapshot'] = [];
            }
            $current = !empty($payload['snapshot']['media_id']) ? (int) $payload['snapshot']['media_id'] : 0;
            if ($current > 0 && $current !== (int) $media->id && !$force) {
                $currentMedia = ManualMedia::query()->find($current);
                $currentAlt = $currentMedia ? (string) $currentMedia->alt : '';
                if ($currentAlt !== (string) $media->alt) {
                    continue;
                }
            }
            if ($current === (int) $media->id) {
                continue;
            }
            $payload['snapshot']['media_id'] = (int) $media->id;
            $block->payload = $payload;
            $block->save();
            $linked++;
        }

        return $linked;
    }

    /**
     * @param  int[]  $ids
     * @return \Illuminate\Support\Collection<int, ManualBloque>
     */
    private function blocksByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return collect();
        }

        return ManualBloque::query()->whereIn('id', $ids)->get();
    }

    private function existingCloneMedia(string $targetKey): ?ManualMedia
    {
        if ($targetKey === '') {
            return null;
        }

        return ManualMedia::query()->where('alt', $targetKey)->orderByDesc('id')->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ManualBloque>  $blocks
     */
    private function anyBlockNeedsMedia($blocks, int $mediaId, bool $force): bool
    {
        if ($force) {
            return true;
        }
        foreach ($blocks as $block) {
            $current = (int) data_get($block->payload, 'snapshot.media_id', 0);
            if ($current !== $mediaId) {
                return true;
            }
        }

        return false;
    }

    private function assertProdCdn(string $cdn): void
    {
        if (!$this->isAllowedProdUrl($cdn . '/manual/capturas/probe.png')) {
            throw new RuntimeException('El CDN debe ser de producción (cdn.probusiness.pe), no localhost ni QA.');
        }
    }

    private function isAllowedProdUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return false;
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }
        if (str_starts_with($host, 'qa-') || str_contains($host, '.qa.') || str_contains($host, 'localhost')) {
            return false;
        }

        $allowedExact = [
            'cdn.probusiness.pe',
            'intranetback.probusiness.pe',
            'intranet.probusiness.pe',
        ];
        if (in_array($host, $allowedExact, true)) {
            return true;
        }
        if (str_ends_with($host, '.probusiness.pe') && !str_starts_with($host, 'qa-')) {
            return true;
        }

        return str_contains($host, '.s3.')
            || str_ends_with($host, '.amazonaws.com')
            || str_ends_with($host, '.cloudfront.net');
    }

    private function imageMime(string $bytes): ?string
    {
        if (strlen($bytes) < 24) {
            return null;
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        return null;
    }
}
