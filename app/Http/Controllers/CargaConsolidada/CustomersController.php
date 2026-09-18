<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use App\Services\CargaConsolidada\CustomersHeadersService;
use App\Support\CargaConsolidada\ClientesVisibility;

/**
 * Vista global Customers (Almacén China): embarcados de todos los consolidados.
 */
class CustomersController extends Controller
{
    private $tableCotizacion = 'contenedor_consolidado_cotizacion';
    private $tableProveedor = 'contenedor_consolidado_cotizacion_proveedores';
    private $tableContenedor = 'carga_consolidada_contenedor';
    private $tablePais = 'pais';

    /**
     * GET /carga-consolidada/customers
     */
    public function index(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $orgIds = $user->organizacionesPermitidas();
            if (empty($orgIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => $this->emptyPagination(),
                    'headers' => $this->emptyHeaders(),
                    'paises' => [],
                ]);
            }

            $page = max(1, (int) $request->input('currentPage', $request->input('page', 1)));
            $perPage = (int) $request->input('itemsPerPage', $request->input('limit', 10));
            if (!in_array($perPage, [5, 10, 20, 50, 100], true)) {
                $perPage = 10;
            }
            $search = trim((string) $request->input('search', ''));
            $idPais = $request->input('id_pais', $request->input('pais'));
            $estadoChina = $request->input('estado_china', 'todos');
            $fechaInicio = $request->input('fecha_inicio', $request->input('start_date'));
            $fechaFin = $request->input('fecha_fin', $request->input('end_date'));

            $baseQuery = $this->baseCustomersQuery($orgIds);
            $this->applyCustomersFilters($baseQuery, $search, $idPais, $estadoChina, null, $fechaInicio, $fechaFin);

            $pageQuery = clone $baseQuery;
            $cotizacionesPage = $pageQuery
                ->orderByRaw('YEAR(CONT.f_inicio) DESC')
                ->orderBy('CONT.carga', 'desc')
                ->orderBy('CC.id', 'asc')
                ->paginate($perPage, ['CC.id'], 'page', $page);

            $items = collect($cotizacionesPage->items());
            $ids = $items->pluck('id')->all();

            $headers = $this->buildHeaders($orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin);
            $paises = $this->buildPaises($orgIds);

