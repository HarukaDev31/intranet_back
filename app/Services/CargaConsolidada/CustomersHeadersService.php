<?php

namespace App\Services\CargaConsolidada;

use App\Support\CargaConsolidada\ClientesVisibility;
use Illuminate\Support\Facades\DB;

/**
 * KPIs de la vista Customers (CBM warehouse, clientes, códigos, NC).
 * Siempre se limitan a las organizaciones recibidas (nunca desde el request).
 */
class CustomersHeadersService
{
    private $tableCotizacion = 'contenedor_consolidado_cotizacion';
    private $tableProveedor = 'contenedor_consolidado_cotizacion_proveedores';
    private $tableContenedor = 'carga_consolidada_contenedor';
    private $tablePais = 'pais';

    /**
     * @param array<int, int> $orgIds
     * @return array<string, array<string, string>>
     */
    public function build(array $orgIds, $search = '', $idPais = null, $estadoChina = 'todos', $fechaInicio = null, $fechaFin = null)
    {
        $orgIds = array_values(array_unique(array_map('intval', $orgIds)));
        if ($orgIds === []) {
            return $this->empty(true);
        }

        $statsQuery = $this->scopedCustomersQuery($orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin, true);
        $stats = $statsQuery
            ->selectRaw('
                COALESCE(SUM(' . $this->sqlCbmFull('PR') . '), 0) as cbm_warehouse,
                COUNT(DISTINCT CC.id) as total_customers,
                COUNT(PR.id) as total_suppliers_code,
                SUM(CASE WHEN PR.estados_proveedor = ? THEN 1 ELSE 0 END) as total_nc
            ', ['NC'])
            ->first();

        $vendidoRows = $this->cbmByCountry($orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin, 'vendido');
        $warehouseRows = $this->cbmByCountry($orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin, 'warehouse');

        return $this->format(
            $stats,
            $this->sumCountryValues($vendidoRows),
            $vendidoRows,
            $warehouseRows,
            true
        );
    }

    /**
     * KPIs del listado Cotización Resumen (org del usuario, COTIZADO + CONFIRMADO).
     *
     * @param array<int, int> $orgIds
     * @return array<string, array<string, string>>
     */
    public function buildForResumen(array $orgIds, $search = '', $estadoChina = 'todos')
    {
        $orgIds = array_values(array_unique(array_map('intval', $orgIds)));
        if ($orgIds === []) {
            return $this->empty(true, true);
        }

        $statsQuery = $this->scopedResumenQuery($orgIds, $search, $estadoChina, true);
        $stats = $statsQuery
            ->selectRaw('
                COALESCE(SUM(PR.cbm_total_china), 0) as cbm_warehouse,
                COUNT(DISTINCT CC.id) as total_customers,
                COUNT(PR.id) as total_suppliers_code,
                SUM(CASE WHEN PR.estados_proveedor = ? THEN 1 ELSE 0 END) as total_nc
            ', ['NC'])
            ->first();

        $volumeRows = $this->scopedResumenQuery($orgIds, $search, $estadoChina, true)
            ->selectRaw('COALESCE(P.No_Pais, "Sin país") as country')
            ->selectRaw('COALESCE(SUM(CASE WHEN CC.estado_resumen = \'CONFIRMADO\' THEN ' . $this->sqlCbmFull('PR') . ' ELSE 0 END), 0) as vendido')
            ->selectRaw('COALESCE(SUM(CASE WHEN CC.estado_resumen IS NULL OR CC.estado_resumen != \'CONFIRMADO\' THEN ' . $this->sqlCbmFull('PR') . ' ELSE 0 END), 0) as pendiente')
            ->groupBy(DB::raw('COALESCE(P.No_Pais, "Sin país")'))
            ->orderBy('country')
            ->get();

        $vendidoRows = [];
        $pendienteRows = [];
        $cbmVendido = 0.0;
        $cbmPendiente = 0.0;
        foreach ($volumeRows as $row) {
            $country = trim((string) $row->country);
            if ($country === '') {
                $country = 'Sin país';
            }
            $vendido = (float) $row->vendido;
            $pendiente = (float) $row->pendiente;
            $cbmVendido += $vendido;
            $cbmPendiente += $pendiente;
            if ($vendido != 0.0) {
                $vendidoRows[] = [
                    'country' => $country,
                    'value' => number_format($vendido, 3, '.', ''),
                ];
            }
            if ($pendiente != 0.0) {
                $pendienteRows[] = [
                    'country' => $country,
                    'value' => number_format($pendiente, 3, '.', ''),
                ];
            }
        }

        $warehouseRows = $this->scopedResumenQuery($orgIds, $search, $estadoChina, true)
            ->selectRaw('COALESCE(P.No_Pais, "Sin país") as country')
            ->selectRaw('COALESCE(SUM(PR.cbm_total_china), 0) as value')
            ->groupBy(DB::raw('COALESCE(P.No_Pais, "Sin país")'))
            ->orderBy('country')
            ->get();

        return $this->format(
            $stats,
            $cbmVendido,
            $vendidoRows,
            $this->mapCountryRows($warehouseRows),
            true,
            $cbmPendiente,
            $pendienteRows,
            true
        );
    }

    /**
     * KPIs de un consolidado para el rol almacén (vista Customers / Por embarcar).
     *
     * @param int $idContenedor
     * @return array<string, array<string, string>>
     */
    public function buildForContenedor($idContenedor)
    {
        $idContenedor = (int) $idContenedor;
        if ($idContenedor <= 0) {
            return $this->empty();
        }

        $statsQuery = DB::table($this->tableCotizacion . ' as CC')
            ->leftJoin($this->tableProveedor . ' as PR', 'PR.id_cotizacion', '=', 'CC.id')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNull('CC.deleted_at')
            ->whereNull('CC.id_cliente_importacion');
        ClientesVisibility::applyConfirmadoParaBd($statsQuery, 'CC');
        $stats = $statsQuery
            ->selectRaw('
                COALESCE(SUM(' . $this->sqlCbmFull('PR') . '), 0) as cbm_warehouse,
                COUNT(DISTINCT CC.id) as total_customers,
                COUNT(PR.id) as total_suppliers_code,
                SUM(CASE WHEN PR.estados_proveedor = ? THEN 1 ELSE 0 END) as total_nc
            ', ['NC'])
            ->first();

        $cbmPaisQuery = DB::table($this->tableCotizacion . ' as CC')
            ->leftJoin($this->tableProveedor . ' as PR', 'PR.id_cotizacion', '=', 'CC.id')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNull('CC.deleted_at')
            ->whereNull('CC.id_cliente_importacion');
        ClientesVisibility::applyConfirmadoParaBd($cbmPaisQuery, 'CC');
        $cbmPais = (float) $cbmPaisQuery->selectRaw('COALESCE(SUM(' . $this->sqlCbmFull('PR') . '), 0) as total')->value('total');

        $headers = [
            'cbm_pais' => [
                'value' => number_format($cbmPais, 3, '.', ''),
                'label' => 'CBM',
                'icon' => 'fluent:box-32-filled',
            ],
        ] + $this->format($stats);

        return $this->insertCbmDateHeaders($headers, $idContenedor);
    }

    /**
     * @param object|null $stats
     * @param float $cbmVendido
     * @param array<int, array<string, string>> $vendidoByCountry
     * @param array<int, array<string, string>> $warehouseByCountry
     * @param bool $includeVendido
     * @param float $cbmPendiente
     * @param array<int, array<string, string>> $pendienteByCountry
     * @param bool $includePendiente
     * @return array<string, array<string, mixed>>
     */
    private function format(
        $stats,
        $cbmVendido = 0,
        array $vendidoByCountry = [],
        array $warehouseByCountry = [],
        $includeVendido = false,
        $cbmPendiente = 0,
        array $pendienteByCountry = [],
        $includePendiente = false
    ) {
        if (!$stats) {
            return $this->empty($includeVendido, $includePendiente);
        }

        $headers = [];
        if ($includeVendido) {
            $headers['cbm_vendido'] = [
                'value' => number_format((float) $cbmVendido, 3, '.', ''),
                'label' => 'CBM Vendido',
                'icon' => 'fluent:box-32-filled',
                'by_country' => $vendidoByCountry,
            ];
        }
        if ($includePendiente) {
            $headers['cbm_pendiente'] = [
                'value' => number_format((float) $cbmPendiente, 3, '.', ''),
                'label' => 'CBM Pendiente',
                'icon' => 'heroicons:clock',
                'by_country' => $pendienteByCountry,
            ];
        }

        return $headers + [
            'cbm_warehouse' => [
                'value' => number_format((float) ($stats->cbm_warehouse ?? 0), 3, '.', ''),
                'label' => 'CBM Warehouse',
                'icon' => 'i-heroicons-cube',
                'by_country' => $warehouseByCountry,
            ],
            'total_customers' => [
                'value' => (string) ((int) ($stats->total_customers ?? 0)),
                'label' => 'Total customers',
                'icon' => 'i-heroicons-users',
            ],
            'total_suppliers_code' => [
                'value' => (string) ((int) ($stats->total_suppliers_code ?? 0)),
                'label' => 'Total suppliers code',
                'icon' => 'i-heroicons-tag',
            ],
            'total_nc' => [
                'value' => (string) ((int) ($stats->total_nc ?? 0)),
                'label' => 'Total NC',
                'icon' => 'i-heroicons-exclamation-triangle',
            ],
        ];
    }

    /**
     * @param bool $includeVendido
     * @param bool $includePendiente
     * @return array<string, array<string, mixed>>
     */
    public function empty($includeVendido = false, $includePendiente = false)
    {
        $headers = [];
        if ($includeVendido) {
            $headers['cbm_vendido'] = [
                'value' => '0.000',
                'label' => 'CBM Vendido',
                'icon' => 'fluent:box-32-filled',
                'by_country' => [],
            ];
        }
        if ($includePendiente) {
            $headers['cbm_pendiente'] = [
                'value' => '0.000',
                'label' => 'CBM Pendiente',
                'icon' => 'heroicons:clock',
                'by_country' => [],
            ];
        }

        return $headers + [
            'cbm_warehouse' => ['value' => '0.000', 'label' => 'CBM Warehouse', 'icon' => 'i-heroicons-cube', 'by_country' => []],
            'total_customers' => ['value' => '0', 'label' => 'Total customers', 'icon' => 'i-heroicons-users'],
            'total_suppliers_code' => ['value' => '0', 'label' => 'Total suppliers code', 'icon' => 'i-heroicons-tag'],
            'total_nc' => ['value' => '0', 'label' => 'Total NC', 'icon' => 'i-heroicons-exclamation-triangle'],
        ];
    }

    /**
     * @param array<int, int> $orgIds
     * @param bool $withProveedores
     * @return \Illuminate\Database\Query\Builder
     */
    private function scopedCustomersQuery(array $orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin, $withProveedores)
    {
        $query = DB::table($this->tableCotizacion . ' as CC')
            ->join($this->tableContenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
            ->leftJoin($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
            ->whereIn('CONT.organizacion_id', $orgIds)
            ->whereNull('CC.deleted_at')
            ->whereNull('CONT.deleted_at')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CONT.empresa', '!=', 1);

        if ($withProveedores) {
            $query->leftJoin($this->tableProveedor . ' as PR', 'PR.id_cotizacion', '=', 'CC.id');
        }

        ClientesVisibility::applyConfirmadoParaBd($query, 'CC');
        ClientesVisibility::excludeGraduadosDeCustomers(
            $query,
            'CC',
            'CONT',
            $this->tableProveedor
        );
        $this->applyFilters(
            $query,
            $search,
            $idPais,
            $estadoChina,
            $withProveedores ? 'PR' : null,
            $fechaInicio,
            $fechaFin
        );

        return $query;
    }

    /**
     * @param array<int, int> $orgIds
     * @param bool $withProveedores
     * @return \Illuminate\Database\Query\Builder
     */
    private function scopedResumenQuery(array $orgIds, $search, $estadoChina, $withProveedores)
    {
        $query = DB::table($this->tableCotizacion . ' as CC')
            ->leftJoin($this->tableContenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
            ->leftJoin($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
            ->whereIn('CC.organizacion_id', $orgIds)
            ->whereNull('CC.deleted_at')
            ->whereNull('CC.id_cliente_importacion')
            ->where(function ($q) {
                $q->whereIn('CC.estado_resumen', ['COTIZADO', 'CONFIRMADO'])
                    ->orWhereNull('CC.estado_resumen');
            });

        if ($withProveedores) {
            $query->leftJoin($this->tableProveedor . ' as PR', function ($join) {
                $join->on('PR.id_cotizacion', '=', 'CC.id')
                    ->where('PR.modo_cotizacion', '=', 'resumen');
            });
        }

        $this->applyFilters($query, $search, null, $estadoChina, $withProveedores ? 'PR' : null);

        return $query;
    }

    /**
     * @param array<int, int> $orgIds
     * @param string $tipo vendido|warehouse
     * @return array<int, array<string, string>>
     */
    private function cbmByCountry(array $orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin, $tipo)
    {
        $sumExpr = $tipo === 'warehouse' || $tipo === 'vendido'
            ? 'COALESCE(SUM(' . $this->sqlCbmFull('PR') . '), 0)'
            : 'COALESCE(SUM(CC.volumen), 0)';

        $rows = $this->scopedCustomersQuery($orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin, true)
            ->selectRaw('COALESCE(P.No_Pais, "Sin país") as country')
            ->selectRaw($sumExpr . ' as value')
            ->groupBy(DB::raw('COALESCE(P.No_Pais, "Sin país")'))
            ->orderBy('country')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $country = trim((string) $row->country);
            $out[] = [
                'country' => $country !== '' ? $country : 'Sin país',
                'value' => number_format((float) $row->value, 3, '.', ''),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return float
     */
    private function sumCountryValues(array $rows)
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($row['value'] ?? 0);
        }

        return $total;
    }

    /**
     * @param \Illuminate\Support\Collection|array $rows
     * @return array<int, array<string, string>>
     */
    private function mapCountryRows($rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $value = (float) $row->value;
            if ($value == 0.0) {
                continue;
            }
            $country = trim((string) $row->country);
            $out[] = [
                'country' => $country !== '' ? $country : 'Sin país',
                'value' => number_format($value, 3, '.', ''),
            ];
        }

        return $out;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $search
     * @param mixed $idPais
     * @param mixed $estadoChina
     * @param string|null $proveedorEstadoAlias
     * @param mixed $fechaInicio
     * @param mixed $fechaFin
     * @return void
     */
    public function applyFilters($query, $search, $idPais, $estadoChina, $proveedorEstadoAlias = null, $fechaInicio = null, $fechaFin = null)
    {
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('CC.nombre', 'like', $like)
                    ->orWhere('CC.telefono', 'like', $like)
                    ->orWhere('CC.documento', 'like', $like)
                    ->orWhere('CONT.carga', 'like', $like)
                    ->orWhere('P.No_Pais', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))
                            ->from($this->tableProveedor . ' as PRS')
                            ->whereColumn('PRS.id_cotizacion', 'CC.id')
                            ->where(function ($inner) use ($like) {
                                $inner->where('PRS.supplier', 'like', $like)
                                    ->orWhere('PRS.code_supplier', 'like', $like)
                                    ->orWhere('PRS.products', 'like', $like)
                                    ->orWhere('PRS.supplier_phone', 'like', $like);
                            });
                    });
            });
        }

        if (!$this->isFilterAll($idPais)) {
            $query->where('CONT.id_pais', (int) $this->scalarFilter($idPais));
        }

        if (!$this->isFilterAll($estadoChina)) {
            $estadoFiltro = (string) $this->scalarFilter($estadoChina);
            if ($proveedorEstadoAlias) {
                $query->where($proveedorEstadoAlias . '.estados_proveedor', $estadoFiltro);
            } else {
                $query->whereExists(function ($sub) use ($estadoFiltro) {
                    $sub->select(DB::raw(1))
                        ->from($this->tableProveedor . ' as PRF')
                        ->whereColumn('PRF.id_cotizacion', 'CC.id')
                        ->where('PRF.estados_proveedor', $estadoFiltro);
                });
            }
        }

        $desde = $this->dateFilter($fechaInicio);
        $hasta = $this->dateFilter($fechaFin);
        if ($desde) {
            $query->whereDate('CONT.f_inicio', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('CONT.f_inicio', '<=', $hasta);
        }
    }

    /**
     * CBM cotizado completo: volumen normal + IMO.
     *
     * @param string $alias
     * @return string
     */
    private function sqlCbmFull($alias = 'PR')
    {
        return '(COALESCE(' . $alias . '.cbm_total, 0) + COALESCE(' . $alias . '.cbm_imo, 0))';
    }

    /**
     * Inserta las fechas proyectadas a 60 y 65 CBM justo después de CBM Warehouse.
     *
     * @param array<string, array<string, mixed>> $headers
     * @param int $idContenedor
     * @return array<string, array<string, mixed>>
     */
    private function insertCbmDateHeaders(array $headers, $idContenedor)
    {
        $proy = $this->fechasProyeccionCbm($idContenedor);
        $out = [];
        $inserted = false;
        foreach ($headers as $key => $header) {
            $out[$key] = $header;
            if ($key === 'cbm_warehouse') {
                $out['fecha_cbm_60'] = $this->headerFechaCbm(60, $proy['fecha_60']);
                $out['fecha_cbm_65'] = $this->headerFechaCbm(65, $proy['fecha_65']);
                $inserted = true;
            }
        }
        if (!$inserted) {
            $out['fecha_cbm_60'] = $this->headerFechaCbm(60, $proy['fecha_60']);
            $out['fecha_cbm_65'] = $this->headerFechaCbm(65, $proy['fecha_65']);
        }

        return $out;
    }

    /**
     * @param int $umbral
     * @param string|null $fechaIso
     * @return array<string, string>
     */
    private function headerFechaCbm($umbral, $fechaIso)
    {
        $value = '—';
        if ($fechaIso) {
            $ts = strtotime($fechaIso);
            if ($ts) {
                $value = date('d/m/Y', $ts);
            }
        }

        return [
            'value' => $value,
            'label' => ((int) $umbral) . ' CBM date',
            'icon' => 'heroicons:calendar-days',
            'hint' => 'Arrive Date projection',
        ];
    }

    /**
     * Proyección por Arrive Date China: primer día en que el CBM acumulado
     * llega a 60 y a 65.
     *
     * @param int $idContenedor
     * @return array{fecha_60: string|null, fecha_65: string|null}
     */
    private function fechasProyeccionCbm($idContenedor)
    {
        $query = DB::table($this->tableCotizacion . ' as CC')
            ->join($this->tableProveedor . ' as PR', 'PR.id_cotizacion', '=', 'CC.id')
            ->where('CC.id_contenedor', (int) $idContenedor)
            ->whereNull('CC.deleted_at')
            ->whereNull('CC.id_cliente_importacion');
        ClientesVisibility::applyConfirmadoParaBd($query, 'CC');
        $rows = $query
            ->select([
                'PR.cbm_total',
                'PR.cbm_imo',
                'PR.cbm_total_china',
                'PR.arrive_date_china',
            ])
            ->get();

        $sinFecha = 0.0;
        $items = [];
        foreach ($rows as $row) {
            $china = (float) ($row->cbm_total_china ?? 0);
            $quoted = (float) ($row->cbm_total ?? 0) + (float) ($row->cbm_imo ?? 0);
            $cbm = $china > 0 ? $china : $quoted;
            if ($cbm <= 0) {
                continue;
            }
            $fecha = trim((string) ($row->arrive_date_china ?? ''));
            if ($fecha === '' || strpos($fecha, '0000-00-00') === 0) {
                $sinFecha += $cbm;
                continue;
            }
            $items[] = [
                'fecha' => substr($fecha, 0, 10),
                'cbm' => $cbm,
            ];
        }

        usort($items, function ($a, $b) {
            return strcmp($a['fecha'], $b['fecha']);
        });

        $cum = $sinFecha;
        $fecha60 = null;
        $fecha65 = null;
        $i = 0;
        $n = count($items);
        while ($i < $n) {
            $day = $items[$i]['fecha'];
            while ($i < $n && $items[$i]['fecha'] === $day) {
                $cum += $items[$i]['cbm'];
                $i++;
            }
            if ($fecha60 === null && $cum >= 60) {
                $fecha60 = $day;
            }
            if ($fecha65 === null && $cum >= 65) {
                $fecha65 = $day;
                break;
            }
        }

        return [
            'fecha_60' => $fecha60,
            'fecha_65' => $fecha65,
        ];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function scalarFilter($value)
    {
        if (is_array($value) && array_key_exists('value', $value)) {
            return $this->scalarFilter($value['value']);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isFilterAll($value)
    {
        $scalar = $this->scalarFilter($value);
        if ($scalar === null || $scalar === '') {
            return true;
        }
        if (is_array($scalar)) {
            return true;
        }

        return in_array(strtolower(trim((string) $scalar)), ['todos', 'todas', 'all', 'todo'], true);
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function dateFilter($value)
    {
        $scalar = trim((string) $this->scalarFilter($value));
        if ($scalar === '' || $this->isFilterAll($scalar)) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scalar)) {
            return null;
        }

        return $scalar;
    }
}
