<?php

namespace App\Support\Storage;

class StoragePathSanitizer
{
    /**
     * Sanitiza una ruta relativa segmento a segmento (ej. templates/archivo.xlsx).
     */
    public static function relativePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath, '/'));
        if ($relativePath === '') {
            return '';
        }

        $segments = [];
        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $segments[] = self::pathSegment($segment);
        }

        return implode('/', $segments);
    }

    /**
     * Sanitiza un nombre de archivo (con extensión).
     */
    public static function fileName(string $fileName): string
    {
        return self::pathSegment($fileName);
    }

    /**
     * Elimina espacios unicode, caracteres de control y símbolos inválidos en claves S3/Flysystem.
     */
    public static function pathSegment(string $segment): string
    {
        $segment = trim($segment);
        if ($segment === '') {
            return 'file';
        }

        $extension = '';
        $pos = strrpos($segment, '.');
        if ($pos !== false && $pos > 0) {
            $base = substr($segment, 0, $pos);
            $extension = substr($segment, $pos);
            $extension = preg_replace('/[^a-z0-9.]/', '', strtolower($extension)) ?: '';
        } else {
            $base = $segment;
        }

        $base = preg_replace('/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', '_', $base);
        $base = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/', '', $base);
        $base = preg_replace('/[^A-Za-z0-9._\-]/', '_', $base);
        $base = preg_replace('/_+/', '_', $base);
        $base = rtrim(trim($base), '.');

        if ($base === '') {
            $base = 'file';
        }

        if (strlen($base) > 200) {
            $base = rtrim(substr($base, 0, 200), '._-');
            if ($base === '') {
                $base = 'file';
            }
        }

        return $base . $extension;
    }
}
