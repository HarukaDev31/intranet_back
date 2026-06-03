<?php

namespace App\Support\WhatsApp;

use App\Contracts\ObjectStorageConnectorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * URLs públicas (S3 firmadas o storage) para encabezados DOCUMENT/IMAGE/VIDEO en plantillas Meta.
 */
class CoordinacionMediaLink
{
    public const META_TEMP_PREFIX = 'temp/whatsapp-meta';

    /**
     * Resuelve ruta local, relativa en storage o URL existente a HTTPS usable por Meta Graph API.
     */
    public static function resolveForMetaHeader(string $path, ?string $filename = null): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        try {
            $storage = app(ObjectStorageConnectorInterface::class);

            if (self::isAbsoluteLocalFile($path)) {
                $url = self::uploadLocalFile($path, self::buildTempKey($path, $filename));

                return $url ?? self::fallbackPublicAssetUrl($path);
            }

            $relative = $storage->normalizeRelativePath($path);
            if ($relative !== null && $storage->exists($relative)) {
                return $storage->url($relative);
            }

            if ($relative !== null) {
                $publicCandidate = public_path($relative);
                if (is_file($publicCandidate)) {
                    $url = self::uploadLocalFile($publicCandidate, self::buildTempKey($publicCandidate, $filename));

                    return $url ?? self::fallbackPublicAssetUrl($publicCandidate);
                }
            }

            $publicPath = public_path(ltrim(str_replace('\\', '/', $path), '/'));
            if (is_file($publicPath)) {
                $url = self::uploadLocalFile($publicPath, self::buildTempKey($publicPath, $filename));

                return $url ?? self::fallbackPublicAssetUrl($publicPath);
            }
        } catch (\Throwable $e) {
            Log::warning('CoordinacionMediaLink: no se pudo resolver media para Meta', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return self::fallbackPublicAssetUrl($path);
    }

    public static function uploadLocalFile(string $fullPath, string $storageRelative): ?string
    {
        if (!is_file($fullPath)) {
            return null;
        }

        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            return null;
        }

        try {
            $storage = app(ObjectStorageConnectorInterface::class);
            $relative = ltrim($storageRelative, '/');
            $storage->putContents($relative, $contents);

            return $storage->url($relative);
        } catch (\Throwable $e) {
            Log::warning('CoordinacionMediaLink: fallo subida S3, probando fallback', [
                'path' => $fullPath,
                'relative' => $storageRelative,
                'error' => $e->getMessage(),
            ]);

            return self::uploadToPublicDisk($contents, $storageRelative)
                ?? self::fallbackPublicAssetUrl($fullPath);
        }
    }

    /**
     * @param  array<string, mixed>|null  $header
     * @return array<string, mixed>|null  Header con link HTTPS o null si no hay media válida
     */
    public static function prepareHeader(?array $header): ?array
    {
        if (!is_array($header) || empty($header['type'])) {
            return $header;
        }

        $path = (string) ($header['path'] ?? '');
        if ($path !== '') {
            $link = self::urlForMetaSend($path);
            if ($link === null) {
                return null;
            }
            $header['link'] = $link;

            return $header;
        }

        if (!empty($header['link']) && filter_var($header['link'], FILTER_VALIDATE_URL)) {
            return $header;
        }

        return $header;
    }

