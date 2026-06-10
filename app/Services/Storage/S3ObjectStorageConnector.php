<?php

namespace App\Services\Storage;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Support\Storage\StoragePathSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class S3ObjectStorageConnector implements ObjectStorageConnectorInterface
{
    /** @var array<string, string> */
    private $tempDownloads = [];

    /**
     * Ruta relativa normalizada → clave S3 real en bucket (cuando no coincide con disks.s3.root).
     *
     * @var array<string, string>
     */
    private $bareS3Keys = [];

    public function uploadDisk(): string
    {
        $disk = (string) config('object_storage.upload_disk', 'local');

        return $disk !== '' ? $disk : 'local';
    }

    public function storeUploadedFile(UploadedFile $file, string $directory, string $filename): string
    {
        $directory = StoragePathSanitizer::relativePath(trim($directory, '/'));
        $filename = StoragePathSanitizer::fileName($filename);
        $stored = $file->storeAs($directory, $filename, ['disk' => $this->uploadDisk()]);

        if ($stored === false || $stored === '') {
            throw new RuntimeException('No se pudo guardar el archivo en almacenamiento.');
        }

        return $this->normalizeRelativePath($stored);
    }

    public function putContents(string $relativePath, string $contents): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null || $relativePath === '') {
            throw new RuntimeException('Ruta relativa inválida para guardar archivo.');
        }

        $relativePath = StoragePathSanitizer::relativePath($relativePath);
        if ($relativePath === '') {
            throw new RuntimeException('Ruta relativa inválida para guardar archivo.');
        }

        if (!$this->put($relativePath, $contents)) {
            throw new RuntimeException('No se pudo guardar el archivo en almacenamiento.');
        }

        return $relativePath;
    }

    public function normalizeRelativePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) && $parsed !== '' ? $parsed : $path;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        $strip = [
            'storage/app/public/',
            'storage/app/',
            'storage/',
            'app/public/',
            'public/',
            'files/',
        ];
        $changed = true;
        while ($changed && $path !== '') {
            $changed = false;
            foreach ($strip as $prefix) {
                if (stripos($path, $prefix) === 0) {
                    $path = substr($path, strlen($prefix));
                    $changed = true;
                }
            }
        }

        return $path === '' ? null : $path;
    }

    public function put(string $relativePath, $contents): bool
    {
        $relativePath = StoragePathSanitizer::relativePath(ltrim($relativePath, '/'));
        if ($relativePath === '') {
            throw new RuntimeException('Ruta relativa inválida para guardar archivo.');
        }

        $disk = $this->uploadDisk();

        try {
            $ok = Storage::disk($disk)->put($relativePath, $contents);
        } catch (\Throwable $e) {
            Log::error('S3ObjectStorageConnector: put falló', [
                'disk' => $disk,
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'No se pudo subir el archivo (' . $disk . '): ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($ok === false) {
            Log::error('S3ObjectStorageConnector: put devolvió false', [
                'disk' => $disk,
                'path' => $relativePath,
            ]);

            throw new RuntimeException('No se pudo subir el archivo (' . $disk . '): ' . $relativePath);
        }

        return true;
    }

    public function exists(string $relativePath): bool
    {
        if ($relativePath === '') {
            return false;
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return false;
        }

        foreach ($this->disksToProbe() as $disk) {
            if (Storage::disk($disk)->exists($relativePath)) {
                unset($this->bareS3Keys[$relativePath]);

                return true;
            }
        }

        if ($this->uploadDisk() === 's3') {
            foreach ($this->bareBucketKeyCandidates($relativePath) as $bareKey) {
                if ($this->existsAtBareBucketKey($bareKey)) {
                    $this->bareS3Keys[$relativePath] = $bareKey;

                    return true;
                }
            }
        }

        return false;
    }

    public function delete(string $relativePath): bool
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return false;
        }
        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            return false;
        }

        return Storage::disk($disk)->delete($relativePath);
    }

    public function url(string $relativePath): ?string
    {
        if ($relativePath === '') {
            return null;
        }

        $relativePath = $this->resolveInputToRelativePath($relativePath);
        if ($relativePath === null) {
            return null;
        }

        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            return null;
        }

        if ($disk === 's3') {
            if ($this->shouldServeViaCdn()) {
                return $this->buildCdnUrl($this->cdnRelativePath($relativePath));
            }

            try {
                return $this->temporaryUrl($relativePath);
            } catch (\Throwable $e) {
                Log::warning('S3ObjectStorageConnector: fallback a url pública', [
                    'path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);

                return Storage::disk('s3')->url($relativePath);
            }
        }

        if ($disk === 'public') {
            if ($this->shouldServeViaCdn()) {
                return $this->buildCdnUrl($relativePath);
            }

            return Storage::disk('public')->url($relativePath);
        }

        return $this->legacyAppStorageUrl($relativePath);
    }

    public function temporaryUrl(string $relativePath, ?int $minutes = null): string
    {
        $relativePath = $this->resolveInputToRelativePath($relativePath);
        if ($relativePath === null) {
            throw new RuntimeException('Ruta de archivo inválida.');
        }

        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            throw new RuntimeException('Archivo no encontrado: ' . $relativePath);
        }

        $minutes = $minutes ?? (int) config('object_storage.signed_url_minutes', 120);

        if ($disk === 's3') {
            if ($this->shouldServeViaCdn()) {
                return $this->buildCdnUrl($this->cdnRelativePath($relativePath));
            }

            if (isset($this->bareS3Keys[$relativePath])) {
                return $this->temporaryUrlForBucketKey($this->bareS3Keys[$relativePath], $minutes);
            }

            return Storage::disk('s3')->temporaryUrl(
                $relativePath,
                now()->addMinutes($minutes)
            );
        }

        return $this->url($relativePath);
    }

    public function localPath(string $relativePath): string
    {
        if (filter_var($relativePath, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('localPath no aplica a URLs remotas.');
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            throw new RuntimeException('Ruta de archivo inválida.');
        }
        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            throw new RuntimeException('Archivo no encontrado: ' . $relativePath);
        }

        if ($disk === 'local' || $disk === 'public') {
            $root = $disk === 'public'
                ? storage_path('app/public')
                : storage_path('app');

            return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        }

        if (isset($this->tempDownloads[$relativePath]) && is_file($this->tempDownloads[$relativePath])) {
            return $this->tempDownloads[$relativePath];
        }

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $tmp = tempnam(sys_get_temp_dir(), 's3dl_');
        if ($extension !== '') {
            $renamed = $tmp . '.' . $extension;
            rename($tmp, $renamed);
            $tmp = $renamed;
        }

        $stream = false;
        if (isset($this->bareS3Keys[$relativePath])) {
            $stream = $this->readStreamAtBareBucketKey($this->bareS3Keys[$relativePath]);
        }
        if ($stream === false) {
            $stream = Storage::disk($disk)->readStream($relativePath);
        }
        if ($stream === false) {
            throw new RuntimeException('No se pudo leer el archivo desde S3: ' . $relativePath);
        }

        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->tempDownloads[$relativePath] = $tmp;

        return $tmp;
    }

    public function mimeType(string $relativePath): ?string
    {
        $disk = $this->diskForPath(ltrim($relativePath, '/'));

        if ($disk === null) {
            return null;
        }

        try {
            return Storage::disk($disk)->mimeType(ltrim($relativePath, '/')) ?: null;
        } catch (\Throwable $e) {
            $local = $this->localPath($relativePath);

            return is_file($local) ? mime_content_type($local) : null;
        }
    }

    public function fileResponse(
        string $relativePath,
        ?string $mimeType = null,
        string $disposition = 'inline',
        ?string $downloadFilename = null
    ): Response {
        $relativePath = ltrim($relativePath, '/');
        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            abort(404, 'Archivo no encontrado');
        }

        $downloadFilename = $downloadFilename ?? basename($relativePath);
        $mimeType = $mimeType ?? $this->mimeType($relativePath) ?? 'application/octet-stream';

        if ($disk === 's3' && config('object_storage.serve_via_signed_redirect', true)) {
            $target = $this->url($relativePath);
            if ($target !== null) {
                return redirect()->away($target);
            }

            return redirect()->away($this->temporaryUrl($relativePath));
        }

        $storage = Storage::disk($disk);

        return new StreamedResponse(function () use ($storage, $relativePath) {
            $stream = $storage->readStream($relativePath);
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition . '; filename="' . $downloadFilename . '"',
        ]);
    }

    public function readStream(string $relativePath)
    {
        $relativePath = ltrim($relativePath, '/');
        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            return false;
        }

        return Storage::disk($disk)->readStream($relativePath);
    }

    public function diskForPath(string $relativePath): ?string
    {
        if ($relativePath === '') {
            return null;
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return null;
        }

        foreach ($this->disksToProbe() as $disk) {
            if (Storage::disk($disk)->exists($relativePath)) {
                return $disk;
            }
        }

        $sanitizedPath = StoragePathSanitizer::relativePath($relativePath);
        if ($sanitizedPath !== '' && $sanitizedPath !== $relativePath) {
            foreach ($this->disksToProbe() as $disk) {
                if (Storage::disk($disk)->exists($sanitizedPath)) {
                    return $disk;
                }
            }
            $relativePath = $sanitizedPath;
        }

        if ($this->uploadDisk() === 's3') {
            foreach ($this->bareBucketKeyCandidates($relativePath) as $bareKey) {
                if ($this->existsAtBareBucketKey($bareKey)) {
                    $this->bareS3Keys[$relativePath] = $bareKey;

                    return 's3';
                }
            }
        }

        return null;
    }

    /**
     * URL firmada directa al objeto en el bucket (sin CDN).
     * Necesaria para Meta WhatsApp cuando el CDN usa prefijo distinto a la clave real (ej. templates/ en probusiness-intranet).
     */
    public function metaPresignedUrl(string $relativePath, ?int $minutes = null): string
    {
        if ($this->uploadDisk() !== 's3') {
            throw new RuntimeException('metaPresignedUrl requiere FILESYSTEM_UPLOAD_DISK=s3');
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null || !$this->existsOnS3($relativePath)) {
            throw new RuntimeException('Archivo no encontrado en S3: ' . $relativePath);
        }

        $minutes = $minutes ?? (int) config('object_storage.signed_url_minutes', 120);

        if (isset($this->bareS3Keys[$relativePath])) {
            return $this->temporaryUrlForBucketKey($this->bareS3Keys[$relativePath], $minutes);
        }

        return Storage::disk('s3')->temporaryUrl(
            $relativePath,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Solo bucket S3 (sin discos legacy local/public).
     */
    public function existsOnS3(string $relativePath): bool
    {
        if ($this->uploadDisk() !== 's3') {
            return false;
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return false;
        }

        try {
            if (Storage::disk('s3')->exists($relativePath)) {
                unset($this->bareS3Keys[$relativePath]);

                return true;
            }
        } catch (\Throwable $e) {
            Log::debug('S3ObjectStorageConnector: existsOnS3 disk', [
                'relative' => $relativePath,
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($this->bareBucketKeyCandidates($relativePath) as $bareKey) {
            if ($this->existsAtBareBucketKey($bareKey)) {
                $this->bareS3Keys[$relativePath] = $bareKey;

                return true;
            }
        }

        return false;
    }

    /**
     * Clave S3 efectiva tras existsOnS3 (disco root o bare key en raíz del bucket).
     */
    public function resolvedS3Key(string $relativePath): ?string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return null;
        }

        if (isset($this->bareS3Keys[$relativePath])) {
            return $this->bareS3Keys[$relativePath];
        }

        return $this->expectedS3DiskKey($relativePath);
    }

    /**
     * Clave esperada en S3 vía disco Laravel (root + relative).
     */
    public function expectedS3DiskKey(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $this->normalizeRelativePath($relativePath) ?? $relativePath), '/');
        $root = trim(str_replace('\\', '/', (string) config('filesystems.disks.s3.root', '')), '/');

        return $root !== '' ? $root . '/' . $relativePath : $relativePath;
    }

    /**
     * @return string[]
     */
    private function bareBucketKeyCandidates(string $relativePath): array
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return [];
        }

        $candidates = [$relativePath];

        $prefix = trim((string) config('object_storage.s3_prefix', ''), '/');
        if ($prefix !== '' && stripos($relativePath, $prefix . '/') !== 0) {
            $candidates[] = $prefix . '/' . $relativePath;
        } elseif ($prefix === '' && stripos($relativePath, 'probusiness/') !== 0) {
            // Legacy: objetos subidos cuando AWS_UPLOAD_PREFIX=probusiness
            $candidates[] = 'probusiness/' . $relativePath;
        }

        // Bucket probusiness-intranet: objetos en templates/..., no probusiness-intranet/templates/...
        if (preg_match('#^probusiness-intranet/#i', $relativePath)) {
            $stripped = preg_replace('#^probusiness-intranet/#i', '', $relativePath);
            if ($stripped !== '' && $stripped !== $relativePath) {
                $candidates[] = $stripped;
            }
        }

        $root = trim(str_replace('\\', '/', (string) config('filesystems.disks.s3.root', '')), '/');
        if ($root !== '' && strpos($relativePath, $root . '/') === 0) {
            $stripped = substr($relativePath, strlen($root) + 1);
            if ($stripped !== '' && $stripped !== $relativePath) {
                $candidates[] = $stripped;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function temporaryUrlForBucketKey(string $key, int $minutes): string
    {
        $client = $this->s3Client();
        $bucket = config('filesystems.disks.s3.bucket');
        if ($client === null || !is_string($bucket) || $bucket === '') {
            throw new RuntimeException('S3 no configurado para URL firmada.');
        }

        $key = ltrim($key, '/');
        $command = $client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key,
        ]);

        $request = $client->createPresignedRequest($command, '+' . $minutes . ' minutes');

        return (string) $request->getUri();
    }

    private function existsAtBareBucketKey(string $key): bool
    {
        $client = $this->s3Client();
        $bucket = config('filesystems.disks.s3.bucket');
        if ($client === null || !is_string($bucket) || $bucket === '') {
            return false;
        }

        try {
            return $client->doesObjectExist($bucket, ltrim($key, '/'));
        } catch (\Throwable $e) {
            Log::debug('S3ObjectStorageConnector: existsAtBareBucketKey', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return resource|false
     */
    private function readStreamAtBareBucketKey(string $key)
    {
        $client = $this->s3Client();
        $bucket = config('filesystems.disks.s3.bucket');
        if ($client === null || !is_string($bucket) || $bucket === '') {
            return false;
        }

        try {
            $result = $client->getObject([
                'Bucket' => $bucket,
                'Key' => ltrim($key, '/'),
            ]);

            return $result['Body']->detach();
        } catch (\Throwable $e) {
            Log::warning('S3ObjectStorageConnector: readStreamAtBareBucketKey', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return \Aws\S3\S3Client|null
     */
    private function s3Client()
    {
        try {
            $adapter = Storage::disk('s3')->getAdapter();
            if (method_exists($adapter, 'getClient')) {
                return $adapter->getClient();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function disksToProbe(): array
    {
        $disks = [
            $this->uploadDisk(),
            (string) config('object_storage.legacy_disk', 'local'),
            (string) config('object_storage.legacy_public_disk', 'public'),
        ];

        return array_values(array_unique(array_filter($disks)));
    }

    /**
     * Normaliza rutas de BD, /storage/..., http://localhost:8001/storage/..., etc.
     */
    private function resolveInputToRelativePath(string $input): ?string
    {
        $input = str_replace('\\', '/', trim($input));

        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $path = parse_url($input, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return null;
            }

            return $this->normalizeRelativePath(rawurldecode($path));
        }

        return $this->normalizeRelativePath($input);
    }

    private function cdnBaseUrl(): string
    {
        return rtrim((string) config('object_storage.cdn_base_url', ''), '/');
    }

    private function shouldServeViaCdn(): bool
    {
        if ($this->cdnBaseUrl() === '') {
            return false;
        }

        if (config('object_storage.cdn_when_upload_disk_s3', true) && $this->uploadDisk() !== 's3') {
            return false;
        }

        /*
         * CDN sin prefijo (OBJECT_STORAGE_CDN_INCLUDE_PREFIX=false) sirve rutas en la raíz
         * del bucket: entregas/..., cargaconsolidada/...
         * Si AWS_UPLOAD_PREFIX está definido, el objeto real queda en probusiness/entregas/...
         * y la URL CDN (sin prefijo) no lo encuentra → usar URL firmada S3.
         */
        if (!filter_var(config('object_storage.cdn_include_s3_prefix', false), FILTER_VALIDATE_BOOLEAN)) {
            $prefix = trim((string) config('object_storage.s3_prefix', ''), '/');
            if ($prefix !== '') {
                return false;
            }
        }

        return true;
    }

    private function cdnRelativePath(string $relativePath): string
    {
        return isset($this->bareS3Keys[$relativePath])
            ? $this->bareS3Keys[$relativePath]
            : $relativePath;
    }

    private function buildCdnUrl(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $base = $this->cdnBaseUrl();
        $prefix = trim(str_replace('\\', '/', (string) config('object_storage.s3_prefix', '')), '/');

        if (filter_var(config('object_storage.cdn_include_s3_prefix', false), FILTER_VALIDATE_BOOLEAN) && $prefix !== '') {
            return $base . '/' . $prefix . '/' . $relativePath;
        }

        return $base . '/' . $relativePath;
    }

    private function legacyAppStorageUrl(string $relativePath): ?string
    {
        if (config('app.env') === 'local' && strpos($relativePath, 'contratos/') === 0) {
            return rtrim((string) config('app.url'), '/') . '/files/' . $relativePath;
        }

        return rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($relativePath, '/');
    }
}
