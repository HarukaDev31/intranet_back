<?php

namespace App\Support\Register;

/**
 * Catálogo de "cómo nos enteró" (no_como_entero / Nu_Como_Entero_Empresa).
 *
 * Códigos (columna comment en entidad.Nu_Como_Entero_Empresa + extensiones):
 * 1=TikTok, 2=Facebook, 3=Instagram, 4=YouTube,
 * 5=Familiares/Amigos (register legacy / main),
 * 6=LinkedIn (hist) | Otros en register legacy,
 * 7=Google,
 * 8=Otros,
 * 9=Recomendado (register v2; reemplaza UI de Familiares/Amigos).
 *
 * v1 = front main/master. v2 = front org nueva (sin Familiares/Amigos; con Recomendado + Google).
 */
class ComoEnteroCatalog
{
    public const VERSION_V1 = 'v1';
    public const VERSION_V2 = 'v2';

    /** Códigos que requieren texto libre en no_otros_como_entero_empresa */
    public const OTROS_CODES = [6, 8];

    /**
     * Mapa completo para lectura / reportes (soporta ambas versiones).
     *
     * @return array<int, string>
     */
    public static function labels()
    {
        return [
            0 => 'No especificado',
            1 => 'TikTok',
            2 => 'Facebook',
            3 => 'Instagram',
            4 => 'YouTube',
            5 => 'Familiares/Amigos',
            6 => 'Otros',
            7 => 'Google',
            8 => 'Otros',
            9 => 'Recomendado',
        ];
    }

    /**
     * @param  int|string|null  $code
     * @return string|null
     */
    public static function label($code)
    {
        if ($code === null || $code === '') {
            return null;
        }
        $int = (int) $code;
        $map = self::labels();

        return $map[$int] ?? (string) $code;
    }

    /**
     * @param  int|string|null  $code
     * @return bool
     */
    public static function requiresOtrosText($code)
    {
        return in_array((int) $code, self::OTROS_CODES, true);
    }

    /**
     * Opciones del select de registro según versión de catálogo.
     *
     * @param  string  $version
     * @return array<int, array{value:int,label:string}>
     */
    public static function registerOptions($version = self::VERSION_V1)
    {
        if ($version === self::VERSION_V2) {
            return [
                ['value' => 1, 'label' => 'TikTok'],
                ['value' => 2, 'label' => 'Facebook'],
                ['value' => 3, 'label' => 'Instagram'],
                ['value' => 4, 'label' => 'YouTube'],
                ['value' => 9, 'label' => 'Recomendado'],
                ['value' => 7, 'label' => 'Google'],
                ['value' => 8, 'label' => 'Otros'],
            ];
        }

        // v1 — main/master (valores históricos del register)
        return [
            ['value' => 1, 'label' => 'TikTok'],
            ['value' => 2, 'label' => 'Facebook'],
            ['value' => 3, 'label' => 'Instagram'],
            ['value' => 4, 'label' => 'YouTube'],
            ['value' => 5, 'label' => 'Familiares/Amigos'],
            ['value' => 6, 'label' => 'Otros'],
        ];
    }

    /**
     * Códigos aceptados en registro (unión v1 + v2).
     *
     * @return array<int, int>
     */
    public static function acceptedCodes()
    {
        return [1, 2, 3, 4, 5, 6, 7, 8, 9];
    }

    /**
     * Org admin (Probusiness main) → v1; resto → v2.
     *
     * @param  int  $organizacionId
     * @return string
     */
    public static function versionForOrganizacion($organizacionId)
    {
        $admin = defined('App\\Support\\Organizacion\\OrganizacionPortalUrls::ADMIN_ORG')
            ? \App\Support\Organizacion\OrganizacionPortalUrls::ADMIN_ORG
            : 1;

        return ((int) $organizacionId === (int) $admin)
            ? self::VERSION_V1
            : self::VERSION_V2;
    }

    /**
     * Comentario SQL documentando ambos catálogos.
     *
     * @return string
     */
    public static function columnComment()
    {
        return '1=TikTok, 2=Facebook, 3=Instagram, 4=YouTube, '
            . '5=Familiares/Amigos (register v1), '
            . '6=Otros (register v1) / LinkedIn (hist), '
            . '7=Google (register v2), '
            . '8=Otros (register v2), '
            . '9=Recomendado (register v2)';
    }
}
