<?php

namespace App\Support;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Services\Storage\S3ObjectStorageConnector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class BrandLogoPaths
{
    private const CDN_DIR = 'logo_icons';

    /**
     * Ruta local legible para embed en emails/PDFs.
     * Orden: S3 → disco local del servidor → descarga CDN.
     */
    public static function resolve(string $filename): ?string
    {
        return self::resolveInDir(self::CDN_DIR, $filename);
    }

    /**
     * Resuelve un asset de marca en un directorio CDN relativo (ej. logo_icons, social_icons).
     * Si $dir es vacío, busca en la raíz de storage/CDN (ej. auto_accept_sign.png).
     */
    public static function resolveInDir(string $dir, string $filename): ?string
    {
        $filename = self::sanitizeFilename($filename);
        $dir = trim(str_replace('\\', '/', $dir), '/');
        $relativePath = $dir === '' ? $filename : ($dir . '/' . $filename);

        $fromS3 = self::resolveFromS3($relativePath);
        if ($fromS3 !== null) {
            return $fromS3;
        }

        foreach (self::localCandidates($dir, $filename) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return self::downloadCdnToCache($dir, $filename);
    }

    public static function cdnUrl(string $filename, string $dir = self::CDN_DIR): string
    {
        $filename = self::sanitizeFilename($filename);
        $dir = trim(str_replace('\\', '/', $dir), '/');
        $base = rtrim((string) config('object_storage.cdn_base_url', ''), '/');
        if ($base === '') {
            $base = 'https://cdn.probusiness.pe';
        }

        $path = $dir === '' ? $filename : ($dir . '/' . $filename);

        return $base . '/' . $path;
    }

    public static function header(): ?string
    {
        return self::resolve('logo_header.png');
    }

    public static function footer(): ?string
    {
        return self::resolve('logo_footer.png');
    }

    public static function headerWhite(): ?string
    {
        return self::resolve('logo_header_white.png');
    }

    public static function footerWhite(): ?string
    {
        return self::resolve('logo_footer_white.png');
    }

    /** Logo del contrato de servicio (PDF Dompdf). */
    public static function contrato(): ?string
    {
        return self::resolve('logo_contrato.png');
    }

    /** Firma Patricia (lado empresa en contratos PDF). */
    public static function firmaPatricia(): ?string
    {
        return self::resolveInDir('social_icons', 'firma_patricia.png');
    }

    /** Sello/firma de auto-aceptación (cron contracts:auto-sign). */
    public static function autoAcceptSign(): ?string
    {
        return self::resolveInDir('', 'auto_accept_sign.png');
    }

    /**
     * Embed base64 para Dompdf. Null si no se pudo resolver el archivo.
     */
    public static function toDataUri(?string $absolutePath): ?string
    {
        if ($absolutePath === null || $absolutePath === '') {
            return null;
        }

        $path = str_replace('\\', DIRECTORY_SEPARATOR, $absolutePath);
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $data = @file_get_contents($path);
        if ($data === false || $data === '') {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private static function sanitizeFilename(string $filename): string
    {
        return basename(str_replace(['\\', '..'], ['/', ''], $filename));
    }

    /**
     * @return list<string>
     */
    private static function localCandidates(string $dir, string $filename): array
    {
        $relative = $dir === '' ? $filename : ($dir . '/' . $filename);

        return [
            storage_path('app/public/' . $relative),
            public_path('storage/' . $relative),
            public_path('assets/brand/' . $filename),
        ];
    }

    private static function resolveFromS3(string $relativePath): ?string
    {
        try {
            $storage = app(ObjectStorageConnectorInterface::class);

            if ($storage instanceof S3ObjectStorageConnector && $storage->existsOnS3($relativePath)) {
                $local = $storage->localPath($relativePath);
                if (is_file($local) && is_readable($local)) {
                    return $local;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Brand logo: fallo resolución vía ObjectStorage', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
        }

        if (! self::isS3Configured()) {
            return null;
        }

        foreach (self::s3KeyCandidates($relativePath) as $key) {
            try {
                if (! Storage::disk('s3')->exists($key)) {
                    continue;
                }

                $cached = self::writeS3ContentsToCache(
                    (string) Storage::disk('s3')->get($key),
                    dirname($relativePath),
                    basename($relativePath)
                );
                if ($cached !== null) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                Log::debug('Brand logo: clave S3 no disponible', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function s3KeyCandidates(string $relativePath): array
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
            $candidates[] = 'probusiness/' . $relativePath;
        }

        $root = trim(str_replace('\\', '/', (string) config('filesystems.disks.s3.root', '')), '/');
        if ($root !== '' && stripos($relativePath, $root . '/') === 0) {
            $stripped = substr($relativePath, strlen($root) + 1);
            if ($stripped !== '' && $stripped !== $relativePath) {
                $candidates[] = $stripped;
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function isS3Configured(): bool
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $key = config('filesystems.disks.s3.key');

        return is_string($bucket) && $bucket !== ''
            && is_string($key) && $key !== '';
    }

    private static function writeS3ContentsToCache(string $body, string $dir, string $filename): ?string
    {
        if ($body === '' || strlen($body) < 50) {
            return null;
        }

        $dir = trim(str_replace('\\', '/', $dir), '/');
        $cacheDir = $dir === '' || $dir === '.'
            ? storage_path('framework/cache/brand')
            : storage_path('framework/cache/' . $dir);

        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
            return null;
        }

        $cacheFile = $cacheDir . '/' . $filename;
        file_put_contents($cacheFile, $body);

        return is_file($cacheFile) ? $cacheFile : null;
    }

    private static function downloadCdnToCache(string $dir, string $filename): ?string
    {
        $dir = trim(str_replace('\\', '/', $dir), '/');
        $cacheDir = $dir === ''
            ? storage_path('framework/cache/brand')
            : storage_path('framework/cache/' . $dir);

        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
            return null;
        }

        $cacheFile = $cacheDir . '/' . $filename;
        $url = self::cdnUrl($filename, $dir);

        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
            return $cacheFile;
        }

        try {
            $response = Http::timeout(15)->get($url);
            if (! $response->successful()) {
                Log::warning('Brand asset no disponible en CDN', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return is_file($cacheFile) ? $cacheFile : null;
            }

            return self::writeS3ContentsToCache($response->body(), $dir, $filename)
                ?? (is_file($cacheFile) ? $cacheFile : null);
        } catch (\Throwable $e) {
            Log::warning('Error descargando brand asset desde CDN', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return is_file($cacheFile) ? $cacheFile : null;
        }
    }
}
