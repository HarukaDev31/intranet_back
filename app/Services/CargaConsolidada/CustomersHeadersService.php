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
            return $this->empty();
        }

        $statsQuery = DB::table($this->tableCotizacion . ' as CC')
            ->join($this->tableContenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
            ->leftJoin($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
            ->leftJoin($this->tableProveedor . ' as PR', 'PR.id_cotizacion', '=', 'CC.id')
            ->whereIn('CONT.organizacion_id', $orgIds)
            ->whereNull('CC.deleted_at')
            ->whereNull('CONT.deleted_at')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CONT.empresa', '!=', 1);
        ClientesVisibility::applyConfirmadoParaBd($statsQuery, 'CC');
        ClientesVisibility::excludeGraduadosDeCustomers(
            $statsQuery,
            'CC',
            'CONT',
            $this->tableProveedor
        );
        $this->applyFilters($statsQuery, $search, $idPais, $estadoChina, 'PR', $fechaInicio, $fechaFin);
        $stats = $statsQuery
            ->selectRaw('
                COALESCE(SUM(PR.cbm_total), 0) as cbm_warehouse,
                COUNT(DISTINCT CC.id) as total_customers,
                COUNT(PR.id) as total_suppliers_code,
                SUM(CASE WHEN PR.estados_proveedor = ? THEN 1 ELSE 0 END) as total_nc
            ', ['NC'])
            ->first();

        return $this->format($stats);
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
            return $this->empty();
        }

        $statsQuery = DB::table($this->tableCotizacion . ' as CC')
            ->leftJoin($this->tableContenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
            ->leftJoin($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
            ->leftJoin($this->tableProveedor . ' as PR', function ($join) {
                $join->on('PR.id_cotizacion', '=', 'CC.id')
                    ->where('PR.modo_cotizacion', '=', 'resumen');
            })
            ->whereIn('CC.organizacion_id', $orgIds)
            ->whereNull('CC.deleted_at')
            ->whereNull('CC.id_cliente_importacion')
            ->where(function ($q) {
                $q->whereIn('CC.estado_resumen', ['COTIZADO', 'CONFIRMADO'])
                    ->orWhereNull('CC.estado_resumen');
            });
        $this->applyFilters($statsQuery, $search, null, $estadoChina, 'PR');
        $stats = $statsQuery
            ->selectRaw('
                COALESCE(SUM(COALESCE(PR.cbm_total, 0) + COALESCE(PR.cbm_imo, 0)), 0) as cbm_warehouse,
                COUNT(DISTINCT CC.id) as total_customers,
                COUNT(PR.id) as total_suppliers_code,
                SUM(CASE WHEN PR.estados_proveedor = ? THEN 1 ELSE 0 END) as total_nc
            ', ['NC'])
            ->first();

        return $this->format($stats);
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
                COALESCE(SUM(PR.cbm_total), 0) as cbm_warehouse,
                COUNT(DISTINCT CC.id) as total_customers,
                COUNT(PR.id) as total_suppliers_code,
                SUM(CASE WHEN PR.estados_proveedor = ? THEN 1 ELSE 0 END) as total_nc
            ', ['NC'])
            ->first();

        return $this->format($stats);
    }

    /**
     * @param object|null $stats
     * @return array<string, array<string, string>>
     */
    private function format($stats)
    {
        if (!$stats) {
            return $this->empty();
        }

        return [
            'cbm_warehouse' => [
                'value' => number_format((float) ($stats->cbm_warehouse ?? 0), 3, '.', ''),
                'label' => 'CBM Warehouse',
                'icon' => 'i-heroicons-cube',
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
     * @return array<string, array<string, string>>
     */
    public function empty()
    {
        return [
            'cbm_warehouse' => ['value' => '0.000', 'label' => 'CBM Warehouse', 'icon' => 'i-heroicons-cube'],
            'total_customers' => ['value' => '0', 'label' => 'Total customers', 'icon' => 'i-heroicons-users'],
            'total_suppliers_code' => ['value' => '0', 'label' => 'Total suppliers code', 'icon' => 'i-heroicons-tag'],
            'total_nc' => ['value' => '0', 'label' => 'Total NC', 'icon' => 'i-heroicons-exclamation-triangle'],
        ];
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