            if (empty($ids)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'current_page' => $cotizacionesPage->currentPage(),
                        'last_page' => $cotizacionesPage->lastPage(),
                        'per_page' => $cotizacionesPage->perPage(),
                        'total' => $cotizacionesPage->total(),
                        'from' => $cotizacionesPage->firstItem(),
                        'to' => $cotizacionesPage->lastItem(),
                    ],
                    'headers' => $headers,
                    'paises' => $paises,
                ]);
            }

            $rows = DB::table($this->tableCotizacion . ' as CC')
                ->leftJoin($this->tableContenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
                ->leftJoin($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
                ->select([
                    'CC.id',
                    'CC.uuid',
                    'CC.nombre',
                    'CC.telefono',
                    'CC.id_contenedor',
                    'CC.estado_cotizador',
                    'CONT.carga',
                    'CONT.f_inicio',
                    'CONT.estado_china as contenedor_estado_china',
                    'CONT.id_pais',
                    'P.No_Pais as pais',
                ])
                ->whereIn('CC.id', $ids)
                ->get()
                ->keyBy('id');

            $proveedoresQuery = DB::table($this->tableProveedor)
                ->whereIn('id_cotizacion', $ids)
                ->select([
                    'id',
                    'id_cotizacion',
                    'qty_box',
                    'qty_pallet_china',
                    'peso',
                    'peso_china',
                    'cbm_total',
                    'cbm_total_china',
                    'supplier',
                    'code_supplier',
                    'estados_proveedor',
                    'estados',
                    'supplier_phone',
                    'qty_box_china',
                    'products',
                    'arrive_date_china',
                    'arrive_date',
                ]);

            if ($estadoChina !== null && $estadoChina !== '' && strtolower((string) $estadoChina) !== 'todos') {
                $proveedoresQuery->where('estados_proveedor', (string) $estadoChina);
            }

            $proveedores = $proveedoresQuery->get()->groupBy('id_cotizacion');

            $data = collect($ids)->map(function ($id) use ($rows, $proveedores) {
                $item = $rows->get($id);
                if (!$item) {
                    return null;
                }

                $anio = $item->f_inicio ? date('Y', strtotime($item->f_inicio)) : '';
                $cerrado = strtoupper((string) $item->contenedor_estado_china) === 'COMPLETADO';
                $provList = [];
                if (isset($proveedores[$item->id])) {
                    $provList = collect($proveedores[$item->id])->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'id_proveedor' => $p->id,
                            'products' => $p->products,
                            'supplier' => $p->supplier,
                            'code_supplier' => $p->code_supplier,
                            'supplier_phone' => $p->supplier_phone,
                            'qty_box' => $p->qty_box,
                            'qty_box_china' => $p->qty_box_china,
                            'qty_pallet_china' => $p->qty_pallet_china,
                            'cbm_total' => $p->cbm_total,
                            'cbm_total_china' => $p->cbm_total_china,
                            'peso' => $p->peso,
                            'peso_china' => $p->peso_china,
                            'estados_proveedor' => $p->estados_proveedor,
                            'estados' => $p->estados,
                            'arrive_date_china' => $p->arrive_date_china,
                            'arrive_date' => $p->arrive_date,
                        ];
                    })->values()->all();
                }

                return [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'nombre' => $item->nombre,
                    'telefono' => $item->telefono,
                    'id_contenedor' => $item->id_contenedor,
                    'estado_cotizador' => $item->estado_cotizador,
                    'carga' => $item->carga,
                    'anio' => $anio,
                    'carga_label' => $item->carga !== null && $item->carga !== ''
                        ? '#' . $item->carga . ($anio !== '' ? '-' . $anio : '')
                        : '',
                    'pais' => $item->pais,
                    'id_pais' => $item->id_pais,
                    'contenedor_estado_china' => $item->contenedor_estado_china,
                    'contenedor_cerrado' => $cerrado,
                    'proveedores' => $provList,
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $cotizacionesPage->currentPage(),
                    'last_page' => $cotizacionesPage->lastPage(),
                    'per_page' => $cotizacionesPage->perPage(),
                    'total' => $cotizacionesPage->total(),
                    'from' => $cotizacionesPage->firstItem(),
                    'to' => $cotizacionesPage->lastItem(),
                ],
                'headers' => $headers,
                'paises' => $paises,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener customers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener customers.',
            ], 500);
        }
    }

    private function baseCustomersQuery($orgIds)
    {
        $query = DB::table($this->tableCotizacion . ' as CC')
            ->join($this->tableContenedor . ' as CONT', 'CONT.id', '=', 'CC.id_contenedor')
            ->leftJoin($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
            ->select(['CC.id'])
            ->whereIn('CONT.organizacion_id', $orgIds)
            ->whereNull('CC.deleted_at')
            ->whereNull('CONT.deleted_at')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CONT.empresa', '!=', 1)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from($this->tableProveedor)
                    ->whereColumn($this->tableProveedor . '.id_cotizacion', 'CC.id');
            });

        ClientesVisibility::applyConfirmadoParaBd($query, 'CC');
        ClientesVisibility::excludeGraduadosDeCustomers(
            $query,
            'CC',
            'CONT',
            $this->tableProveedor
        );

        return $query;
    }

    /**
     * Mismos filtros de listado y de KPI (país, estado, búsqueda).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $search
     * @param mixed $idPais
     * @param mixed $estadoChina
     * @param string|null $proveedorEstadoAlias
     * @param mixed $fechaInicio
     * @param mixed $fechaFin
     * @return void
     */
    private function applyCustomersFilters($query, $search, $idPais, $estadoChina, $proveedorEstadoAlias = null, $fechaInicio = null, $fechaFin = null)
    {
        (new CustomersHeadersService())->applyFilters($query, $search, $idPais, $estadoChina, $proveedorEstadoAlias, $fechaInicio, $fechaFin);
    }

    private function buildHeaders($orgIds, $search = '', $idPais = null, $estadoChina = 'todos', $fechaInicio = null, $fechaFin = null)
    {
        return (new CustomersHeadersService())->build($orgIds, $search, $idPais, $estadoChina, $fechaInicio, $fechaFin);
    }

    private function buildPaises($orgIds)
    {
        return DB::table($this->tableContenedor . ' as CONT')
            ->join($this->tablePais . ' as P', 'P.ID_Pais', '=', 'CONT.id_pais')
            ->whereIn('CONT.organizacion_id', $orgIds)
            ->whereNull('CONT.deleted_at')
            ->where('CONT.empresa', '!=', 1)
            ->select(['P.ID_Pais as id', 'P.No_Pais as nombre'])
            ->distinct()
            ->orderBy('P.No_Pais')
            ->get()
            ->values();
    }

    private function emptyPagination()
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 10,
            'total' => 0,
            'from' => 0,
            'to' => 0,
        ];
    }

    private function emptyHeaders()
    {
        return (new CustomersHeadersService())->empty();
    }
}
