<?php

namespace App\Support\WhatsApp;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Support\Storage\StoragePathSanitizer;
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
        $result = self::uploadLocalFileToStorage($fullPath, $storageRelative);
        if ($result === null) {
            return null;
        }

        $url = $result['url'] ?? null;
        if ($url !== null && $url !== '') {
            return $url;
        }

        $path = (string) ($result['path'] ?? '');

        return $path !== '' ? $path : null;
    }

    /**
     * Sube un archivo local y devuelve la clave real en storage (post-sanitización) y URL si existe.
     *
     * @return array{path: string, url: ?string}|null
     */
    public static function uploadLocalFileToStorage(string $fullPath, string $storageRelative): ?array
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
            $storedPath = $storage->putContents($relative, $contents);
            $url = self::resolveUrlForStoredPath($storage, $storedPath);

            return [
                'path' => $storedPath,
                'url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::warning('CoordinacionMediaLink: fallo subida S3, probando fallback', [
                'path' => $fullPath,
                'relative' => $storageRelative,
                'error' => $e->getMessage(),
            ]);

            $fallbackUrl = self::uploadToPublicDisk($contents, $storageRelative)
                ?? self::fallbackPublicAssetUrl($fullPath);
            if ($fallbackUrl === null || $fallbackUrl === '') {
                return null;
            }

            $storedPath = StoragePathSanitizer::relativePath(ltrim($storageRelative, '/'));

            return [
                'path' => $storedPath !== '' ? $storedPath : ltrim($storageRelative, '/'),
                'url' => $fallbackUrl,
            ];
        }
    }

    /**
     * @param  ObjectStorageConnectorInterface  $storage
     * @param  string  $storedPath
     * @return string|null
     */
    private static function resolveUrlForStoredPath($storage, string $storedPath): ?string
    {
        $url = $storage->url($storedPath);
        if ($url !== null && $url !== '') {
            return $url;
        }

        if (method_exists($storage, 'metaPresignedUrl')) {
            try {
                return $storage->metaPresignedUrl($storedPath);
            } catch (\Throwable $e) {
                Log::debug('CoordinacionMediaLink: metaPresignedUrl tras subida', [
                    'path' => $storedPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
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
            $header = self::ensureHeaderStoredOnObjectStorage($header, $path);
            if ($header === null) {
                return null;
            }

            $storagePath = (string) ($header['path'] ?? '');
            $link = self::urlForMetaSend($storagePath);
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
     * Clave S3 + mime para persistir plantilla con encabezado multimedia en wa_inbox_messages.
     *
     * @param  array<string, mixed>|null  $header
     * @return array{media_url: string|null, media_mime: string|null}
     */
    public static function templateHeaderMediaForDatabase($header)
    {
        $mediaMime = null;
        $mediaUrl = null;

        if (!is_array($header) || $header === []) {
            return ['media_url' => null, 'media_mime' => null];
        }

        $path = (string) ($header['path'] ?? '');
        if ($path !== '' && self::resolveStoragePath($path) === null) {
            $stored = self::ensureHeaderStoredOnObjectStorage($header, $path);
            if ($stored !== null) {
                $header = $stored;
            }
        }

        if (!empty($header['path'])) {
            $rawPath = (string) $header['path'];
            $mediaUrl = self::storagePathForDatabase($rawPath)
                ?: self::resolveStoragePath($rawPath);
        } elseif (!empty($header['link'])) {
            $rawLink = (string) $header['link'];
            $mediaUrl = self::storagePathForDatabase($rawLink)
                ?: self::resolveStoragePath($rawLink);
        }

        if (isset($header['type'])) {
            $ht = strtolower((string) $header['type']);
            if ($ht === 'image') {
                $mediaMime = 'image/jpeg';
            } elseif ($ht === 'video') {
                $mediaMime = 'video/mp4';
            } elseif ($ht === 'document') {
                $mime = isset($header['mimeType']) ? trim((string) $header['mimeType']) : '';
                $mediaMime = $mime !== '' ? $mime : 'application/pdf';
            }
        }

        if ($mediaUrl !== null && $mediaUrl !== '') {
            $stored = self::storagePathForDatabase($mediaUrl);
            if ($stored !== null && $stored !== '') {
                $mediaUrl = $stored;
            }
        }

        return [
            'media_url' => $mediaUrl !== null && $mediaUrl !== '' ? $mediaUrl : null,
            'media_mime' => $mediaMime,
        ];
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
                $sanitized = StoragePathSanitizer::relativePath($resolved);
                if ($sanitized !== '' && $sanitized !== $resolved && $storage->exists($sanitized)) {
                    $resolved = $sanitized;
                } else {
                    return self::stringOrNullUrl($pathOrUrl);
                }
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

        $name = StoragePathSanitizer::fileName((string) $originalFilename);
        if ($name === '' || $name === 'file') {
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

        $name = StoragePathSanitizer::fileName((string) $originalFilename);
        if ($name === '' || $name === 'file') {
            $name = 'media.bin';
        }

        return 'whatsapp-meta/inbox/conversations/' . $conversationId . '/inbound/' . $kind . '/'
            . Str::uuid() . '_' . $name;
    }

    /**
     * Clave S3 para media enviada en ventana abierta (Copiloto / ventas).
     *
     * @param  int  $conversationId
     * @param  string  $kind
     * @param  string  $originalFilename
     * @return string
     */
    public static function buildCopilotoConversationStorageKey($conversationId, $kind, $originalFilename)
    {
        $conversationId = (int) $conversationId;
        $kind = strtolower(preg_replace('/[^a-z]/', '', (string) $kind));
        if ($kind === '') {
            $kind = 'document';
        }

        $name = StoragePathSanitizer::fileName((string) $originalFilename);
        if ($name === '' || $name === 'file') {
            $name = 'media.bin';
        }

        return 'whatsapp-meta/copiloto/conversations/' . $conversationId . '/' . $kind . '/'
            . Str::uuid() . '_' . $name;
    }

    /**
     * Clave S3 para media recibida del cliente (Copiloto / ventas).
     *
     * @param  int  $conversationId
     * @param  string  $kind
     * @param  string  $originalFilename
     * @return string
     */
    public static function buildCopilotoInboundStorageKey($conversationId, $kind, $originalFilename)
    {
        $conversationId = (int) $conversationId;
        $kind = strtolower(preg_replace('/[^a-z]/', '', (string) $kind));
        if ($kind === '') {
            $kind = 'document';
        }

        $name = StoragePathSanitizer::fileName((string) $originalFilename);
        if ($name === '' || $name === 'file') {
            $name = 'media.bin';
        }

        return 'whatsapp-meta/copiloto/conversations/' . $conversationId . '/inbound/' . $kind . '/'
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

    /**
     * Archivos locales (rotulado PDF, etc.) → clave S3 en header.path para el inbox.
     *
     * @param  array<string, mixed>  $header
     * @param  string  $path
     * @return array<string, mixed>|null
     */
    private static function ensureHeaderStoredOnObjectStorage(array $header, $path)
    {
        $resolved = self::resolveStoragePath($path);
        if ($resolved !== null) {
            try {
                $storage = app(ObjectStorageConnectorInterface::class);
                if ($storage->exists($resolved)) {
                    $header['path'] = $resolved;

                    return $header;
                }
            } catch (\Throwable $e) {
                Log::debug('CoordinacionMediaLink: ensureHeader exists check', [
                    'path' => $resolved,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $localPath = $path;
        if (!self::isAbsoluteLocalFile($localPath)) {
            $relative = ltrim(str_replace('\\', '/', $localPath), '/');
            $candidates = [
                storage_path('app/' . $relative),
                storage_path($relative),
                public_path($relative),
            ];
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $localPath = $candidate;
                    break;
                }
            }
        }

        if (!is_file($localPath)) {
            return null;
        }

        $storageKey = self::buildTempKey(
            $localPath,
            isset($header['filename']) ? (string) $header['filename'] : null
        );
        $uploaded = self::uploadLocalFileToStorage($localPath, $storageKey);
        if ($uploaded === null || ($uploaded['path'] ?? '') === '') {
            return null;
        }

        $header['path'] = ltrim((string) $uploaded['path'], '/');

        return $header;
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