    /**
     * URL para el chat / intranet (presigned S3 por defecto; evita CDN 403 en templates privados).
     *
     * @param  string|null  $pathOrUrl  Clave relativa S3 o URL guardada en BD
     * @return string|null
     */
    public static function urlForDisplay($pathOrUrl)
    {
        $resolved = self::resolveStoragePath($pathOrUrl);
        if ($resolved === null) {
            return self::stringOrNullUrl($pathOrUrl);
        }

        try {
            $storage = app(ObjectStorageConnectorInterface::class);
            if (!$storage->exists($resolved)) {
                return self::stringOrNullUrl($pathOrUrl);
            }

            if (method_exists($storage, 'metaPresignedUrl') && self::shouldUsePresignedForDisplay($resolved)) {
                try {
                    return $storage->metaPresignedUrl($resolved);
                } catch (\Throwable $e) {
                    Log::debug('CoordinacionMediaLink: urlForDisplay presigned fallback CDN', [
                        'path' => $resolved,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $storage->url($resolved);
        } catch (\Throwable $e) {
            Log::debug('CoordinacionMediaLink: urlForDisplay', [
                'path' => $resolved,
                'error' => $e->getMessage(),
            ]);
        }

        return self::stringOrNullUrl($pathOrUrl);
    }

    /**
     * Clave S3 para media enviada en ventana abierta (chat manual).
     *
     * @param  int  $conversationId
     * @param  string  $kind  image|video|document|audio
     * @param  string  $originalFilename
     * @return string
     */
    public static function buildInboxConversationStorageKey($conversationId, $kind, $originalFilename)
    {
        $conversationId = (int) $conversationId;
        $kind = strtolower(preg_replace('/[^a-z]/', '', (string) $kind));
        if ($kind === '') {
            $kind = 'document';
        }

        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $originalFilename);
        if ($name === '') {
            $name = 'media.bin';
        }

        return 'whatsapp-meta/inbox/conversations/' . $conversationId . '/' . $kind . '/'
            . Str::uuid() . '_' . $name;
    }

    /**
     * Clave S3 para media recibida del cliente (webhook Meta).
     *
     * @param  int  $conversationId
     * @param  string  $kind
     * @param  string  $originalFilename
     * @return string
     */
    public static function buildInboxInboundStorageKey($conversationId, $kind, $originalFilename)
    {
        $conversationId = (int) $conversationId;
        $kind = strtolower(preg_replace('/[^a-z]/', '', (string) $kind));
        if ($kind === '') {
            $kind = 'document';
        }

        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $originalFilename);
        if ($name === '') {
            $name = 'media.bin';
        }

        return 'whatsapp-meta/inbox/conversations/' . $conversationId . '/inbound/' . $kind . '/'
            . Str::uuid() . '_' . $name;
    }

    /**
     * @param  string  $url
     * @return bool
     */
    private static function isPresignedOrDirectObjectUrl($url)
    {
        if (stripos($url, 'X-Amz-Signature=') !== false || stripos($url, 'X-Amz-Algorithm=') !== false) {
            return true;
        }

        return stripos($url, '.amazonaws.com') !== false;
    }

    /**
     * @param  string  $relativePath
     * @return bool
     */
    private static function shouldUsePresignedForDisplay($relativePath)
    {
        if (config('object_storage.inbox_display_use_presigned', true)) {
            return true;
        }

        return strpos($relativePath, 'templates/') === 0;
    }

    /**
     * URL HTTPS para envío a Meta (presigned S3 si hace falta; si no, CDN/url pública).
     *
     * @param  string|null  $pathOrUrl
     * @return string|null
     */
    public static function urlForMetaSend($pathOrUrl)
    {
        if ($pathOrUrl === null) {
            return null;
        }

        $pathOrUrl = trim((string) $pathOrUrl);
        if ($pathOrUrl === '') {
            return null;
        }

        $directUrl = self::stringOrNullUrl($pathOrUrl);
        if ($directUrl !== null) {
            return $directUrl;
        }

        if (self::isAbsoluteLocalFile($pathOrUrl)) {
            return self::resolveForMetaHeader($pathOrUrl);
        }

        $resolved = self::resolveStoragePath($pathOrUrl);
        if ($resolved === null) {
            return self::resolveForMetaHeader($pathOrUrl);
        }

        try {
            $storage = app(ObjectStorageConnectorInterface::class);
            if (!$storage->exists($resolved)) {
                return self::resolveForMetaHeader($resolved);
            }

            if (method_exists($storage, 'metaPresignedUrl')) {
                try {
                    return $storage->metaPresignedUrl($resolved);
                } catch (\Throwable $e) {
                    Log::debug('CoordinacionMediaLink: metaPresignedUrl fallback a CDN', [
                        'path' => $resolved,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $cdn = $storage->url($resolved);
            if ($cdn !== null && $cdn !== '') {
                return $cdn;
            }

            return self::resolveForMetaHeader($resolved);
        } catch (\Throwable $e) {
            Log::warning('CoordinacionMediaLink: urlForMetaSend', [
                'path' => $resolved,
                'error' => $e->getMessage(),
            ]);
        }

        return self::resolveForMetaHeader((string) $pathOrUrl);
    }

    /**
     * @param  string|null  $pathOrUrl
     * @return string|null  Ruta relativa en object storage
     */
    public static function resolveStoragePath($pathOrUrl)
    {
        if ($pathOrUrl === null) {
            return null;
        }

        $pathOrUrl = trim((string) $pathOrUrl);
        if ($pathOrUrl === '') {
            return null;
        }

        if (!filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            if (self::isAbsoluteLocalFile($pathOrUrl)) {
                return null;
            }

            return ltrim(str_replace('\\', '/', $pathOrUrl), '/');
        }

        $parsedPath = parse_url($pathOrUrl, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $decoded = rawurldecode(ltrim($parsedPath, '/'));
            if ($decoded !== '') {
                return ltrim(str_replace('\\', '/', $decoded), '/');
            }
        }

        try {
            $storage = app(ObjectStorageConnectorInterface::class);
            $relative = $storage->normalizeRelativePath($pathOrUrl);

            return $relative !== null && $relative !== '' ? $relative : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Solo ruta relativa en object storage (nunca URL firmada ni CDN).
     *
     * @param  string|null  $pathOrUrl
     * @return string|null
     */
    public static function storagePathForDatabase($pathOrUrl)
    {
        $resolved = self::resolveStoragePath($pathOrUrl);
        if ($resolved !== null && $resolved !== '') {
            return $resolved;
        }

        $trimmed = trim((string) $pathOrUrl);

        return $trimmed !== '' && !filter_var($trimmed, FILTER_VALIDATE_URL) ? ltrim(str_replace('\\', '/', $trimmed), '/') : null;
    }

    /**
     * @param  string|null  $value
     * @return string|null
     */
    private static function stringOrNullUrl($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private static function uploadToPublicDisk(string $contents, string $storageRelative): ?string
    {
        try {
            $relative = ltrim($storageRelative, '/');
            if (!Storage::disk('public')->put($relative, $contents)) {
                return null;
            }

            return Storage::disk('public')->url($relative);
        } catch (\Throwable $e) {
            Log::debug('CoordinacionMediaLink: fallback public disk falló', [
                'relative' => $storageRelative,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function fallbackPublicAssetUrl(string $fullPath): ?string
    {
        if (!config('meta_whatsapp.fallback_public_asset_url', true)) {
            return null;
        }

        if (!is_file($fullPath)) {
            return null;
        }

        $publicRoot = realpath(public_path());
        $realPath = realpath($fullPath);
        if ($publicRoot === false || $realPath === false) {
            return null;
        }

        $publicRoot = str_replace('\\', '/', $publicRoot);
        $realPath = str_replace('\\', '/', $realPath);
        if (strpos($realPath, $publicRoot) !== 0) {
            return null;
        }

        $relative = ltrim(substr($realPath, strlen($publicRoot)), '/');
        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '') {
            return null;
        }

        $url = $baseUrl . '/' . $relative;
        if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?/#i', $url)) {
            Log::warning('CoordinacionMediaLink: URL pública es localhost; Meta no podrá descargarla sin túnel (ngrok) o permisos S3 PutObject', [
                'url' => $url,
            ]);
        } else {
            Log::info('CoordinacionMediaLink: usando URL pública como fallback', ['url' => $url]);
        }

        return $url;
    }

    private static function buildTempKey(string $sourcePath, ?string $filename): string
    {
        $name = $filename ?? basename(str_replace('\\', '/', $sourcePath));
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: 'media.bin';
        $date = date('Y/m/d');

        return self::META_TEMP_PREFIX . '/' . $date . '/' . Str::uuid()->toString() . '_' . $name;
    }

    private static function isAbsoluteLocalFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':';
    }
}
