<?php

namespace App\Support\Phone;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prefijo telefónico desde pais_flags (join por id_pais del contenedor).
 *
 * Guardado: si el número no trae código, se antepone el del consolidado.
 * Comparación: dígitos internacionales + nacionales (prefijos de la tabla).
 */
class CountryPhoneHelper
{
    /** @var array<int, string>|null */
    private static $codesCache = null;

    public static function resetCache()
    {
        self::$codesCache = null;
    }

    /**
     * Códigos en DB, el más largo primero (593 antes que 51).
     *
     * @return array<int, string>
     */
    public static function callingCodes()
    {
        if (self::$codesCache !== null) {
            return self::$codesCache;
        }

        $codes = [];
        if (Schema::hasTable('pais_flags') && Schema::hasColumn('pais_flags', 'phone_code')) {
            $fromDb = DB::table('pais_flags')
                ->whereNotNull('phone_code')
                ->where('phone_code', '!=', '')
                ->distinct()
                ->pluck('phone_code');
            foreach ($fromDb as $code) {
                $digits = preg_replace('/[^0-9]/', '', (string) $code);
                if ($digits !== '') {
                    $codes[] = $digits;
                }
            }
        }

        $codes = array_values(array_unique($codes));
        usort($codes, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        self::$codesCache = $codes;

        return $codes;
    }

    /**
     * @param int|null $idPais
     * @return string|null
     */
    public static function codeForPaisId($idPais)
    {
        $idPais = (int) $idPais;
        if ($idPais <= 0 || !Schema::hasTable('pais_flags') || !Schema::hasColumn('pais_flags', 'phone_code')) {
            return null;
        }

        $code = DB::table('pais_flags')->where('id_pais', $idPais)->value('phone_code');
        $digits = preg_replace('/[^0-9]/', '', (string) $code);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Join contenedor.id_pais → pais_flags.phone_code.
     *
     * @param object|null $contenedor
     * @return string|null
     */
    public static function codeForContenedor($contenedor)
    {
        if (!$contenedor) {
            return null;
        }

        if (!empty($contenedor->phone_code)) {
            $digits = preg_replace('/[^0-9]/', '', (string) $contenedor->phone_code);
            return $digits !== '' ? $digits : null;
        }

        $id = isset($contenedor->id) ? (int) $contenedor->id : 0;
        if ($id <= 0 && method_exists($contenedor, 'getKey')) {
            $id = (int) $contenedor->getKey();
        }
        if ($id <= 0 || !Schema::hasTable('pais_flags') || !Schema::hasColumn('pais_flags', 'phone_code')) {
            return self::codeForPaisId($contenedor->id_pais ?? (method_exists($contenedor, 'getAttribute') ? $contenedor->getAttribute('id_pais') : null));
        }

        $code = DB::table('carga_consolidada_contenedor as c')
            ->leftJoin('pais_flags as pf', 'pf.id_pais', '=', 'c.id_pais')
            ->where('c.id', $id)
            ->value('pf.phone_code');
        $digits = preg_replace('/[^0-9]/', '', (string) $code);

        return $digits !== '' ? $digits : null;
    }

    /**
     * @param string|null $phone
     * @return string
     */
    public static function digits($phone)
    {
        return preg_replace('/[^0-9]/', '', (string) $phone) ?: '';
    }

    /**
     * @param string $digits
     * @return string
     */
    public static function nationalNumber($digits)
    {
        $digits = self::digits($digits);
        if ($digits === '') {
            return '';
        }

        if (preg_match('/^0(\d{7,10})$/', $digits, $m)) {
            return $m[1];
        }

        foreach (self::callingCodes() as $code) {
            if ($code === '' || strpos($digits, $code) !== 0) {
                continue;
            }
            $resto = substr($digits, strlen($code));
            $len = strlen($resto);
            if ($len < 7 || $len > 10) {
                continue;
            }
            if (strlen($code) === 1 && $len !== 10) {
                continue;
            }
            return $resto;
        }

        return $digits;
    }

    /**
     * @param string|null $phone
     * @param string|null $callingCode
     * @return string
     */
    public static function ensureCountryCode($phone, $callingCode)
    {
        $digits = self::digits($phone);
        if ($digits === '') {
            return '';
        }

        $code = preg_replace('/[^0-9]/', '', (string) $callingCode);
        $national = self::nationalNumber($digits);

        if ($national !== $digits) {
            return $digits;
        }

        if ($code === '') {
            return $digits;
        }

        if (strpos($digits, $code) === 0 && strlen($digits) > strlen($code) + 6) {
            return $digits;
        }

        return $code . $national;
    }

    /**
     * @param string|null $phone
     * @return array<int, string>
     */
    public static function searchVariants($phone)
    {
        $digits = self::digits($phone);
        if ($digits === '' || strlen($digits) < 7) {
            return [];
        }

        $variantes = [$digits];
        $national = self::nationalNumber($digits);
        if ($national !== '' && $national !== $digits && strlen($national) >= 7) {
            $variantes[] = $national;
        }

        return array_values(array_unique($variantes));
    }
}
