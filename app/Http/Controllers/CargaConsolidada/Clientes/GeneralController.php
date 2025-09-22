<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;

use App\Http\Controllers\Controller;
use App\Traits\UserGroupsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\CargaConsolidada\Clientes\GeneralService;
use App\Services\CargaConsolidada\Clientes\GeneralExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;


class GeneralController extends Controller
{
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $table = "carga_consolidada_contenedor";

    protected $generalService;
    protected $generalExportService;

    public function __construct(
        GeneralService $generalService,
        GeneralExportService $generalExportService
    ) {
        $this->generalService = $generalService;
        $this->generalExportService = $generalExportService;
    }

    use UserGroupsTrait;

    /**
     * Format a numeric value as currency string, e.g., $1,234.56
     */
    private function formatCurrency($value, $symbol = '$')
    {
        $num = is_numeric($value) ? (float) $value : 0.0;
        return $symbol . number_format($num, 2, '.', ',');
    }

    /**
     * Add value_formatted to headers with currency values (CBMs and logística related)
     */
    private function addCurrencyFormatting(array $headers)
    {
        $keysToFormat = ['total_logistica', 'total_logistica_pagado', 'total_fob', 'total_impuestos'];
        foreach ($headers as $k => $item) {
            if (is_array($item) && array_key_exists('value', $item) && in_array($k, $keysToFormat)) {
                $headers[$k]['value'] = $this->formatCurrency($item['value']);
            }
        }
        return $headers;
    }

