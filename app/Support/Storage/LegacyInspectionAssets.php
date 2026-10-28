<?php

namespace App\Support\Storage;

/**
 * Media legacy de inspección (proyecto CodeIgniter probusinees-intranet).
 *
 * Antes: file_path = https://intranet.probusiness.pe/assets/images/{archivo}
 * Ahora: el archivo vive en el bucket bajo assets/imgs/{archivo} (raíz del bucket,
 * sin AWS_UPLOAD_PREFIX) y en BD se guarda la ruta relativa assets/imgs/{archivo}.
 * El back arma la URL pública concatenando OBJECT_STORAGE_CDN_URL + ruta.
 */
final class LegacyInspectionAssets
{
    /** Prefijo (key S3 y ruta relativa en BD) de los archivos legacy reubicados */
    public const PREFIX = 'assets/imgs';

    /** Marcador de las URLs legacy del front CodeIgniter */
    public const LEGACY_MARKER = '/assets/images/';

    private function __construct()
    {
    }

    /** ¿La ruta (relativa o URL) apunta a un archivo legacy ya reubicado en assets/imgs/? */
    public static function isRelocatedPath(?string $path): bool
    {
        $relative = self::relativeFromInput($path);

        return $relative !== null && stripos($relative, self::PREFIX . '/') === 0;
    }

    /**
     * URL CDN pública para una ruta assets/imgs/... (acepta nombre codificado o no).
     * Devuelve null si la ruta no es de assets/imgs/ o no hay CDN configurado.
     */
    public static function cdnUrl(?string $path, ?string $cdnBaseUrl = null): ?string
    {
        if (!self::isRelocatedPath($path)) {
            return null;
        }

        $base = rtrim((string) ($cdnBaseUrl ?? config('object_storage.cdn_base_url', '')), '/');
        if ($base === '') {
            $base = 'https://cdn.probusiness.pe';
        }

        $relative = rawurldecode((string) self::relativeFromInput($path));

        return $base . '/' . StoragePathSanitizer::encodeRelativePathForUrl($relative);
    }

    /**
     * https://intranet.probusiness.pe/assets/images/1765_%E5%BE%AE.jpg → 1765_微.jpg
     * (ruta bajo assets/images/, decodificada). null si no es una URL/ruta legacy.
     */
    public static function legacyRelativeName(?string $filePath): ?string
    {
        if ($filePath === null) {
            return null;
        }

        $filePath = str_replace('\\', '/', trim($filePath));
        $pos = stripos($filePath, self::LEGACY_MARKER);
        if ($pos === false) {
            if (stripos($filePath, ltrim(self::LEGACY_MARKER, '/')) !== 0) {
                return null;
            }
            $rest = substr($filePath, strlen(ltrim(self::LEGACY_MARKER, '/')));
        } else {
            $rest = substr($filePath, $pos + strlen(self::LEGACY_MARKER));
        }

        $rest = rawurldecode(ltrim((string) strtok($rest, '?#'), '/'));

        return $rest === '' ? null : $rest;
    }

    /** Nombre relativo (bajo assets/images/) → key S3 / file_path nuevo */
    public static function relocatedPath(string $legacyRelativeName): string
    {
        return self::PREFIX . '/' . ltrim(str_replace('\\', '/', $legacyRelativeName), '/');
    }

    private static function relativeFromInput(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '';
        }

        $path = ltrim($path, '/');

        return $path === '' ? null : $path;
    }
}
