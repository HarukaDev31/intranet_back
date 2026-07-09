<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BrandLogoPaths
{
    private const CDN_DIR = 'logo_icons';

    /**
     * Ruta local legible para embed en emails/PDFs. Local primero; si no existe, descarga desde CDN.
     */
    public static function resolve(string $filename): ?string
    {
        $filename = self::sanitizeFilename($filename);

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

            $body = $response->body();
            if ($body === '' || strlen($body) < 50) {
                return is_file($cacheFile) ? $cacheFile : null;
            }

            file_put_contents($cacheFile, $body);

            return $cacheFile;
        } catch (\Throwable $e) {
            Log::warning('Error descargando brand logo desde CDN', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return is_file($cacheFile) ? $cacheFile : null;
        }
    }
}