    public function index(Request $request, $idContenedor)
    {
        // Convertir la consulta SQL de CodeIgniter a Query Builder de Laravel
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.*',
                'C.*',
                'CC.id as id_cotizacion',
                'TC.name as name',
                'U.No_Nombres_Apellidos as asesor',
                DB::raw("CONCAT(
                    C.carga,
                    DATE_FORMAT(CC.fecha, '%d%m%y'),
                    UPPER(LEFT(TRIM(CC.nombre), 3))
                ) as COD")
            ])
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->join('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'CC.id_usuario')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');

        // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 10);
        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Paginación
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $data->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }
    public function updateStatusCliente(Request $request)
    {
        try {
            $id_cotizacion = $request->id_cotizacion;
            $status = $request->status_cliente_doc;
            $user = JWTAuth::parseToken()->authenticate();
            if ($user->getNombreGrupo() != Usuario::ROL_DOCUMENTACION) {
                return [
                    'success' => false,
                    'message' => 'No tienes permisos para actualizar el estado del cliente'
                ];
            }

            $cotizacion = Cotizacion::find($id_cotizacion);
            if (!$cotizacion) {
                return [
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ];
            }

            $currentStatus = $cotizacion->status_cliente_doc;   
            if ($currentStatus != 'Completado') {
                $cotizacion->update(['status_cliente_doc' => $status]);
                return [
                    'success' => true,
                    'message' => 'Estado del cliente actualizado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Solo se puede cambiar el estado si está en Pendiente.'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error en updateStatusCliente: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => $e->getMessage()
            ];
        }
    }
    public function updateEstadoCliente(Request $request)
    {
        $id = $request->id_cotizacion;
        $estado = $request->estado_cliente;
        Log::info('id', ['id' => $id]);
        Log::info('estado', ['estado' => $estado]);
        $cotizacion = DB::table($this->table_contenedor_cotizacion)
            ->where('id', $id)
            ->update(['estado_cliente' => $estado]);
        if ($cotizacion) {
            return response()->json([
                'success' => true,
                'message' => 'Estado del cliente actualizado correctamente'
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'Cotización no encontrada'
        ], 404);
    }
    public function getHeadersData($idContenedor)
    {
        $headers = DB::table($this->table_contenedor_cotizacion)
            ->select([
                DB::raw('SUM(qty_items) as total_qty_items'),
                DB::raw('SUM(total_logistica) as total_logistica'),
                DB::raw('SUM(total_logistica_pagado) as total_logistica_pagado'),
            ])
            ->where('id_contenedor', $idContenedor)
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereNull('id_cliente_importacion')
            ->first();
        // Attach formatted totals alongside numeric values
        if ($headers) {
            $headers->total_logistica_formatted = $this->formatCurrency($headers->total_logistica ?? 0);
            $headers->total_logistica_pagado_formatted = $this->formatCurrency($headers->total_logistica_pagado ?? 0);
        }
        return response()->json([
            'data' => $headers,
            'success' => true
        ]);
    }

    public function getClientesHeader($idContenedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            // Consulta principal con múltiples subconsultas
            $result = DB::table($this->table_contenedor_cotizacion_proveedores . ' as cccp')
                ->select([
                    // CBM total china con condición de estado
                    DB::raw('COALESCE(SUM(IF(cc.estado_cotizador = "CONFIRMADO", cccp.cbm_total_china, 0)), 0) as cbm_total_china'),

                    // Subconsulta para cbm_total
                    DB::raw('(
                        SELECT COALESCE(SUM(cbm_total), 0) 
                        FROM ' . $this->table_contenedor_cotizacion_proveedores . ' 
                        WHERE id_contenedor = ' . $idContenedor . '
                        AND estados_proveedor = "LOADED"
                        AND id_cotizacion IN (
                            SELECT id 
                            FROM ' . $this->table_contenedor_cotizacion . ' 
                            WHERE estado_cotizador = "CONFIRMADO"
                        )
                    ) as cbm_total'),

                    // Subconsulta para total_logistica
                    DB::raw('(
                        SELECT COALESCE(SUM(monto), 0) 
                        FROM ' . $this->table_contenedor_cotizacion . ' 
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion 
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' 
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                        AND estado_cliente IS NOT NULL
                        AND id_cliente_importacion IS NULL
                        AND (id_contenedor_pago =' .$idContenedor. ' OR id_contenedor_pago is null)

                    ) as total_logistica'),

                    // Subconsulta para total_fob_loaded
                    DB::raw('(
                        SELECT COALESCE(SUM(valor_doc), 0) 
                        FROM ' . $this->table_contenedor_cotizacion . ' 
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion 
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' 
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                        AND id IN (
                            SELECT id_cotizacion 
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' 
                            WHERE id_contenedor = ' . $idContenedor . '
                            AND estados_proveedor = "LOADED"
                        )
                    ) as total_fob_loaded'),

                    // Subconsulta para total_fob
                    DB::raw('(
                        SELECT COALESCE(SUM(fob), 0) 
                        FROM ' . $this->table_contenedor_cotizacion . ' 
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion 
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' 
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                        AND estado_cliente IS NOT NULL
                        AND id_cliente_importacion IS NULL
                    ) as total_fob'),

                    // Subconsulta para total_qty_items
                    DB::raw('(
                        SELECT COALESCE(SUM(qty_item), 0)
                        FROM ' . $this->table_contenedor_cotizacion . '
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . '
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                        AND estado_cliente IS NOT NULL
                        AND id_cliente_importacion IS NULL
                    ) as total_qty_items'),

                    // Subconsulta para total_logistica_pagado
                    DB::raw('(
                        SELECT COALESCE(SUM(monto), 0)
                        FROM ' . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . ' 
                        JOIN ' . $this->table_pagos_concept . ' ON ' . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . '.id_concept = ' . $this->table_pagos_concept . '.id
                        WHERE id_contenedor = ' . $idContenedor . '
                        AND ' . $this->table_pagos_concept . '.name = "LOGISTICA"
                    ) as total_logistica_pagado')
                ])
                ->join($this->table_contenedor_cotizacion . ' as cc', 'cccp.id_cotizacion', '=', 'cc.id')
                ->where('cccp.id_contenedor', $idContenedor)
                ->where('cccp.estados_proveedor', 'LOADED')
                ->whereNull('id_cliente_importacion')

                ->first();

            // Consulta para obtener carga del contenedor
            $cargaRow = DB::table($this->table)
                ->select('carga')
                ->where('id', $idContenedor)
                ->first();

            // Consulta para obtener bl_file_url y lista_empaque_file_url
            $result2 = DB::table($this->table)
                ->select('bl_file_url', 'lista_embarque_url')
                ->where('id', $idContenedor)
                ->first();

            // Consulta para obtener total de impuestos
            $result3 = DB::table($this->table_contenedor_cotizacion)
                ->select(DB::raw('COALESCE(SUM(impuestos), 0) as total_impuestos'))
                ->where('estado_cotizador', 'CONFIRMADO')
                ->where('id_contenedor', $idContenedor)
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->first();

            if ($result) {
                //separte for roles
                $roleAllowedMap = [
                    Usuario::ROL_COTIZADOR => ['cbm_total_china', 'cbm_total', 'total_logistica', 'total_logistica_pagado', 'qty_items', 'total_fob', 'total_impuestos'],
                    Usuario::ROL_ADMINISTRACION => ['cbm_total_china', 'cbm_total', 'total_logistica', 'total_logistica_pagado', 'qty_items', 'total_fob', 'total_impuestos'],
                    Usuario::ROL_COORDINACION => ['cbm_total_china', 'cbm_total', 'total_logistica', 'total_logistica_pagado', 'qty_items', 'total_fob', 'total_impuestos'],
                    Usuario::ROL_DOCUMENTACION => [ null],
                ];
                $userIdCheck = $user->getNombreGrupo();
                $headersData = [
                    'cbm_total_china' => [
                        'value' => $result->cbm_total_china,
                        'label' => 'CBM Total China',
                        'icon' => 'https://upload.wikimedia.org/wikipedia/commons/f/fa/Flag_of_the_People%27s_Republic_of_China.svg'
                    ],
                    'cbm_total' => [
                        'value' => $result->cbm_total,
                        'label' => 'CBM Total',
                        'icon' => 'https://upload.wikimedia.org/wikipedia/commons/c/cf/Flag_of_Peru.svg'
                    ],
                    /*cbm_total_pendiente' => ['value' => $result->cbm_total_pendiente ?? 0, 'label' => 'CBM Total Pendiente', 'icon' => 'i-heroicons-currency-dollar'],*/
                    'total_logistica' => ['value' => $result->total_logistica, 'label' => 'Total Logistica', 'icon' => 'cryptocurrency-color:soc'],
                    'total_logistica_pagado' => ['value' => round($result->total_logistica_pagado, 2), 'label' => 'Total Logistica Pagado', 'icon' => 'cryptocurrency-color:soc'],
                    /*'bl_file_url' => ['value' => $result2->bl_file_url ?? '', 'label' => 'BL File URL', 'icon' => 'i-heroicons-currency-dollar'],*/
                    /*'lista_embarque_url' => ['value' => $result2->lista_embarque_url ?? '', 'label' => 'Lista Embarque URL', 'icon' => 'i-heroicons-currency-dollar'],*/
                    'total_fob' => ['value' => $result->total_fob, 'label' => 'Total FOB', 'icon' => 'cryptocurrency-color:soc'],
                    'total_impuestos' => ['value' => $result3->total_impuestos, 'label' => 'Total Impuestos', 'icon' => 'cryptocurrency-color:soc'],
                    'qty_items' => ['value' => $result->total_qty_items, 'label' => 'Cantidad de Items', 'icon' => 'bi:boxes']
                ];
                if (array_key_exists($userIdCheck, $roleAllowedMap)) {
                    $allowedKeys = $roleAllowedMap[$userIdCheck];
                    $headersData = array_filter($headersData, function ($key) use ($allowedKeys) {
                        return in_array($key, $allowedKeys);
                    }, ARRAY_FILTER_USE_KEY);
                } 
                // Add formatted currency strings for CBMs and logística totals
                $headersData = $this->addCurrencyFormatting($headersData);
                return response()->json([
                    'success' => true,
                    'data' => $headersData,
                    'carga' => $cargaRow->carga ?? '',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'data' => $this->addCurrencyFormatting([
                        'cbm_total_china' => ['value' => 0, 'label' => 'CBM Total China', 'icon' => 'i-heroicons-currency-dollar'],
                        'cbm_total_pendiente' => ['value' => 0, 'label' => 'CBM Total Pendiente', 'icon' => 'i-heroicons-currency-dollar'],
                        'total_logistica' => ['value' => 0, 'label' => 'Total Logistica', 'icon' => 'cryptocurrency-color:soc'],
                        'total_logistica_pagado' => ['value' => 0, 'label' => 'Total Logistica Pagado', 'icon' => 'cryptocurrency-color:soc'],
                        'cbm_total' => ['value' => 0, 'label' => 'CBM Total', 'icon' => 'i-heroicons-currency-dollar'],
                        'total_fob' => ['value' => 0, 'label' => 'Total FOB', 'icon' => 'i-heroicons-currency-dollar'],
                        'total_impuestos' => ['value' => 0, 'label' => 'Total Impuestos', 'icon' => 'i-heroicons-currency-dollar'],
                        'qty_items' => ['value' => 0, 'label' => 'Cantidad de Items', 'icon' => 'bi:boxes'],
                        'bl_file_url' => ['value' => '', 'label' => 'BL File URL', 'icon' => 'i-heroicons-currency-dollar'],
                        'lista_embarque_url' => ['value' => '', 'label' => 'Lista Embarque URL', 'icon' => 'i-heroicons-currency-dollar']
                    ]),
                    'carga' => $cargaRow->carga ?? '',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en getClientesHeader: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del header',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function exportarClientes(Request $request, $idContenedor)
    {
        try {
            return $this->generalExportService->exportarClientes($request, $idContenedor);
        } catch (\Exception $e) {
            Log::error('Error en exportarClientes: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
