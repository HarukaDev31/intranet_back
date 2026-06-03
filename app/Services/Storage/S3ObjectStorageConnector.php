<?php

namespace App\Services\Storage;

use App\Contracts\ObjectStorageConnectorInterface;
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

    public function uploadDisk(): string
    {
        $disk = (string) config('object_storage.upload_disk', 'local');

        return $disk !== '' ? $disk : 'local';
    }

    public function storeUploadedFile(UploadedFile $file, string $directory, string $filename): string
    {
        $directory = trim($directory, '/');
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
        $relativePath = ltrim($relativePath, '/');

        return Storage::disk($this->uploadDisk())->put($relativePath, $contents);
    }

    public function exists(string $relativePath): bool
    {
        if ($relativePath === '' || filter_var($relativePath, FILTER_VALIDATE_URL)) {
            return filter_var($relativePath, FILTER_VALIDATE_URL) !== false;
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return false;
        }

        foreach ($this->disksToProbe() as $disk) {
            if (Storage::disk($disk)->exists($relativePath)) {
                return true;
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
        if (empty($relativePath)) {
            return null;
        }

        if (filter_var($relativePath, FILTER_VALIDATE_URL)) {
            return $relativePath;
        }

        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return null;
        }
        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            return null;
        }

        if ($disk === 's3') {
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
            return Storage::disk('public')->url($relativePath);
        }

        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl . '/storage/' . $relativePath;
    }

    public function temporaryUrl(string $relativePath, ?int $minutes = null): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === null) {
            throw new RuntimeException('Ruta de archivo inválida.');
        }
        $disk = $this->diskForPath($relativePath);

        if ($disk === null) {
            throw new RuntimeException('Archivo no encontrado: ' . $relativePath);
        }

        $minutes = $minutes ?? (int) config('object_storage.signed_url_minutes', 120);

        if ($disk === 's3') {
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

        $stream = Storage::disk($disk)->readStream($relativePath);
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
        if ($relativePath === '' || filter_var($relativePath, FILTER_VALIDATE_URL)) {
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
}
