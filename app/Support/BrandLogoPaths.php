<?php

namespace App\Support;

final class BrandLogoPaths
{
    /**
     * Ruta legible del logo en disco (emails, PDFs). Prueba storage/app/public y public/storage.
     */
    public static function resolve(string $filename): ?string
    {
        $filename = ltrim(str_replace(['\\', '..'], ['/', ''], $filename), '/');

        foreach (self::candidates($filename) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function header(): ?string
    {
        return self::resolve('logo_header.png');
    }

    public static function footer(): ?string
    {
        return self::resolve('logo_footer.png');
    }

    /**
     * @return list<string>
     */
    private static function candidates(string $filename): array
    {
        return [
            storage_path('app/public/logo_icons/' . $filename),
            public_path('storage/logo_icons/' . $filename),
        ];
    }
}
