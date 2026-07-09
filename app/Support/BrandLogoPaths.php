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
        $filename = self::sanitizeFilename($filename);
        $relativePath = self::CDN_DIR . '/' . $filename;

        $fromS3 = self::resolveFromS3($relativePath);
        if ($fromS3 !== null) {
            return $fromS3;
        }

        foreach (self::localCandidates($filename) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return self::downloadCdnToCache($filename);
    }

    public static function cdnUrl(string $filename): string
    {
        $filename = self::sanitizeFilename($filename);
        $base = rtrim((string) config('object_storage.cdn_base_url', ''), '/');
        if ($base === '') {
            $base = 'https://cdn.probusiness.pe';
        }

        return $base . '/' . self::CDN_DIR . '/' . $filename;
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

    private static function sanitizeFilename(string $filename): string
    {
        return basename(str_replace(['\\', '..'], ['/', ''], $filename));
    }

    /**
     * @return list<string>
     */
    private static function localCandidates(string $filename): array
    {
        return [
            storage_path('app/public/' . self::CDN_DIR . '/' . $filename),
            public_path('storage/' . self::CDN_DIR . '/' . $filename),
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

    private static function writeS3ContentsToCache(string $body, string $filename): ?string
    {
        if ($body === '' || strlen($body) < 50) {
            return null;
        }

        $cacheDir = storage_path('framework/cache/' . self::CDN_DIR);
        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
            return null;
        }

        $cacheFile = $cacheDir . '/' . $filename;
        file_put_contents($cacheFile, $body);

        return is_file($cacheFile) ? $cacheFile : null;
    }

    private static function downloadCdnToCache(string $filename): ?string
    {
        $cacheDir = storage_path('framework/cache/' . self::CDN_DIR);
        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
            return null;
        }

        $cacheFile = $cacheDir . '/' . $filename;
        $url = self::cdnUrl($filename);

        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
            return $cacheFile;
        }

        try {
            $response = Http::timeout(15)->get($url);
            if (! $response->successful()) {
                Log::warning('Brand logo no disponible en CDN', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return is_file($cacheFile) ? $cacheFile : null;
            }

            return self::writeS3ContentsToCache($response->body(), $filename)
                ?? (is_file($cacheFile) ? $cacheFile : null);
        } catch (\Throwable $e) {
            Log::warning('Error descargando brand logo desde CDN', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return is_file($cacheFile) ? $cacheFile : null;
        }
    }
}
