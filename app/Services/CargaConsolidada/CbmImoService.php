<?php

namespace App\Services\CargaConsolidada;

use Illuminate\Support\Facades\DB;

/**
 * CBM IMO por contenedor (cotizaciones CONFIRMADO). Org 1: volumen completo de las
 * cotizaciones es_imo (sin desglose por proveedor/item). Resto (Socio): calculadora
 * (es_imo + proveedores) más cbm_imo de proveedores resumen.
 */
class CbmImoService
{
    /**
     * @param  array<int, int>  $contenedorIds
     * @param  array<int, int>  $orgByContenedor
     * @return array<int, float>
     */
    public function forContenedores(array $contenedorIds, array $orgByContenedor)
    {
        if ($contenedorIds === []) {
            return [];
        }

        $imoCalculadora = DB::table('contenedor_consolidado_cotizacion as cci')
            ->join('calculadora_importacion as ci', function ($join) {
                $join->on('ci.id_cotizacion', '=', 'cci.id')
                    ->where('ci.es_imo', '=', 1);
            })
            ->join('calculadora_importacion_proveedores as cip', 'ci.id', '=', 'cip.id_calculadora_importacion')
            ->whereIn('cci.id_contenedor', $contenedorIds)
            ->whereNull('cci.deleted_at')
            ->where('cci.estado_cotizador', 'CONFIRMADO')
            ->groupBy('cci.id_contenedor')
            ->selectRaw('cci.id_contenedor, COALESCE(SUM(cip.cbm), 0) as cbm_imo')
            ->pluck('cbm_imo', 'id_contenedor');

        $imoProveedores = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
            ->join('contenedor_consolidado_cotizacion as cc', 'cc.id', '=', 'cccp.id_cotizacion')
            ->whereIn('cccp.id_contenedor', $contenedorIds)
            ->whereNull('cc.deleted_at')
            ->where('cc.estado_cotizador', 'CONFIRMADO')
            ->groupBy('cccp.id_contenedor')
            ->selectRaw('cccp.id_contenedor, COALESCE(SUM(cccp.cbm_imo), 0) as cbm_imo')
            ->pluck('cbm_imo', 'id_contenedor');

        $imoCotizacion = DB::table('contenedor_consolidado_cotizacion')
            ->whereIn('id_contenedor', $contenedorIds)
            ->whereNull('deleted_at')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where('es_imo', 1)
            ->groupBy('id_contenedor')
            ->selectRaw('id_contenedor, COALESCE(SUM(volumen), 0) as cbm_imo')
            ->pluck('cbm_imo', 'id_contenedor');

        $result = [];
        foreach ($contenedorIds as $id) {
            $orgId = (int) ($orgByContenedor[$id] ?? 0);
            $calc = (float) ($imoCalculadora[$id] ?? 0);
            $prov = (float) ($imoProveedores[$id] ?? 0);
            $result[$id] = $orgId === 1 ? (float) ($imoCotizacion[$id] ?? 0) : ($calc + $prov);
        }

        return $result;
    }
}
