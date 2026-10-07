<?php

namespace App\Support\CargaConsolidada;

use App\Models\Organizacion;
use App\Models\Pais;
use App\Models\PaisFlag;
use Illuminate\Support\Str;

/**
 * Formato del documento de la cotización resumen según el país de la organización.
 * Ecuador y el resto (incluida la org 1) usan el formato por defecto sin cambios;
 * Bolivia usa la proforma LCL (USD + Bs con tipo de cambio).
 */
class ResumenFormato
{
    const DEFAULT = 'default';
    const BOLIVIA = 'bolivia';

    /** @var array<int, string> */
    private static $cache = [];

    /**
     * @param mixed $orgId
     * @return string
     */
    public static function deOrganizacion($orgId)
    {
        $orgId = (int) $orgId;
        if ($orgId <= 1) {
            return self::DEFAULT;
        }
        if (isset(self::$cache[$orgId])) {
            return self::$cache[$orgId];
        }

        $formato = self::DEFAULT;
        $org = Organizacion::query()->find($orgId);
        $idPais = $org ? (int) $org->getAttribute('id_pais') : 0;
        if ($idPais > 0) {
            $flag = PaisFlag::query()->where('id_pais', $idPais)->first();
            $iso2 = $flag ? strtolower(trim((string) $flag->getAttribute('iso2'))) : '';
            if ($iso2 === 'bo') {
                $formato = self::BOLIVIA;
            } elseif ($iso2 === '') {
                $pais = Pais::query()->find($idPais);
                $nombre = $pais ? mb_strtolower(trim(Str::ascii((string) $pais->getAttribute('No_Pais')))) : '';
                if ($nombre === 'bolivia') {
                    $formato = self::BOLIVIA;
                }
            }
        }

        return self::$cache[$orgId] = $formato;
    }

    /**
     * @param string $formato
     * @return bool
     */
    public static function esBolivia($formato)
    {
        return $formato === self::BOLIVIA;
    }
}
