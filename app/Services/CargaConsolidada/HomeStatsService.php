<?php

namespace App\Services\CargaConsolidada;

use Illuminate\Support\Facades\DB;

/**
 * Indicadores de la home operativa (Almacén China global / socio por org).
 */
class HomeStatsService
{
    /**
     * @param array<int, int> $orgIds
     * @return array<string, mixed>
     */
    public function resumen(array $orgIds, $conDesglosePais)
    {
        $orgIds = array_values(array_unique(array_map('intval', $orgIds)));
        if ($orgIds === []) {
            return $this->vacio($conDesglosePais);
        }

        $cbm = $this->sumarPorPais($orgIds, 'cbm');
        $clientes = $this->sumarPorPais($orgIds, 'clientes');
        $codigos = $this->sumarPorPais($orgIds, 'codigos');
        $contenedores = $this->sumarPorPais($orgIds, 'contenedores');

        return [
            'scope' => $conDesglosePais ? 'global' : 'organizacion',
            'cards' => [
                $this->card('cbm', $cbm, $conDesglosePais),
                $this->card('customers', $clientes, $conDesglosePais),
                $this->card('codes', $codigos, $conDesglosePais),
                $this->card('containers', $contenedores, true),
            ],
        ];
    }

    /**
     * @param array{total: float, by_country: array<int, array{country: string, value: float}>} $agg
     * @return array<string, mixed>
     */
    private function card($key, array $agg, $incluirPais)
    {
        return [
            'key' => $key,
            'value' => $agg['total'],
            'by_country' => $incluirPais ? $agg['by_country'] : [],
        ];
    }

    /**
     * @param array<int, int> $orgIds
     * @return array{total: float, by_country: array<int, array{country: string, value: float}>}
     */
    private function sumarPorPais(array $orgIds, $tipo)
    {
        $query = DB::table('carga_consolidada_contenedor as cont')
            ->leftJoin('pais as pa', 'pa.ID_Pais', '=', 'cont.id_pais')
            ->whereIn('cont.organizacion_id', $orgIds)
            ->where('cont.empresa', '!=', 1)
            ->whereNull('cont.deleted_at');

        if ($tipo === 'cbm') {
            $query->join('contenedor_consolidado_cotizacion_proveedores as p', 'p.id_contenedor', '=', 'cont.id')
                ->join('contenedor_consolidado_cotizacion as cc', 'cc.id', '=', 'p.id_cotizacion')
                ->whereNull('cc.deleted_at')
                ->where('p.estados_proveedor', 'LOADED')
                ->selectRaw('UPPER(TRIM(COALESCE(pa.No_Pais, "SIN PAIS"))) as country')
                ->selectRaw('COALESCE(SUM(COALESCE(p.cbm_total, 0) + COALESCE(p.cbm_imo, 0)), 0) as value');
        } elseif ($tipo === 'clientes') {
            $query->join('contenedor_consolidado_cotizacion as cc', 'cc.id_contenedor', '=', 'cont.id')
                ->whereNull('cc.deleted_at')
                ->whereNotNull('cc.id_cliente')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores as p')
                        ->whereColumn('p.id_cotizacion', 'cc.id')
                        ->where('p.estados_proveedor', 'LOADED');
                })
                ->selectRaw('UPPER(TRIM(COALESCE(pa.No_Pais, "SIN PAIS"))) as country')
                ->selectRaw('COUNT(DISTINCT cc.id_cliente) as value');
        } elseif ($tipo === 'codigos') {
            $query->join('contenedor_consolidado_cotizacion_proveedores as p', 'p.id_contenedor', '=', 'cont.id')
                ->join('contenedor_consolidado_cotizacion as cc', 'cc.id', '=', 'p.id_cotizacion')
                ->whereNull('cc.deleted_at')
                ->whereNotNull('p.code_supplier')
                ->where('p.code_supplier', '!=', '')
                ->selectRaw('UPPER(TRIM(COALESCE(pa.No_Pais, "SIN PAIS"))) as country')
                ->selectRaw('COUNT(p.id) as value');
        } else {
            $query->selectRaw('UPPER(TRIM(COALESCE(pa.No_Pais, "SIN PAIS"))) as country')
                ->selectRaw('COUNT(cont.id) as value');
        }

        $rows = $query->groupBy(DB::raw('UPPER(TRIM(COALESCE(pa.No_Pais, "SIN PAIS")))'))
            ->orderByDesc('value')
            ->get();

        $byCountry = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $value = (float) $row->value;
            $total += $value;
            $byCountry[] = [
                'country' => (string) $row->country,
                'value' => $value,
            ];
        }

        return [
            'total' => $total,
            'by_country' => $byCountry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vacio($conDesglosePais)
    {
        $empty = ['total' => 0.0, 'by_country' => []];

        return [
            'scope' => $conDesglosePais ? 'global' : 'organizacion',
            'cards' => [
                $this->card('cbm', $empty, $conDesglosePais),
                $this->card('customers', $empty, $conDesglosePais),
                $this->card('codes', $empty, $conDesglosePais),
                $this->card('containers', $empty, true),
            ],
        ];
    }
}
