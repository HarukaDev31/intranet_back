<?php

namespace App\Support\SoporteTi;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\UploadedFile;

/**
 * Neutraliza archivos .html/.htm subidos como evidencia: purifica el markup
 * (quita <script>, on-event handlers, etc.) y fuerza que nunca se sirvan/abran
 * como text/html, para que un adjunto no pueda ejecutarse como página en el
 * dominio del bucket/CDN (stored XSS vía evidencia).
 */
class SoporteTiHtmlEvidenceSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function esHtml(UploadedFile $file): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['html', 'htm'], true)) {
            return true;
        }

        $mime = strtolower((string) $file->getClientMimeType());

        return $mime === 'text/html';
    }

    /**
     * Devuelve el contenido saneado (texto plano de markup purificado, nunca ejecutable)
     * y el nombre de archivo con extensión forzada a .txt.
     *
     * @return array{contenido: string, nombre: string}
     */
    public static function sanear(UploadedFile $file): array
    {
        $original = (string) file_get_contents($file->getRealPath());
        $limpio = self::purifier()->purify($original);

        $nombreOriginal = (string) $file->getClientOriginalName();
        $base = pathinfo($nombreOriginal, PATHINFO_FILENAME) ?: 'evidencia';
        $nombre = $base . '.txt';

        return array(
            'contenido' => $limpio,
            'nombre' => $nombre,
        );
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,ul,ol,li,span,div,table,thead,tbody,tr,td,th,h1,h2,h3,h4,h5,h6,a[href],img[src|alt]');
            $config->set('URI.AllowedSchemes', array('http' => true, 'https' => true, 'mailto' => true));
            $config->set('HTML.TargetBlank', true);
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
