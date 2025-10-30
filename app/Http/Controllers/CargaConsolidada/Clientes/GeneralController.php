<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;

use App\Http\Controllers\Controller;
use App\Traits\UserGroupsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\CargaConsolidada\Clientes\GeneralService;
use App\Services\CargaConsolidada\Clientes\GeneralExportService;
use App\Models\CargaConsolidada\CotizacionProveedor;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\Contenedor;

class GeneralController extends Controller
{
    use WhatsappTrait;
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
            ->where('CC.estado_cotizador', 'CONFIRMADO')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            });
        // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 10);
        // Aplicar filtros adicionales si se proporcionan (con TRIM y búsqueda mejorada)
        if ($request->has('search') && !empty($request->search)) {
            $search = trim($request->search); // TRIM del request

            $query->where(function ($q) use ($search) {
                // Búsqueda en nombre (con TRIM de BD)
                $q->whereRaw('TRIM(CC.nombre) LIKE ?', ["%{$search}%"])
                    // Búsqueda en documento (con TRIM de BD)
                    ->orWhereRaw('TRIM(CC.documento) LIKE ?', ["%{$search}%"])
                    // Búsqueda en correo (con TRIM de BD)
                    ->orWhereRaw('TRIM(CC.correo) LIKE ?', ["%{$search}%"]);

                // Si el término parece ser un teléfono (contiene solo números, espacios, guiones, etc.)
                if (preg_match('/^[\d\s\-\(\)\.\+]+$/', $search)) {
                    // Normalizar el término de búsqueda (remover espacios, guiones, paréntesis, puntos y +)
                    $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $search);

                    // Si empieza con 51 y tiene más de 9 dígitos, remover prefijo
                    if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $matches)) {
                        $telefonoNormalizado = $matches[1];
                    }

                    if (!empty($telefonoNormalizado)) {
                        // Buscar coincidencias flexibles en teléfono
                        $q->orWhere(function ($subQuery) use ($telefonoNormalizado, $search) {
                            // Búsqueda por teléfono normalizado (eliminar espacios, guiones, etc. de BD)
                            $subQuery->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(CC.telefono), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"])
                                // Búsqueda con prefijo 51
                                ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(CC.telefono), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%51{$telefonoNormalizado}%"])
                                // Búsqueda del término original también (con TRIM)
                                ->orWhereRaw('TRIM(CC.telefono) LIKE ?', ["%{$search}%"]);
                        });
                    }
                } else {
                    // Si no parece teléfono, hacer búsqueda normal en teléfono (con TRIM)
                    $q->orWhereRaw('TRIM(CC.telefono) LIKE ?', ["%{$search}%"]);
                }
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
                    Usuario::ROL_DOCUMENTACION => [null],
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
    public function getProveedoresItemsCotizacion(Request $request, $idCotizacion)
    {
        try {
            $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->select('id', 'code_supplier')
                ->where('id_cotizacion', $idCotizacion)
                ->orderBy('id', 'asc')
                ->get();

            if ($proveedores->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $proveedorIds = $proveedores->pluck('id')->all();
            $items = DB::table('contenedor_consolidado_cotizacion_proveedores_items')
                ->select('id_proveedor', 'initial_name', 'tipo_producto', 'id')
                ->whereIn('id_proveedor', $proveedorIds)
                ->orderBy('id', 'asc')
                ->get()
                ->groupBy('id_proveedor');

            $result = $proveedores->map(function ($prov) use ($items) {
                $provItems = $items->get($prov->id, collect())
                    ->map(function ($it) {
                        return [
                            'id' => $it->id,
                            'initial_name' => $it->initial_name,
                            'tipo_producto' => $it->tipo_producto,
                        ];
                    })
                    ->values();
                return [
                    'id' => $prov->id,
                    'code_supplier' => $prov->code_supplier,
                    'items' => $provItems,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getProveedoresItemsCotizacion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proveedores e items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getProveedorPendingDocuments(Request $request, $idCotizacion)
    {
        try {

            $proveedores = CotizacionProveedor::where('id_cotizacion', $idCotizacion)->get();
            foreach ($proveedores as $proveedor) {
                $id = $proveedor->id;
                $packing_list = $proveedor->packing_list;
                $factura_comercial = $proveedor->factura_comercial;
                $excel_confirmacion = $proveedor->excel_confirmacion;
                $data[] = [
                    'id' => $id,
                    'code_supplier' => $proveedor->code_supplier,
                    'packing_list' => $packing_list,
                    'excel_confirmacion' => $excel_confirmacion,
                    'factura_comercial' => $factura_comercial,
                ];
            }
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getProveedorPendingDocuments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proveedores pendientes de documentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function solicitarDocumentos(Request $request)
    {
        try {
            $data = $request->all();
            $idCotizacion = $data['id_cotizacion'] ?? null;
            $proveedores = $data['proveedores'] ?? [];

            if (!$idCotizacion || empty($proveedores)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payload inválido: se requiere id_cotizacion y proveedores'
                ], 422);
            }

            // Actualizar tipo_producto por item.id
            foreach ($proveedores as $prov) {
                $items = $prov['items'] ?? [];
                foreach ($items as $item) {
                    if (!isset($item['id']) || !isset($item['tipo_producto'])) {
                        continue;
                    }
                    DB::table('contenedor_consolidado_cotizacion_proveedores_items')
                        ->where('id', $item['id'])
                        ->update(['tipo_producto' => $item['tipo_producto']]);
                }
            }

            // Obtener carga a partir de la cotización
            $cot = DB::table('contenedor_consolidado_cotizacion')
                ->select('id_contenedor', 'telefono')
                ->where('id', $idCotizacion)
                ->first();

            if (!$cot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }
            $telefono = $cot->telefono;
            $telefono = preg_replace('/\s+/', '', $telefono);
            $telefono = $telefono ? $telefono . '@c.us' : '';
            //if tel length is 9, add 51 to the beginning

            $cont = DB::table('carga_consolidada_contenedor')
                ->select('carga')
                ->where('id', $cot->id_contenedor)
                ->first();

            $carga = $cont->carga ?? '';
            $cargaCode = is_numeric($carga) ? str_pad($carga, 2, '0', STR_PAD_LEFT) : $carga;

            $message = "Hola 🙋🏻‍♀, según lo conversado en la reunión virtual, necesitamos los siguiente documentos:\n \n*Documentación: CONSOLIDADO #{$cargaCode}\n\n☑ PASO 1: Llenar el Excel de confirmación con las características de los productos que estás importando para poder declarar correctamente tus productos 📄 y evitar multas o pérdidas en aduanas.\n\n📢 IMPORTANTE:  Ver el video sobre el Excel de confirmación. 📋\nVideo:  https://youtu.be/rvhwblBEbXQ";

            $response = $this->sendMessage($message, $telefono, 5);
            Log::info('Respuesta de WhatsApp: ' . json_encode($response));

            // Generar Excel de confirmación por proveedor
            $templatePath = storage_path('app/public/templates/excel-confirmacion/EXCEL_DE_CONFIRMACION_GENERAL.xlsx');
            $outputDir = storage_path('app/public/excel-confirmacion');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0775, true);
            }

            // Mapa de etiquetas por tipo de producto
            $labelsMap = [
                'GENERAL' => [
                    'Material:',
                    'Marca:',
                    'Modelo:',
                    'Tamaño:',
                    'Capacidad (ml o kg):',
                    'Peso Neto:',
                    'Incluye:',
                    'Pares o Piezas:',
                    'Funcion:',
                    'Presentacion (botella, caja, etc.)::',
                    ''
                ],
                'CALZADO' => [
                    'Marca:',
                    'Modelo:',
                    'Material de Capellada (%): ',
                    'Material de Forro (%):',
                    'Material de Plantilla (%):',
                    'Material de Suela (%):',
                    'Talla:',
                    'Colores:',
                    'Incluye:',
                    'Empaque (Granel o Cajas):',
                    ''
                ],
                'ROPA' => [
                    'Marca:',
                    'Modelo:',
                    'Material Exterior (%):',
                    'Material del Forro (%):',
                    'Material del Relleno (%):',
                    'Material del Cierre (%):',
                    'Material del Puños (%):',
                    'Talla:',
                    'Colores:',
                    'Incluye:',
                    ''
                ],
                'TECNOLOGIA' => [
                    'Material:',
                    'Marca:',
                    'Modelo:',
                    'Tamaño del producto:',
                    'Potencia:',
                    'Voltaje:',
                    'Amperaje:',
                    'Bateria',
                    'Peso Neto:',
                    'Incluye:',
                    'Pares o Piezas:',
                    'Función:',
                    ''
                ],
                'TELA' => [
                    'Material (%):',
                    'Marca:',
                    'Modelo:',
                    'Tamaño (Metros):',
                    'Gramaje (g/m²):',
                    'Tipo de Tela:',
                    'Cantidad de Rollos:',
                    'Uso:',
                    ''
                ],
                'AUTOMOTRIZ' => [
                    'Material:',
                    'Marca:',
                    'Modelo:',
                    'Tamaño:',
                    'Compatibilidad (vehiculo/moto):',
                    'Voltaje:',
                    'Potencia:',
                    'Peso Neto:',
                    'Incluye:',
                    'Pares o Piezas:',
                    'Función:',
                    ''
                ],
                'MOVILIDAD PERSONAL' => [
                    'Material:',
                    'Marca:',
                    'Modelo:',
                    'Tamaño del producto:',
                    'Tamaño de ruedas:',
                    'Distancia entre ruedas:',
                    'Voltaje:',
                    'Potencia:',
                    'Amperaje:',
                    'Autonomia:',
                    'Velocidad maxima:',
                    'Peso Neto:',
                    'Capacidad de Carga:',
                    'Tipo de Bateria:',
                    'Incluye:',
                ],
            ];

            $generated = [];
            foreach ($proveedores as $prov) {
                $items = $prov['items'] ?? [];
                if (!file_exists($templatePath)) {
                    Log::error('Plantilla de Excel de confirmación no encontrada: ' . $templatePath);
                    continue;
                }
                $spreadsheet = IOFactory::load($templatePath);
                $sheet = $spreadsheet->getActiveSheet();

                $baseStartRow = 14; // B14:L25
                $baseBlockRows = 12;    // 12 filas base

                $currentRow = $baseStartRow;
                foreach ($items as $idx => $item) {
                    $tipo = strtoupper($item['tipo_producto'] ?? 'GENERAL');
                    $labels = $labelsMap[$tipo] ?? $labelsMap['GENERAL'];
                    $rowsNeeded = max($baseBlockRows, count($labels));

                    if ($idx === 0) {
                        // Primer bloque: expandir si se requieren más de 12 filas
                        if ($rowsNeeded > $baseBlockRows) {
                            // Para MOVILIDAD PERSONAL, primero desmergear columnas B,C,D,F,G,H,I,J,K,L del bloque base
                            if ($tipo === 'MOVILIDAD PERSONAL') {
                                foreach (['B', 'C', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $colToUnmerge) {
                                    $sheet->unmergeCells("{$colToUnmerge}{$baseStartRow}:{$colToUnmerge}" . ($baseStartRow + $baseBlockRows - 1));
                                }
                            }
                            $sheet->insertNewRowBefore($baseStartRow + $baseBlockRows, $rowsNeeded - $baseBlockRows);
                            // Duplicar estilos para filas nuevas, tomando la última fila base como referencia
                            for ($r = 0; $r < ($rowsNeeded - $baseBlockRows); $r++) {
                                $srcRow = $baseStartRow + $baseBlockRows - 1;
                                $dstRow = $baseStartRow + $baseBlockRows + $r;
                                $sheet->duplicateStyle($sheet->getStyle("B{$srcRow}:L{$srcRow}"), "B{$dstRow}:L{$dstRow}");
                            }
                        }
                        $startRow = $baseStartRow;
                    } else {
                        // Bloques subsecuentes: insertar bloque completo con el tamaño requerido
                        $sheet->insertNewRowBefore($currentRow, $rowsNeeded);
                        // Copiar estilos del bloque base fila por fila (hasta 12) y extender con la última fila base
                        for ($r = 0; $r < $rowsNeeded; $r++) {
                            $srcRow = $baseStartRow + min($r, $baseBlockRows - 1);
                            $dstRow = $currentRow + $r;
                            $sheet->duplicateStyle($sheet->getStyle("B{$srcRow}:L{$srcRow}"), "B{$dstRow}:L{$dstRow}");
                        }
                        $startRow = $currentRow;
                    }


                    foreach (range('B', 'L') as $colRef) {
                        for ($rowApply = $startRow; $rowApply <= ($startRow + $rowsNeeded - 1); $rowApply++) {
                            $sheet->duplicateStyle($sheet->getStyle("{$colRef}14"), "{$colRef}{$rowApply}");
                        }
                    }

                    // A nunca debe tener bordes: limpiar bordes en el rango afectado
                    for ($rowApply = $startRow; $rowApply <= ($startRow + $rowsNeeded - 1); $rowApply++) {
                        $borders = $sheet->getStyle('A' . $rowApply)->getBorders();
                        $borders->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                        $borders->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                        $borders->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                        $borders->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                    }

                    // Merge vertical por columna para preservar forma del bloque completo (excepto E)
                    foreach (range('B', 'L') as $col) {
                        if ($col === 'E') {
                            continue; // no mergear la columna E (labels)
                        }
                        $sheet->mergeCells("{$col}{$startRow}:{$col}" . ($startRow + $rowsNeeded - 1));
                    }

                    // Formateo especial posterior a merges
                    $endRow = $startRow + $rowsNeeded - 1;
                    // Columna H: aplicar formateo del primer rango (H14) al rango mergeado actual
                    $sheet->duplicateStyle($sheet->getStyle('H14'), "H{$startRow}:H{$endRow}");

                    // Columna I: fórmula explícita G{row}*H{row}
                    $sheet->setCellValue('I' . $startRow, '=G' . $startRow . '*H' . $startRow);

                    // Columna C: initial_name (en la primera fila del bloque)
                    $initialName = $item['initial_name'] ?? '';
                    $sheet->setCellValueExplicit('C' . $startRow, $initialName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    // Ajustar estilo/texto de la columna C (usar estilo base C14 y centrar)
                    $sheet->duplicateStyle($sheet->getStyle('C14'), 'C' . $startRow . ':C' . $endRow);
                    $sheet->getStyle('C' . $startRow . ':C' . $endRow)
                        ->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    // Columna E: labels por tipo de producto
                    for ($i = 0; $i < $rowsNeeded; $i++) {
                        $sheet->setCellValueExplicit('E' . ($startRow + $i), $labels[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    // Columna F: tipo de producto para todas las filas del bloque
                    for ($i = 0; $i < $rowsNeeded; $i++) {
                        $sheet->setCellValueExplicit('F' . ($startRow + $i), $tipo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }

                    // Avanzar currentRow al final de este bloque
                    $currentRow = $startRow + $rowsNeeded;
                }
                // Agregar fila de totales debajo del último bloque
                $lastEndRow = $currentRow - 1;
                $totalRow = $currentRow;
                if ($lastEndRow >= $baseStartRow) {
                    // SUM para G y I desde la fila 14 hasta la última fila del bloque
                    $sheet->setCellValue('G' . $totalRow, '=SUM(G' . $baseStartRow . ':G' . $lastEndRow . ')');
                    $sheet->setCellValue('I' . $totalRow, '=SUM(I' . $baseStartRow . ':I' . $lastEndRow . ')');
                }
                $codeSupplier = CotizacionProveedor::where('id', $prov['id'])->first()->code_supplier;
                $fileName = 'excel_confirmacion' . '_' . $codeSupplier . '.xlsx';
                $fullPath = $outputDir . DIRECTORY_SEPARATOR . $fileName;
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($fullPath);
                $response = $this->sendMedia($fullPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Documento de confirmación', $telefono, 5);
                Log::info('Respuesta de WhatsApp: ' . json_encode($response));
            }
            $contenedor = Contenedor::where('id', $cot->id_contenedor)->first();
            $fecha_documentacion_max = $contenedor->fecha_documentacion_max;
            $fecha_documentacion_max_formatted = date('d/m/Y', strtotime($fecha_documentacion_max));
            $message = "☑ PASO 2: Solicita a tu proveedor los documentos finales:
•⁠  ⁠Commercial Invoice 📄.
•⁠  ⁠Packing List 📦.

📋 Adjuntamos un Word con indicaciones para un correcto llenado.
📩 El documento está en idioma chino, solo enviarlo a su proveedor.
🚫 Indicar a tu proveedor, que no se rellena encima del World . ESTE WORD ES SOLO UNA GUIA.

Fecha maxima de entrega: {$fecha_documentacion_max_formatted}";
            $response = $this->sendMessage($message, $telefono, 8);
            //send CONSIDERATIONS.docx on public storage templates
            $considerationsPath = public_path('storage/templates/CONSIDERATIONS.docx');
            $response = $this->sendMedia($considerationsPath, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Consideraciones', $telefono, 10);


            return response()->json([
                'success' => true,
                'message' => 'Items actualizados, mensaje enviado y archivos generados',
                'payload' => [
                    'id_cotizacion' => $idCotizacion,
                    'carga' => $cargaCode,
                    'text' => $message,
                    'files' => $generated
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en solicitarDocumentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en el procesamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function recordatoriosDocumentos(Request $request){
        try {
            $idCotizacion = $request->input('id_cotizacion');
            $proveedores = $request->input('proveedores', []);

            if (!$idCotizacion || empty($proveedores)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payload inválido: se requiere id_cotizacion y proveedores'
                ], 422);
            }

            // Obtener información de la cotización
            $cot = DB::table('contenedor_consolidado_cotizacion')
                ->select('nombre', 'telefono')
                ->where('id', $idCotizacion)
                ->first();

            if (!$cot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            $nombreCliente = $cot->nombre;
            $telefono = $cot->telefono;
            $telefono = preg_replace('/\s+/', '', $telefono);
            $telefono = $telefono ? $telefono . '@c.us' : '';

            // Mapa de documentos a nombres legibles
            $documentosMap = [
                'commercial_invoice' => 'Commercial Invoice 📄',
                'packing_list' => 'Packing List 📦.',
                'excel_confirmacion' => 'Excel de confirmacion 📄'
            ];

            // Construir el mensaje
            $mensaje = "Hola {$nombreCliente} estamos esperando nos envies los documentos de tu importación, a continuacion detallo los que faltan:\n\n";

            foreach ($proveedores as $prov) {
                $idProveedor = $prov['id'] ?? null;
                $documentos = $prov['documentos'] ?? [];

                if (!$idProveedor || empty($documentos)) {
                    continue;
                }

                // Obtener el código del proveedor
                $proveedor = CotizacionProveedor::where('id', $idProveedor)->first();
                if (!$proveedor) {
                    Log::warning('Proveedor no encontrado: ' . $idProveedor);
                    continue;
                }

                $codeSupplier = $proveedor->code_supplier ?? "Proveedor #{$idProveedor}";
                $mensaje .= "Proveedor: {$codeSupplier}\n";

                // Agregar documentos faltantes
                foreach ($documentos as $doc) {
                    $nombreDocumento = $documentosMap[$doc] ?? ucwords(str_replace('_', ' ', $doc));
                    $mensaje .= "{$nombreDocumento}\n";
                }
                $mensaje .= "\n";
            }

            $mensaje .= "Si no tenemos tus documentos a tiempo aduana puede aplicarte multas o inmovilización de tus productos.";

            // Enviar mensaje por WhatsApp
            $response = $this->sendMessage($mensaje, $telefono, 5);
            Log::info('Respuesta de WhatsApp recordatorios: ' . json_encode($response));

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio enviado correctamente',
                'payload' => [
                    'id_cotizacion' => $idCotizacion,
                    'nombre_cliente' => $nombreCliente,
                    'text' => $mensaje
                ]
            ]);
        }catch (\Exception $e) {
            Log::error('Error en recordatoriosDocumentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en el procesamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
