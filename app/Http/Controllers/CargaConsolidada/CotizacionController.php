<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use App\Traits\UserGroupsTrait;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Contenedor;
use App\Services\CargaConsolidada\CotizacionService;
use App\Services\CargaConsolidada\CotizacionExportService;
use App\Models\Usuario;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;


class CotizacionController extends Controller
{
    protected $cotizacionService;
    protected $cotizacionExportService;

    public function __construct(
        CotizacionService $cotizacionService,
        CotizacionExportService $cotizacionExportService
    ) {
        $this->cotizacionService = $cotizacionService;
        $this->cotizacionExportService = $cotizacionExportService;
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
     * Apply currency formatting directly to the 'value' of CBM and logística totals.
     */
    private function applyCurrencyToHeaders(array $headers)
    {
        foreach ($headers as $key => $item) {
            if (!is_array($item) || !array_key_exists('value', $item)) {
                continue;
            }
            if (in_array($key, ['total_logistica', 'total_logistica_pagado'])) {
                $headers[$key]['value'] = $this->formatCurrency($item['value']);
            }
        }
        return $headers;
    }
    public function index(Request $request, $idContenedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $query = Cotizacion::where('id_contenedor', $idContenedor);
            $rol = $user->getNombreGrupo();
            // Aplicar filtros básicos
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('documento', 'LIKE', "%{$search}%")
                        ->orWhere('telefono', 'LIKE', "%{$search}%");
                });
            }

            // Filtrar por estado si se proporciona
            if ($request->has('estado') && !empty($request->estado)) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->has('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }
            //if request has estado_coordinacion or estado_china  then query with  proveedores  and just get cotizaciones with at least one proveedor with the state
            if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
                $query->whereHas('proveedores', function ($query) use ($request) {
                    $query->where('estados', $request->estado_coordinacion)
                        ->orWhere('estados_proveedor', $request->estado_china);
                });
            }
            // Aplicar filtros según el rol del usuario
            switch ($rol) {
                case Usuario::ROL_COTIZADOR:
                    if ($user->getIdUsuario() != 28791) {
                        $query->where('id_usuario', $user->getIdUsuario());
                    }

                    break;

                case Usuario::ROL_DOCUMENTACION:
                    $query->where('estado_cotizador', 'CONFIRMADO');
                    break;

                case Usuario::ROL_COORDINACION:
                    $query->where('estado_cotizador', 'CONFIRMADO');
                    break;
            }
            $query->whereNull('id_cliente_importacion');
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('limit', 100);
            $page = $request->input('page', 1);
            $results = $query->paginate($perPage, ['*'], 'page', $page);

            $userId = auth()->id();

            $files = DB::table('carga_consolidada_contenedor')
                ->where('id', $idContenedor)
                ->select('bl_file_url', 'lista_embarque_url')
                ->first();

            if (!$files) {
                $files = (object) [
                    'bl_file_url' => null,
                    'lista_embarque_url' => null,
                ];
            }

            // Transformar los datos para la respuesta
            $data = $results->map(function ($cotizacion) use ($files) {
                return [
                    'id' => $cotizacion->id,
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'telefono' => $cotizacion->telefono,
                    'correo' => $cotizacion->correo,
                    'fecha' => $cotizacion->fecha,
                    'estado' => $cotizacion->estado,
                    'estado_cliente' => $cotizacion->name,
                    'estado_cotizador' => $cotizacion->estado_cotizador,
                    'monto' => $cotizacion->monto,
                    'monto_final' => $cotizacion->monto_final,
                    'volumen' => $cotizacion->volumen,
                    'volumen_final' => $cotizacion->volumen_final,
                    'tarifa' => $cotizacion->tarifa,
                    'qty_item' => $cotizacion->qty_item,
                    'fob' => $cotizacion->fob,
                    'cotizacion_file_url' => $cotizacion->cotizacion_file_url ? $this->generateImageUrl($cotizacion->cotizacion_file_url) : null,
                    'impuestos' => $cotizacion->impuestos,
                    'tipo_cliente' => $cotizacion->tipoCliente->name,
                    'bl_file_url' => $files->bl_file_url ? $files->bl_file_url : null,
                    'lista_embarque_url' => $files->lista_embarque_url ? $files->lista_embarque_url : null,
                ];
            });


            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage()
                ],
                'lista_embarque_url' => $files->lista_embarque_url,
                'bl_file_url' => $files->bl_file_url,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en index de cotizaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones: ' . $e->getMessage()
            ], 500);
        }
    }
    private function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }

        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }

        // Limpiar la ruta de barras iniciales para evitar doble slash
        $ruta = ltrim($ruta, '/');

        // Construir URL manualmente para evitar problemas con Storage::url()
        $baseUrl = config('app.url');
        $storagePath = '/storage/';

        // Asegurar que no haya doble slash
        $baseUrl = rtrim($baseUrl, '/');
        $storagePath = ltrim($storagePath, '/');
        $ruta = ltrim($ruta, '/');

        return $baseUrl . '/'  . $ruta;
    }
    public function getHeadersData($idContenedor)
    {
        $userId = auth()->id();
        $user = JWTAuth::parseToken()->authenticate();
        $usergroup = $user->getNombreGrupo();

        $headers = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
            ->join('contenedor_consolidado_cotizacion as cc', 'cccp.id_cotizacion', '=', 'cc.id')
            ->where('cccp.id_contenedor', $idContenedor)
            ->select([
                DB::raw('COALESCE(SUM(IF(cc.estado_cotizador = "CONFIRMADO", cccp.cbm_total_china, 0)), 0) as cbm_total_china'),
                DB::raw('(
                    SELECT COALESCE(SUM(volumen), 0)
                    FROM contenedor_consolidado_cotizacion
                    WHERE id IN (
                        SELECT DISTINCT id_cotizacion
                        FROM contenedor_consolidado_cotizacion_proveedores
                        WHERE id_contenedor = ' . $idContenedor . '
                    )
                    AND estado_cotizador = "CONFIRMADO"
                ) as cbm_total_peru'),
                DB::raw('(
                    SELECT COALESCE(SUM(volumen), 0)
                    FROM contenedor_consolidado_cotizacion
                    WHERE id_contenedor = ' . $idContenedor . '
                    AND estado_cotizador = "CONFIRMADO"
                    AND id_usuario = ' . $userId . '
                ) as cbm_vendido'),
                DB::raw('(
                    SELECT COALESCE(SUM(volumen), 0)
                    FROM contenedor_consolidado_cotizacion
                    WHERE id_contenedor = ' . $idContenedor . '
                    AND estado_cotizador != "CONFIRMADO"
                    AND id_usuario = ' . $userId . '
                ) as cbm_pendiente'),
                DB::raw('(
                    SELECT COALESCE(SUM(cccp.cbm_total_china), 0)
                    FROM contenedor_consolidado_cotizacion_proveedores cccp
                    JOIN contenedor_consolidado_cotizacion cc ON cccp.id_cotizacion = cc.id
                    WHERE cccp.id_contenedor = ' . $idContenedor . '
                    AND cccp.estados_proveedor = "LOADED"
                    AND cc.id_usuario = ' . $userId . '
                ) as cbm_embarcado'),
                DB::raw('(
                    SELECT COALESCE(SUM(monto), 0)
                    FROM contenedor_consolidado_cotizacion
                    WHERE id IN (
                        SELECT DISTINCT id_cotizacion
                        FROM contenedor_consolidado_cotizacion_proveedores
                        WHERE id_contenedor = ' . $idContenedor . '
                    )
                    AND estado_cotizador = "CONFIRMADO"
                ) as total_logistica'),
                DB::raw('(
                    SELECT COALESCE(SUM(qty_item), 0)
                    FROM contenedor_consolidado_cotizacion
                    WHERE id IN (
                        SELECT DISTINCT id_cotizacion
                        FROM contenedor_consolidado_cotizacion_proveedores
                        WHERE id_contenedor = ' . $idContenedor . '
                    )
                    AND estado_cotizador = "CONFIRMADO"
                ) as total_qty_items'),
                DB::raw('(
                    SELECT COALESCE(SUM(monto), 0)
                    FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                    JOIN cotizacion_coordinacion_pagos_concept pc ON cccp.id_concept = pc.id
                    WHERE cccp.id_contenedor = ' . $idContenedor . '
                    AND pc.name = "LOGISTICA"
                ) as total_logistica_pagado'),
                DB::raw('(
                    SELECT lista_embarque_url
                    FROM carga_consolidada_contenedor
                    WHERE id = ' . $idContenedor . '
                ) as lista_embarque_url')
            ])
            ->first();
        // Preparar los headers
        $headersData = [
            'cbm_total_china' => [
                'value' => $headers ? $headers->cbm_total_china : 0,
                'label' => 'CBM Total ',
                'icon' => 'https://upload.wikimedia.org/wikipedia/commons/f/fa/Flag_of_the_People%27s_Republic_of_China.svg'
            ],
            'cbm_total_peru' => [
                'value' => $headers ? $headers->cbm_total_peru : 0,
                'label' => 'CBM Total ',
                'icon' => 'https://upload.wikimedia.org/wikipedia/commons/c/cf/Flag_of_Peru.svg'
            ],
            'cbm_vendido' => [
                'value' => $headers ? $headers->cbm_vendido : 0,
                'label' => 'CBM Vendido',
                'icon' => 'mage:box-3d'
            ],
            'cbm_pendiente' => [
                'value' => $headers ? $headers->cbm_pendiente : 0,
                'label' => 'CBM Pendiente',
                'icon' => 'mage:box-3d'
            ],
            'cbm_embarcado' => [
                'value' => $headers ? $headers->cbm_embarcado : 0,
                'label' => 'CBM Embarcado',
                'icon' => 'mage:box-3d'
            ],
            'total_logistica_pagado' => [
                'value' => $headers ? $headers->total_logistica_pagado : 0,
                'label' => 'Total Logistica Pagado',
                'icon' => 'cryptocurrency-color:soc'
            ],
            'total_logistica' => [
                'value' => $headers ? $headers->total_logistica : 0,
                'label' => 'Total Logistica',
                'icon' => 'cryptocurrency-color:soc'
            ],
            'qty_items' => [
                'value' => $headers ? $headers->total_qty_items : 0,
                'label' => 'Cantidad de Items',
                'icon' => 'bi:boxes'
            ],



        ];
        $roleAllowedMap = [
            Usuario::ROL_COTIZADOR => ['cbm_vendido', 'cbm_pendiente', 'cbm_embarcado', 'qty_items', 'cbm_total_peru', 'cbm_total_china'],
            Usuario::ROL_ALMACEN_CHINA => ['cbm_total_china', 'cbm_total_peru', 'qty_items'],
            Usuario::ROL_ADMINISTRACION => ['cbm_total_china', 'cbm_total_peru', 'qty_items', 'total_logistica', 'total_logistica_pagado'],
            Usuario::ROL_COORDINACION => ['cbm_total_china', 'cbm_total_peru', 'qty_items', 'total_logistica', 'total_logistica_pagado']
            //por defecto:todos
        ];
        $userIdCheck = $user->ID_Usuario;
        if (array_key_exists($usergroup, $roleAllowedMap)) {
            $allowedKeys = $roleAllowedMap[$usergroup];
            $headersData = array_filter($headersData, function ($key) use ($allowedKeys) {
                return in_array($key, $allowedKeys);
            }, ARRAY_FILTER_USE_KEY);
        } else {
            // Si el rol no está en el mapa, devolver todos los headers
            return $headersData;
        }
        if ($userIdCheck == "28791") {
            // CBM Vendido por usuario (estado CONFIRMADO)
            $vendidoRows = DB::table('contenedor_consolidado_cotizacion as c')
                ->leftJoin('usuario as u', 'u.ID_Usuario', '=', 'c.id_usuario')
                ->where('c.id_contenedor', $idContenedor)
                ->where('c.estado_cotizador', 'CONFIRMADO')
                ->select(
                    DB::raw('u.No_Nombres_Apellidos as nombre'),
                    DB::raw('COALESCE(SUM(c.volumen),0) as cbm_vendido'),
                    (DB::raw('SUM(c.volumen) as cbm_vendido_total'))
                )
                ->groupBy('u.No_Nombres_Apellidos')
                ->get();
            $vendidoMap = [];
            foreach ($vendidoRows as $r) {
                $nombre = is_string($r->nombre) ? trim($r->nombre) : '';
                if ($nombre === '' || $nombre === null) {
                    continue; // skip empty/null names
                }
                $vendidoMap[$nombre] = (float) $r->cbm_vendido;
            }

            // CBM Pendiente por usuario (estado != CONFIRMADO)
            $pendienteRows = DB::table('contenedor_consolidado_cotizacion as c')
                ->leftJoin('usuario as u', 'u.ID_Usuario', '=', 'c.id_usuario')
                ->where('c.id_contenedor', $idContenedor)
                ->where('c.estado_cotizador', '!=', 'CONFIRMADO')
                ->select(DB::raw('u.No_Nombres_Apellidos as nombre'), DB::raw('COALESCE(SUM(c.volumen),0) as cbm_pendiente'))
                ->groupBy('u.No_Nombres_Apellidos')
                ->get();
            $pendienteMap = [];
            foreach ($pendienteRows as $r) {
                $nombre = is_string($r->nombre) ? trim($r->nombre) : '';
                if ($nombre === '' || $nombre === null) {
                    continue; // skip empty/null names
                }
                $pendienteMap[$nombre] = (float) $r->cbm_pendiente;
            }

            // CBM Embarcado por usuario (proveedores LOADED)
            $embarcadoRows = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
                ->join('contenedor_consolidado_cotizacion as cc', 'cc.id', '=', 'cccp.id_cotizacion')
                ->leftJoin('usuario as u', 'u.ID_Usuario', '=', 'cc.id_usuario')
                ->where('cccp.id_contenedor', $idContenedor)
                ->where('cccp.estados_proveedor', 'LOADED')
                ->select(DB::raw('u.No_Nombres_Apellidos as nombre'), DB::raw('COALESCE(SUM(cccp.cbm_total_china),0) as cbm_embarcado'))
                ->groupBy('u.No_Nombres_Apellidos')
                ->get();
            $embarcadoMap = [];
            foreach ($embarcadoRows as $r) {
                $nombre = is_string($r->nombre) ? trim($r->nombre) : '';
                if ($nombre === '' || $nombre === null) {
                    continue; // skip empty/null names
                }
                $embarcadoMap[$nombre] = (float) $r->cbm_embarcado;
            }
            // Formatear valores por usuario como moneda (dólares) para mostrar
            $vendidoMapFormatted = [];
            foreach ($vendidoMap as $nombre => $valor) {
                $vendidoMapFormatted[$nombre] = number_format((float)$valor, 2, '.', '');
            }
            $pendienteMapFormatted = [];
            foreach ($pendienteMap as $nombre => $valor) {
                $pendienteMapFormatted[$nombre] = number_format((float)$valor, 2, '.', '');
            }
            $embarcadoMapFormatted = [];
            foreach ($embarcadoMap as $nombre => $valor) {
                $embarcadoMapFormatted[$nombre] = number_format((float)$valor, 2, '.', '');
            }
            // Adjuntar el desglose por usuario dentro de los mismos headersData
            if (isset($headersData['cbm_vendido'])) {
                $headersData['cbm_vendido']['por_usuario'] = $vendidoMapFormatted;
                $headersData['cbm_vendido']['value'] = number_format(array_sum($vendidoMap), 2, '.', '');
            } else {
                $headersData['cbm_vendido'] = [
                    'value' => number_format(array_sum($vendidoMap), 2, '.', ''),
                    'label' => 'CBM Vendido',
                    'icon' => 'i-heroicons-currency-dollar',
                    'por_usuario' => $vendidoMapFormatted
                ];
            }
            if (isset($headersData['cbm_pendiente'])) {
                $headersData['cbm_pendiente']['por_usuario'] = $pendienteMapFormatted;
                $headersData['cbm_pendiente']['value'] = number_format(array_sum($pendienteMap), 2, '.', '');
            } else {
                $headersData['cbm_pendiente'] = [
                    'value' => number_format(array_sum($pendienteMap), 2, '.', ''),
                    'label' => 'CBM Pendiente',
                    'icon' => 'i-heroicons-currency-dollar',
                    'por_usuario' => $pendienteMapFormatted
                ];
            }
            if (isset($headersData['cbm_embarcado'])) {
                $headersData['cbm_embarcado']['por_usuario'] = $embarcadoMapFormatted;
                $headersData['cbm_embarcado']['value'] = number_format(array_sum($embarcadoMap), 2, '.', '');
            } else {
                $headersData['cbm_embarcado'] = [
                    'value' => number_format(array_sum($embarcadoMap), 2, '.', ''),
                    'label' => 'CBM Embarcado',
                    'icon' => 'i-heroicons-currency-dollar',
                    'por_usuario' => $embarcadoMapFormatted
                ];
            }

            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }
            // Format values as currency where applicable before returning
            $headersData = $this->applyCurrencyToHeaders($headersData);
            return response()->json([
                'success' => true,
                'data' => $headersData,
                'carga' => $contenedor->carga,
                'lista_embarque_url' => $contenedor->lista_embarque_url
            ]);
        }

        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            return response()->json([
                'success' => false,
                'message' => 'Contenedor no encontrado'
            ], 404);
        }
        // Format values as currency where applicable before returning
        $headersData = $this->applyCurrencyToHeaders($headersData);
        return response()->json([
            'success' => true,
            'data' => $headersData,
            'carga' => $contenedor->carga,
            'lista_embarque_url' => $contenedor->lista_embarque_url
        ]);
    }
    public function store(Request $request)
    {
        try {
            // Validar los datos requeridos
            if (!$request->has('id_contenedor')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El ID del contenedor es requerido'
                ], 400);
            }

            // Validar que el contenedor existe
            $contenedor = Contenedor::find($request->id_contenedor);
            if (!$contenedor) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El contenedor especificado no existe'
                ], 404);
            }

            // Validar el archivo subido
            if (!$request->hasFile('cotizacion')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se ha proporcionado ningún archivo'
                ], 400);
            }

            $file = $request->file('cotizacion');

            // Crear un directorio temporal si no existe
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            // Mover el archivo a nuestro directorio temporal
            $tempFileName = uniqid('cotizacion_') . '.' . $file->getClientOriginalExtension();
            $tempFilePath = $tempPath . '/' . $tempFileName;

            // Copiar el archivo al directorio temporal
            copy($file->getRealPath(), $tempFilePath);

            Log::info('Archivo temporal creado:', [
                'original_path' => $file->getRealPath(),
                'temp_path' => $tempFilePath,
                'exists' => file_exists($tempFilePath)
            ]);

            $cotizacion = [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $tempFilePath,
                'error' => 0,
                'size' => $file->getSize()
            ];

            // Iniciar transacción de base de datos para todo el flujo
            DB::beginTransaction();

            try {
                // Subir archivo usando el sistema de almacenamiento de Laravel
                $fileName = time() . '_' . $file->getClientOriginalName();
                $fileUrl = $file->storeAs('public/agentecompra', $fileName);

                if (!$fileUrl) {
                    Log::error('Error al subir archivo usando Laravel Storage');
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'success' => false,
                        'message' => 'Error al subir el archivo'
                    ], 500);
                }

                // Convertir la ruta de storage a URL pública
                $fileUrl = Storage::url($fileUrl);
                Log::info('Cotizacion: ' . json_encode($cotizacion));
                Log::info('Data: ' . $fileUrl);

                $dataToInsert = $this->getCotizacionData($cotizacion);

                // Verificar si getCotizacionData devolvió un error
                if (!is_array($dataToInsert)) {
                    DB::rollBack();
                    Storage::delete($fileUrl); // Limpiar el archivo si hay error
                    return response()->json([
                        'status' => 'error',
                        'success' => false,
                        'message' => 'Error al procesar el archivo: ' . $dataToInsert
                    ], 500);
                }

                $dataToInsert['cotizacion_file_url'] = $fileUrl;
                $dataToInsert['id_contenedor'] = $request->id_contenedor;
                $dataToInsert['id_usuario'] = Auth::id();

                // Crear la cotización
                $cotizacionModel = Cotizacion::create($dataToInsert);

                if (!$cotizacionModel) {
                    DB::rollBack();
                    Storage::delete($fileUrl);
                    return response()->json([
                        'status' => 'error',
                        'success' => false,
                        'message' => 'No se pudo crear la cotización'
                    ], 500);
                }

                $idCotizacion = $cotizacionModel->id;
                $dataToInsert['id_cotizacion'] = $idCotizacion;

                // Obtener datos de proveedores
                $dataEmbarque = $this->getEmbarqueData($cotizacion, $dataToInsert);

                // Insertar proveedores en lote si existen
                if (!empty($dataEmbarque)) {
                    try {
                        CotizacionProveedor::insert($dataEmbarque);
                        Log::info('Proveedores insertados correctamente: ' . count($dataEmbarque));
                    } catch (\Exception $e) {
                        Log::error('Error al insertar proveedores: ' . $e->getMessage());
                        DB::rollBack();
                        Storage::delete($fileUrl);
                        return response()->json([
                            'status' => 'error',
                            'success' => false,
                            'message' => 'Error al insertar proveedores: ' . $e->getMessage()
                        ], 500);
                    }
                }

                $nombre = $dataToInsert['nombre'];

                // Ya tenemos el contenedor validado desde el inicio
                $f_cierre = $contenedor->f_cierre;

                $message = 'Hola ' . $nombre . ' pudiste revisar la cotización enviada? 
                Te comento que cerramos nuestro consolidado este ' . $f_cierre . ' Por favor si cuentas con alguna duda me avisas y puedo llamarte para aclarar tus dudas.';

                $telefono = preg_replace('/\s+/', '', $dataToInsert['telefono']);
                $telefono = $telefono ? $telefono . '@c.us' : '';

                $data_json = [
                    'message' => $message,
                    'phoneNumberId' => $telefono,
                ];

                // Aquí podrías agregar la lógica para guardar en la tabla de crons si es necesario

                // Si todo salió bien, confirmar la transacción
                DB::commit();

                // Crear notificación para Coordinación
                $this->crearNotificacionCoordinacion($cotizacionModel, $contenedor);

                // Limpiar el archivo temporal
                if (file_exists($cotizacion['tmp_name'])) {
                    unlink($cotizacion['tmp_name']);
                }

                return response()->json([
                    'id' => $idCotizacion,
                    'status' => 'success',
                    'success' => true,
                    'message' => 'Cotización creada exitosamente'
                ]);
            } catch (\Exception $e) {
                // En caso de cualquier error, hacer rollback y limpiar archivos
                DB::rollBack();

                if (isset($fileUrl)) {
                    Storage::delete($fileUrl);
                }

                // Limpiar archivo temporal
                if (file_exists($cotizacion['tmp_name'])) {
                    unlink($cotizacion['tmp_name']);
                }

                Log::error('Error en store de cotizaciones: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Error al procesar la cotización: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en store de cotizaciones: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion show']);
    }

    public function update(Request $request, $id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion update']);
    }

    public function destroy($id)
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            $cotizacionProveedor = CotizacionProveedor::where('id_cotizacion', $id);
            $cotizacionProveedor->delete();
            //delete cotizacion
            $cotizacion = Cotizacion::find($id);
            $cotizacion->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            return response()->json(['message' => 'Cotizacion borrada correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al borrar cotizacion: ' . $e->getMessage()], 500);
        }
    }

    public function filterOptions()
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion filter options']);
    }

    /**
     * Obtener documentación de clientes para una cotización específica
     * Replica la funcionalidad del método showClientesDocumentacion de CodeIgniter
     */
    public function showClientesDocumentacion($id)
    {
        try {
            // Obtener la cotización principal con todas sus relaciones
            $cotizacion = Cotizacion::with([
                'documentacion',
                'proveedores',
                'documentacionAlmacen',
                'inspeccionAlmacen'
            ])
                ->where('id', $id)
                ->whereNotNull('estado')
                ->first();

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            if ($cotizacion->proveedores->count() > 0) {
                $firstProvider = $cotizacion->proveedores->first();
                Log::info('Primer proveedor:', [
                    'id' => $firstProvider->id,
                    'code_supplier' => $firstProvider->code_supplier,
                    'volumen_doc' => $firstProvider->volumen_doc,
                    'valor_doc' => $firstProvider->valor_doc,
                    'id_cotizacion' => $firstProvider->id_cotizacion
                ]);
            }

            // Transformar los datos para mantener la estructura original
            $files = $cotizacion->documentacion->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_url,
                    'folder_name' => $file->name,
                    'id_proveedor' => $file->id_proveedor
                ];
            });

            $filesAlmacenDocumentacion = $cotizacion->documentacionAlmacen->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_path,
                    'folder_name' => $file->file_name,
                    'file_name' => $file->file_name,
                    'id_proveedor' => $file->id_proveedor,
                    'file_ext' => $file->file_ext
                ];
            });

            $providers = $cotizacion->proveedores->map(function ($provider) {
                return [
                    'code_supplier' => $provider->code_supplier,
                    'id' => $provider->id,
                    'volumen_doc' => $provider->volumen_doc ? (float) $provider->volumen_doc : null,
                    'valor_doc' => $provider->valor_doc ? (float) $provider->valor_doc : null,
                    'factura_comercial' => $provider->factura_comercial,
                    'excel_confirmacion' => $provider->excel_confirmacion,
                    'packing_list' => $provider->packing_list
                ];
            });

            $filesAlmacenInspection = $cotizacion->inspeccionAlmacen->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_path,
                    'file_name' => $file->file_name,
                    'id_proveedor' => $file->id_proveedor,
                    'file_ext' => $file->file_type
                ];
            });

            // Debug: Verificar datos transformados
            Log::info('Files transformados:', ['count' => $files->count()]);
            Log::info('Providers transformados:', ['count' => $providers->count()]);
            if ($providers->count() > 0) {
                Log::info('Primer provider transformado:', $providers->first());
            }

            // Construir la respuesta similar a la original
            $result = [
                'id' => $cotizacion->id,
                'id_contenedor' => $cotizacion->id_contenedor,
                'id_tipo_cliente' => $cotizacion->id_tipo_cliente,
                'id_cliente' => $cotizacion->id_cliente,
                'fecha' => $cotizacion->fecha,
                'nombre' => $cotizacion->nombre,
                'documento' => $cotizacion->documento,
                'correo' => $cotizacion->correo,
                'telefono' => $cotizacion->telefono,
                'volumen' => $cotizacion->volumen,
                'cotizacion_file_url' => $cotizacion->cotizacion_file_url,
                'cotizacion_final_file_url' => $cotizacion->cotizacion_final_file_url,
                'estado' => $cotizacion->estado,
                'volumen_doc' => $cotizacion->volumen_doc,
                'valor_doc' => $cotizacion->valor_doc,
                'valor_cot' => $cotizacion->valor_cot,
                'volumen_china' => $cotizacion->volumen_china,
                'factura_comercial' => $cotizacion->factura_comercial,
                'id_usuario' => $cotizacion->id_usuario,
                'monto' => $cotizacion->monto,
                'fob' => $cotizacion->fob,
                'impuestos' => $cotizacion->impuestos,
                'tarifa' => $cotizacion->tarifa,
                'excel_comercial' => $cotizacion->excel_comercial,
                'excel_confirmacion' => $cotizacion->excel_confirmacion,
                'vol_selected' => $cotizacion->vol_selected,
                'estado_cliente' => $cotizacion->estado_cliente,
                'peso' => $cotizacion->peso,
                'tarifa_final' => $cotizacion->tarifa_final,
                'monto_final' => $cotizacion->monto_final,
                'volumen_final' => $cotizacion->volumen_final,
                'guia_remision_url' => $cotizacion->guia_remision_url,
                'factura_general_url' => $cotizacion->factura_general_url,
                'cotizacion_final_url' => $cotizacion->cotizacion_final_url,
                'estado_cotizador' => $cotizacion->estado_cotizador,
                'fecha_confirmacion' => $cotizacion->fecha_confirmacion,
                'estado_pagos_coordinacion' => $cotizacion->estado_pagos_coordinacion,
                'estado_cotizacion_final' => $cotizacion->estado_cotizacion_final,
                'impuestos_final' => $cotizacion->impuestos_final,
                'fob_final' => $cotizacion->fob_final,
                'note_administracion' => $cotizacion->note_administracion,
                'status_cliente_doc' => $cotizacion->status_cliente_doc,
                'logistica_final' => $cotizacion->logistica_final,
                'qty_item' => $cotizacion->qty_item,
                'id_cliente_importacion' => $cotizacion->id_cliente_importacion,
                'files' => $files,
                'files_almacen_documentacion' => $filesAlmacenDocumentacion,
                'providers' => $providers,
                'files_almacen_inspection' => $filesAlmacenInspection
            ];

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Documentación de clientes obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener documentación de clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentación de clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrae datos de una cotización desde un archivo Excel
     * @param array $cotizacion Array con información del archivo subido
     * @return array|string Datos extraídos o mensaje de error
     */
    public function getCotizacionData($cotizacion)
    {
        try {
            if (!file_exists($cotizacion['tmp_name'])) {
                Log::error('Archivo no encontrado en: ' . $cotizacion['tmp_name']);
                return 'Archivo no encontrado: ' . $cotizacion['tmp_name'];
            }

            Log::info('Intentando cargar archivo desde: ' . $cotizacion['tmp_name']);

            try {
                $objPHPExcel = IOFactory::load($cotizacion['tmp_name']);
            } catch (\Exception $e) {
                Log::error('Error al cargar archivo Excel: ' . $e->getMessage());
                return 'Error al cargar archivo Excel: ' . $e->getMessage();
            }

            $sheet = $objPHPExcel->getSheet(0);
            $nombre = $sheet->getCell('B8')->getValue();
            $documento = $sheet->getCell('B9')->getValue();
            $correo = $sheet->getCell('B10')->getValue();
            $telefono = $sheet->getCell('B11')->getValue();
            $volumen = $sheet->getCell('I11')->getCalculatedValue();
            $valorCot = $sheet->getCell('J14')->getCalculatedValue();

            //get calculated value from cell e9
            $fecha = $sheet->getCell('E9')->getValue();
            if ($fecha == "=+TODAY()") {
                $fecha = date("Y-m-d");
            } else {
                $fecha = $this->convertDateFormat($fecha);
            }

            $tipoCliente = $sheet->getCell('E11')->getValue();

            //find if exists in table contenedor_consolidado_tipo_cliente with name = $tipoCliente else create new and get id
            $tipoClienteModel = TipoCliente::where('name', $tipoCliente)->first();
            if (!$tipoClienteModel) {
                $tipoClienteModel = TipoCliente::create(['name' => $tipoCliente]);
            }
            $idTipoCliente = $tipoClienteModel->id;

            if (trim($sheet->getCell('A23')->getValue()) == "ANTIDUMPING") {
                $monto = $sheet->getCell('J31')->getOldCalculatedValue();
                $fob = $sheet->getCell('J30')->getOldCalculatedValue();
                $impuestos = $sheet->getCell('J32')->getOldCalculatedValue();

                //get j24 and j26
                Log::error('20: ' . $sheet->getCell('J20')->getOldCalculatedValue());
                Log::error('21: ' . $sheet->getCell('J21')->getOldCalculatedValue());
                Log::error('22: ' . $sheet->getCell('J22')->getOldCalculatedValue());
                Log::error('23: ' . $sheet->getCell('J23')->getOldCalculatedValue());
                Log::error('24: ' . $sheet->getCell('J24')->getOldCalculatedValue());
                Log::error('26: ' . $sheet->getCell('J26')->getOldCalculatedValue());
                Log::error('impuestos: ' . $impuestos);
            } else {
                $monto = $sheet->getCell('J30')->getOldCalculatedValue();
                $fob = $sheet->getCell('J29')->getOldCalculatedValue();
                $impuestos = $sheet->getCell('J31')->getOldCalculatedValue();
            }

            $tarifa = $monto / (($volumen <= 0 ? 1 : $volumen) < 1.00 ? 1 : ($volumen <= 0 ? 1 : $volumen));
            $peso = $sheet->getCell('I9')->getOldCalculatedValue();
            $highestRow = $sheet->getHighestRow();
            $qtyItem = 0;

            for ($row = 36; $row <= $highestRow; $row++) {
                $cellValue = $sheet->getCell('A' . $row)->getValue();
                if (is_numeric($cellValue) && $cellValue > 0) {
                    $qtyItem++;
                }
            }

            $data = [
                'nombre' => $nombre,
                'documento' => $documento,
                'correo' => $correo,
                'telefono' => $telefono,
                'volumen' => $volumen,
                'id_tipo_cliente' => $idTipoCliente,
                'fecha' => $fecha,
                'valor_cot' => $valorCot,
                'monto' => $monto,
                'tarifa' => $tarifa,
                'peso' => $peso,
                'fob' => $fob,
                'impuestos' => $impuestos,
                'qty_item' => $qtyItem
            ];
            Log::error('Data: ' . json_encode($data));
            return $data;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Incrementa una columna de Excel (ej: A -> B, Z -> AA)
     * @param string $column Columna actual
     * @param int $increment Cantidad a incrementar
     * @return string Nueva columna
     */
    public function incrementColumn($column, $increment = 1)
    {
        $column = strtoupper($column); // Asegurarse de que todas las letras sean mayúsculas
        $length = strlen($column);
        $number = 0;

        // Convertir la columna a un número
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($column[$i]) - ord('A') + 1);
        }

        // Incrementar el número
        $number += $increment;

        // Convertir el número de vuelta a una columna
        $newColumn = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $newColumn = chr(ord('A') + $remainder) . $newColumn;
            $number = intval(($number - 1) / 26);
        }

        return $newColumn;
    }

    /**
     * Convierte el formato de fecha del Excel a formato Y-m-d
     * @param mixed $fecha Fecha del Excel
     * @return string Fecha en formato Y-m-d
     */
    private function convertDateFormat($fecha)
    {
        if (is_numeric($fecha)) {
            // Si es un número de serie de Excel, convertirlo a fecha
            $timestamp = ($fecha - 25569) * 86400;
            return date('Y-m-d', $timestamp);
        }

        // Si es una cadena, intentar parsearla
        $parsedDate = date_parse($fecha);
        if ($parsedDate['error_count'] === 0) {
            return date('Y-m-d', strtotime($fecha));
        }

        // Si no se puede parsear, devolver la fecha actual
        return date('Y-m-d');
    }

    /**
     * Lee la plantilla de cotización inicial desde assets/templates
     * @return array|string Datos de la plantilla o mensaje de error
     */
    public function leerPlantillaCotizacionInicial()
    {
        try {
            $plantillaPath = public_path('assets/templates/PLANTILLA_COTIZACION_INICIAL.xlsm');

            if (!file_exists($plantillaPath)) {
                Log::error('Plantilla de cotización inicial no encontrada en: ' . $plantillaPath);
                return 'Plantilla de cotización inicial no encontrada: ' . $plantillaPath;
            }

            Log::info('Leyendo plantilla de cotización inicial desde: ' . $plantillaPath);

            try {
                $objPHPExcel = IOFactory::load($plantillaPath);
            } catch (\Exception $e) {
                Log::error('Error al cargar plantilla Excel: ' . $e->getMessage());
                return 'Error al cargar plantilla Excel: ' . $e->getMessage();
            }

            $sheet = $objPHPExcel->getSheet(0);

            // Leer datos básicos de la plantilla
            $datosPlantilla = [
                'nombre_plantilla' => $sheet->getCell('A1')->getValue() ?? 'PLANTILLA_COTIZACION_INICIAL',
                'fecha_plantilla' => $sheet->getCell('E9')->getValue() ?? date('Y-m-d'),
                'tipo_cliente_default' => $sheet->getCell('E11')->getValue() ?? '',
                'volumen_default' => $sheet->getCell('I11')->getCalculatedValue() ?? 0,
                'peso_default' => $sheet->getCell('I9')->getOldCalculatedValue() ?? 0,
                'valor_cot_default' => $sheet->getCell('J14')->getCalculatedValue() ?? 0,
                'monto_default' => $sheet->getCell('J30')->getOldCalculatedValue() ?? 0,
                'fob_default' => $sheet->getCell('J29')->getOldCalculatedValue() ?? 0,
                'impuestos_default' => $sheet->getCell('J31')->getOldCalculatedValue() ?? 0,
                'tarifa_default' => 0,
                'qty_item_default' => 0
            ];

            // Calcular tarifa por defecto
            if ($datosPlantilla['volumen_default'] > 0) {
                $datosPlantilla['tarifa_default'] = $datosPlantilla['monto_default'] / $datosPlantilla['volumen_default'];
            }

            // Contar items por defecto
            $highestRow = $sheet->getHighestRow();
            for ($row = 36; $row <= $highestRow; $row++) {
                $cellValue = $sheet->getCell('A' . $row)->getValue();
                if (is_numeric($cellValue) && $cellValue > 0) {
                    $datosPlantilla['qty_item_default']++;
                }
            }

            Log::info('Plantilla leída exitosamente: ' . json_encode($datosPlantilla));
            return $datosPlantilla;
        } catch (Exception $e) {
            Log::error('Error al leer plantilla de cotización inicial: ' . $e->getMessage());
            return 'Error al leer plantilla: ' . $e->getMessage();
        }
    }

    // Propiedades de la clase
    protected $defaultHoursContactado = 60; // 1 hora por defecto

    /**
     * Almacena una nueva cotización
     */
    public function storeCotizacion($data, $cotizacion)
    {
        try {
            $fileUrl = $this->uploadSingleFile([
                "name" => $cotizacion['name'],
                "type" => $cotizacion['type'],
                "tmp_name" => $cotizacion['tmp_name'],
                "error" => $cotizacion['error'],
                "size" => $cotizacion['size']
            ], 'assets/images/agentecompra/');

            $dataToInsert = $this->getCotizacionData($cotizacion);
            $dataToInsert['cotizacion_file_url'] = $fileUrl;
            $dataToInsert['id_contenedor'] = $data['id_contenedor'];
            $dataToInsert['id_usuario'] = Auth::id();

            $cotizacionModel = Cotizacion::create($dataToInsert);

            if ($cotizacionModel) {
                $idCotizacion = $cotizacionModel->id;
                $dataToInsert['id_cotizacion'] = $idCotizacion;

                $dataEmbarque = $this->getEmbarqueData($cotizacion, $dataToInsert);
                Log::error('Data embarque: ' . json_encode($dataEmbarque));

                // Insertar proveedores
                foreach ($dataEmbarque as $proveedor) {
                    CotizacionProveedor::create($proveedor);
                }

                $nombre = $dataToInsert['nombre'];

                // Obtener fecha de cierre del contenedor
                $contenedor = Contenedor::find($data['id_contenedor']);
                $f_cierre = $contenedor ? $contenedor->f_cierre : 'fecha no disponible';

                $message = 'Hola ' . $nombre . ' pudiste revisar la cotización enviada? 
        Te comento que cerramos nuestro consolidado este ' . $f_cierre . ' Por favor si cuentas con alguna duda me avisas y puedo llamarte para aclarar tus dudas.';

                $telefono = preg_replace('/\s+/', '', $dataToInsert['telefono']);
                $telefono = $telefono ? $telefono . '@c.us' : '';

                $data_json = [
                    'message' => $message,
                    'phoneNumberId' => $telefono,
                ];

                // Aquí podrías insertar en la tabla de crons si existe
                // Por ahora solo retornamos éxito

                return [
                    'id' => $idCotizacion,
                    'status' => "success"
                ];
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error en storeCotizacion: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene todos los tipos de cliente
     */
    public function getTipoCliente()
    {
        return TipoCliente::all();
    }

    /**
     * Elimina el archivo de cotización
     */


    /**
     * Elimina una cotización completa
     */
    public function deleteCotizacion($id)
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $cotizacion = Cotizacion::find($id);
            if ($cotizacion && $cotizacion->cotizacion_file_url) {
                if (file_exists($cotizacion->cotizacion_file_url)) {
                    unlink($cotizacion->cotizacion_file_url);
                }
            }

            $deleted = Cotizacion::destroy($id);

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            if ($deleted > 0) {
                return "success";
            }
            return false;
        } catch (Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            Log::error('Error en deleteCotizacion: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza el estado del cliente
     */


    /**
     * Refresca el archivo de cotización
     */
    public function refreshCotizacionFile($id)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return [
                    'status' => "error",
                    'message' => 'No se encontró la cotización con el ID proporcionado.'
                ];
            }

            $fileUrl = $cotizacion->cotizacion_file_url;
            if (!$fileUrl) {
                return [
                    'status' => "error",
                    'message' => 'No se encontró la URL del archivo de cotización.'
                ];
            }

            Log::info('Procesando archivo: ' . $fileUrl);

            $fileContents = $this->readFileFromMultipleSources($fileUrl);
            if ($fileContents === false || $fileContents === null || strlen($fileContents) == 0) {
                Log::error('No se pudo leer el archivo de cotización desde ninguna fuente: ' . $fileUrl);
                return [
                    'status' => "error",
                    'message' => 'El archivo de cotización no existe o no se puede leer.'
                ];
            }

            $originalExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);
            $tempFile = sys_get_temp_dir() . '/' . uniqid('cotizacion_', true) . '.' . $originalExtension;

            Log::info('Creando archivo temporal: ' . $tempFile);

            $bytesWritten = file_put_contents($tempFile, $fileContents);
            if ($bytesWritten === false) {
                Log::error('No se pudo crear el archivo temporal: ' . $tempFile);
                return [
                    'status' => "error",
                    'message' => 'Error al crear archivo temporal.'
                ];
            }

            if (!file_exists($tempFile) || !is_readable($tempFile)) {
                Log::error('El archivo temporal no es accesible: ' . $tempFile);
                return [
                    'status' => "error",
                    'message' => 'El archivo temporal no es accesible.'
                ];
            }

            $cotizacionFile = [
                'tmp_name' => $tempFile,
                'name' => basename($fileUrl),
                'size' => $bytesWritten,
                'type' => $this->getMimeType($originalExtension)
            ];

            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            try {
                $dataToInsert = $this->getCotizacionData($cotizacionFile);
                if (!$dataToInsert) {
                    throw new Exception('No se pudieron extraer datos del archivo de cotización');
                }

                if (isset($dataToInsert['fecha'])) {
                    unset($dataToInsert['fecha']);
                }

                $dataToInsert['updated_at'] = now();

                Log::info('Datos extraídos del archivo: ' . json_encode($dataToInsert));

                $cotizacion->update($dataToInsert);

                if (file_exists($tempFile)) {
                    unlink($tempFile);
                    Log::info('Archivo temporal eliminado: ' . $tempFile);
                }

                DB::statement('SET FOREIGN_KEY_CHECKS = 1');

                return [
                    'status' => "success",
                    'message' => 'Cotización actualizada exitosamente.'
                ];
            } catch (Exception $e) {
                if (isset($tempFile) && file_exists($tempFile)) {
                    unlink($tempFile);
                }
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Error en refreshCotizacionFile: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => 'Error al procesar la cotización: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene cargas disponibles
     */
    public function getCargasDisponibles()
    {
        $hoy = now()->format('Y-m-d');
        return Contenedor::where('f_cierre', '>=', $hoy)
            ->orderBy('carga', 'desc')
            ->get();
    }

    /**
     * Mueve una cotización a otro contenedor
     */
    public function moveCotizacionToConsolidado($idCotizacion, $idContenedorDestino)
    {
        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::find($idCotizacion);
            if ($cotizacion) {
                $cotizacion->update([
                    'id_contenedor' => $idContenedorDestino,
                    'estado_cotizador' => 'CONFIRMADO',
                    'updated_at' => now()
                ]);
            }

            CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->update(['id_contenedor' => $idContenedorDestino]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en moveCotizacionToConsolidado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lee archivo desde múltiples fuentes
     */
    private function readFileFromMultipleSources($fileUrl)
    {
        Log::error('Intentando leer archivo: ' . $fileUrl);

        // 1. Ruta absoluta
        if (file_exists($fileUrl)) {
            Log::error('Leyendo archivo desde ruta absoluta: ' . $fileUrl);
            $content = file_get_contents($fileUrl);
            if ($content !== false) {
                Log::error('Archivo leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }
        }

        // 2. Ruta local
        $localPath = base_path() . '/' . ltrim($fileUrl, '/');
        if (file_exists($localPath)) {
            Log::error('Leyendo archivo desde ruta local: ' . $localPath);
            $content = file_get_contents($localPath);
            if ($content !== false) {
                Log::error('Archivo leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }
        }

        // 3. Storage de Laravel
        if (Storage::exists($fileUrl)) {
            Log::error('Leyendo archivo desde storage: ' . $fileUrl);
            $content = Storage::get($fileUrl);
            if ($content !== false) {
                Log::error('Archivo leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }
        }

        // 4. URL remota
        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            Log::error('Intentando leer archivo remoto: ' . $fileUrl);

            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,*/*',
                        'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                        'Cache-Control: no-cache'
                    ],
                    'follow_location' => true,
                    'max_redirects' => 5
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $content = @file_get_contents($fileUrl, false, $context);
            if ($content !== false && strlen($content) > 0) {
                Log::error('Archivo remoto leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }

            // Fallback con cURL
            if (function_exists('curl_init')) {
                Log::error('Intentando con cURL...');
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fileUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,*/*',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                    'Cache-Control: no-cache'
                ]);

                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($content !== false && $httpCode == 200 && strlen($content) > 0) {
                    Log::error('Archivo remoto leído exitosamente con cURL, tamaño: ' . strlen($content) . ' bytes');
                    return $content;
                } else {
                    Log::error('Error cURL: ' . $error . ', HTTP Code: ' . $httpCode);
                }
            }
        }

        Log::error('No se pudo encontrar el archivo en ninguna ubicación: ' . $fileUrl);
        return false;
    }

    /**
     * Obtiene el tipo MIME basado en la extensión
     */
    private function getMimeType($extension)
    {
        $mimeTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv'
        ];

        return isset($mimeTypes[strtolower($extension)]) ? $mimeTypes[strtolower($extension)] : 'application/octet-stream';
    }

    /**
     * Sube un nuevo archivo de cotización
     */
    public function uploadCotizacionFile($id, $file)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return false;
            }

            try {
                // Eliminar archivo antiguo si existe
                $oldFileUrl = $cotizacion->cotizacion_file_url;
                if ($oldFileUrl) {
                    // Convertir la URL pública a ruta de storage
                    $oldStoragePath = str_replace('/storage/', 'public/', parse_url($oldFileUrl, PHP_URL_PATH));
                    Storage::delete($oldStoragePath);
                }

                // Subir nuevo archivo
                $fileName = time() . '_' . $file['name'];
                $fileUrl = Storage::putFileAs('public/agentecompra', $file['tmp_name'], $fileName);

                if (!$fileUrl) {
                    Log::error('Error al subir el nuevo archivo');
                    return false;
                }

                // Convertir a URL pública
                $fileUrl = Storage::url($fileUrl);
                Log::info('Nuevo archivo subido exitosamente:', ['url' => $fileUrl]);
            } catch (\Exception $e) {
                Log::error('Error al procesar el archivo: ' . $e->getMessage());
                return false;
            }

            $dataToInsert = $this->getCotizacionData($file);
            $dataToInsert['cotizacion_file_url'] = $fileUrl;
            $dataToInsert['updated_at'] = now();

            // Verificar si existe fecha en la BD
            if ($cotizacion->fecha) {
                unset($dataToInsert['fecha']);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $cotizacion->update($dataToInsert);

            if ($cotizacion->wasChanged()) {
                $dataToInsert['id_cotizacion'] = $id;
                $dataToInsert['id_contenedor'] = $cotizacion->id_contenedor;

                // Obtener proveedores existentes
                $existingProviders = CotizacionProveedor::where('id_cotizacion', $id)
                    ->pluck('code_supplier')
                    ->toArray();

                // Obtener datos de embarque
                $dataEmbarque = $this->getEmbarqueDataModified($file, $dataToInsert);
                $newProviders = array_column($dataEmbarque, 'code_supplier');
                Log::info("newProviders", $newProviders);
                Log::info("existingProviders", $existingProviders);
                Log::info("dataEmbarque", $dataEmbarque);
                // Actualizar proveedores existentes
                foreach ($existingProviders as $code) {
                    if (in_array($code, $newProviders)) {
                        $key = array_search($code, $newProviders);
                        $dataToUpdate = $dataEmbarque[$key];
                        CotizacionProveedor::where('code_supplier', $code)
                            ->where('id_cotizacion', $id)
                            ->update($dataToUpdate);
                    } else {
                        CotizacionProveedor::where('code_supplier', $code)
                            ->where('id_cotizacion', $id)
                            ->delete();
                    }
                }

                // Insertar nuevos proveedores
                foreach ($dataEmbarque as $data) {
                    if (!in_array($data['code_supplier'], $existingProviders)) {
                        CotizacionProveedor::create($data);
                    }
                }

                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                return "success";
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            return false;
        } catch (Exception $e) {
            Log::error('Error en uploadCotizacionFile: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Muestra una cotización específica
     */
    public function showCotizacion($id)
    {
        return Cotizacion::find($id);
    }

    /**
     * Actualiza una cotización
     */
    /**
     * Actualiza el archivo de una cotización existente
     * @param Request $request
     * @param int $id ID de la cotización
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCotizacionFile(Request $request, $id)
    {
        try {
            // Validar que la cotización existe
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Validar el archivo
            if (!$request->hasFile('cotizacion')) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'No se ha proporcionado ningún archivo'
                ], 400);
            }

            $file = $request->file('cotizacion');

            // Preparar el archivo para el método uploadCotizacionFile
            $fileData = [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getPathname(),
                'error' => 0,
                'size' => $file->getSize()
            ];

            // Intentar actualizar el archivo
            $result = $this->uploadCotizacionFile($id, $fileData);

            if ($result === "success") {
                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'message' => 'Archivo actualizado correctamente'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Error al actualizar el archivo'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error al actualizar archivo de cotización: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCotizacion($data, $cotizacion)
    {
        try {
            $cotizacionModel = Cotizacion::find($data['id']);
            if (!$cotizacionModel) {
                return false;
            }

            $oldFileUrl = $cotizacionModel->cotizacion_file_url;
            if ($oldFileUrl && file_exists($oldFileUrl)) {
                unlink($oldFileUrl);
            }

            $fileUrl = $this->uploadSingleFile([
                "name" => $cotizacion['name'],
                "type" => $cotizacion['type'],
                "tmp_name" => $cotizacion['tmp_name'],
                "error" => $cotizacion['error'],
                "size" => $cotizacion['size']
            ], 'assets/images/agentecompra/');

            $data['cotizacion_file_url'] = $fileUrl;

            $updated = $cotizacionModel->update($data);
            return $updated ? "success" : false;
        } catch (Exception $e) {
            Log::error('Error en updateCotizacion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el estado de una cotización
     */
    public function updateEstadoCotizacion($id, Request $request)
    {
        try {
            $estado = $request->estado;
            Log::info('estado: ' . $estado);
            Log::info('id: ' . $id);
            // Verificar si hay proveedores sin productos
            $proveedoresConProductos = CotizacionProveedor::where('id_cotizacion', $id)
                ->whereNotNull('products')
                ->where('products', '!=', '')
                ->count();

            $proveedoresSinProductos = CotizacionProveedor::where('id_cotizacion', $id)
                ->where(function ($query) {
                    $query->whereNull('products')
                        ->orWhere('products', '');
                })->count();




            if ($proveedoresSinProductos > 0 && $estado == 'CONFIRMADO') {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'No se puede cambiar el estado a CONFIRMADO hasta que todos los proveedores tengan productos'
                ], 400);
            }

            $cotizacion = Cotizacion::findOrFail($id);
            $cotizacion->update([
                'estado_cotizador' => $estado,

            ]);
            if ($estado == 'INTERESADO') {
                // Obtener datos necesarios
                $contenedor = $cotizacion->contenedor;

                // Preparar mensaje WhatsApp
                $message = "Hola {$cotizacion->nombre}, sabemos que está interesad@ en el consolidado #{$contenedor->carga}, " .
                    "y no queremos que te quedes sin espacio, deseas confirmar tu participación? \n\n";

                $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
                $telefono = $telefono ? $telefono . '@c.us' : '';

                // Crear entrada en la tabla de crons
                DB::table('contenedor_consolidado_cotizacion_crons')->insert([
                    'id_contenedor' => $contenedor->id,
                    'id_cotizacion' => $id,
                    'created_at' => now(),
                    'data_json' => json_encode([
                        'message' => $message,
                        'phoneNumberId' => $telefono,
                    ]),
                    'execution_at' => now()->addMinutes(config('app.default_hours_interesado', 60)),
                    'time_between' => config('app.default_hours_interesado', 60),
                    'status' => 'PENDING',
                ]);
            }

            if ($estado == 'CONFIRMADO') {
                $message = "El cliente {$cotizacion->nombre} ha pasado a confirmado, por favor contactar.";
                event(new \App\Events\CotizacionStatusUpdated($cotizacion, $estado, $message));

                // Crear notificación para Coordinación cuando se confirma la cotización
                $this->crearNotificacionCotizacionConfirmada($cotizacion);
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Estado actualizado correctamente'
            ]);
        } catch (Exception $e) {
            Log::error('Error en updateEstadoCotizacion: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Error al actualizar el estado de la cotización'
            ], 500);
        }
    }

    /**
     * Actualiza el estado general
     */
    public function updateEstado($id, $estado)
    {
        try {
            $user = Auth::user();
            $contenedor = Contenedor::find($id);

            if (!$contenedor) {
                return false;
            }

            if (in_array($user->No_Grupo, ['roleContenedorAlmacen'])) {
                $contenedor->update(['estado_china' => $estado]);
            } else {
                $contenedor->update(['estado' => $estado]);
            }

            return "success";
        } catch (Exception $e) {
            Log::error('Error en updateEstado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el estado de documentación
     */
    public function updateEstadoDocumentacion($id, $estado)
    {
        try {
            $contenedor = Contenedor::find($id);
            if ($contenedor) {
                $updated = $contenedor->update(['estado_documentacion' => $estado]);
                return $updated ? "success" : false;
            }
            return false;
        } catch (Exception $e) {
            Log::error('Error en updateEstadoDocumentacion: ' . $e->getMessage());
            return false;
        }
    }



    /**
     * Obtiene datos de embarque (placeholder - implementar según necesidades)
     */
    private function getEmbarqueData($file, $data)
    {
        try {
            if (!file_exists($file['tmp_name'])) {
                Log::error('Archivo no encontrado en getEmbarqueData: ' . $file['tmp_name']);
                return [];
            }

            $objPHPExcel = IOFactory::load($file['tmp_name']);
            $rowProveedores = 4;
            $nameCliente = $objPHPExcel->getSheet(0)->getCell('B8')->getValue();
            $sheet2 = $objPHPExcel->getSheet(1);
            $columnStart = "C";
            $columnTotales = "";

            // Convertir array a objeto si es necesario
            if (is_array($data)) {
                $data = (object)$data;
            }

            $idContenedor = $data->id_contenedor;

            // Obtener campo carga de la tabla contenedor
            $contenedor = DB::table('carga_consolidada_contenedor')
                ->select('carga')
                ->where('id', $idContenedor)
                ->first();

            if (!$contenedor) {
                Log::error('Contenedor no encontrado con ID: ' . $idContenedor);
                return [];
            }

            $carga = $contenedor->carga;

            // Completar a 2 dígitos si se puede convertir a número, sino usar últimos 2 caracteres
            $count = is_numeric($carga) ? str_pad($carga, 2, "0", STR_PAD_LEFT) : substr($carga, -2);
            $stop = false;

            // Buscar la columna "TOTALES"
            while (!$stop) {
                $cell = $sheet2->getCell($columnStart . "3")->getValue();
                if (strtoupper(trim($cell)) == "TOTALES") {
                    $columnTotales = $columnStart;
                    $stop = true;
                } else {
                    $columnStart = $this->incrementColumn($columnStart);
                }
            }

            $rowCajasProveedor = 5;
            $rowPesoProveedor = 6;
            $rowVolProveedor = 8;

            // Iterar desde C hasta $columnTotales y obtener valores de las filas 5,6,8
            $columnStart = "C"; // Columna inicial
            $stop = false;
            $provider = 1;
            $currentRange = null;
            $processedRanges = []; // Almacena los rangos procesados
            $proveedores = []; // Lista de proveedores

            while (!$stop) {
                // Verifica si la columna actual es la última
                if ($columnStart == $columnTotales) {
                    $stop = true;
                } else {
                    // Obtiene el rango combinado de la celda actual
                    $cell = $sheet2->getCell($columnStart . $rowProveedores);
                    $currentRange = $cell->getMergeRange();

                    // Si el rango ya fue procesado, pasa a la siguiente columna
                    if ($currentRange && in_array($currentRange, $processedRanges)) {
                        $columnStart = $this->incrementColumn($columnStart);
                        continue;
                    }

                    // Agrega el rango actual a los rangos procesados
                    if ($currentRange) {
                        $processedRanges[] = $currentRange;
                    }

                    // Genera el código del proveedor usando tu función
                    $codeSupplier = $this->generateCodeSupplier($nameCliente, $carga, $count, $provider);
                    // Obtener valores de las celdas
                    $qtyBox = $sheet2->getCell($columnStart . $rowCajasProveedor)->getValue();
                    $peso = $sheet2->getCell($columnStart . $rowPesoProveedor)->getValue();
                    $cbmTotal = $sheet2->getCell($columnStart . $rowVolProveedor)->getValue();

                    // Solo agregar proveedor si tiene datos válidos
                    if ($qtyBox > 0 || $peso > 0 || $cbmTotal > 0) {
                        // Agrega los datos del proveedor
                        $proveedores[] = [
                            'qty_box' => $qtyBox ?? 0,
                            'peso' => $peso ?? 0,
                            'cbm_total' => $cbmTotal ?? 0,
                            'id_cotizacion' => $data->id_cotizacion,
                            'code_supplier' => $codeSupplier,
                            'id_contenedor' => $data->id_contenedor,
                            'supplier' => '', // Campo adicional si es necesario
                            'products' => '', // Campo adicional si es necesario
                            'volumen_doc' => 0,
                            'valor_doc' => 0,
                            'factura_comercial' => '',
                            'excel_confirmacion' => '',
                            'packing_list' => '',
                            'supplier_phone' => '',
                            'estados_proveedor' => 'NC',
                            'estado_china' => 'PENDIENTE',
                            'qty_box_china' => 0,
                            'cbm_total_china' => 0,
                            'arrive_date_china' => null,
                            'send_rotulado_status' => 'PENDING',
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }

                    // Incrementa la columna y el contador del proveedor
                    $columnStart = $this->incrementColumn($columnStart);
                    $provider++;
                }
            }

            Log::info('Proveedores extraídos: ' . count($proveedores));
            return $proveedores;
        } catch (\Exception $e) {
            Log::error('Error en getEmbarqueData: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos de embarque modificados (placeholder - implementar según necesidades)
     */
    private function getEmbarqueDataModified($file, $data)
    {
        try {
            if (!file_exists($file['tmp_name'])) {
                Log::error('Archivo no encontrado en getEmbarqueDataModified: ' . $file['tmp_name']);
                return [];
            }

            $objPHPExcel = IOFactory::load($file['tmp_name']);
            $rowProveedores = 4;
            $nameCliente = $objPHPExcel->getSheet(0)->getCell('B8')->getValue();
            $sheet2 = $objPHPExcel->getSheet(1);
            $columnStart = "C";
            $columnTotales = "";

            // Convertir array a objeto si es necesario
            if (is_array($data)) {
                $data = (object)$data;
            }

            $idContenedor = $data->id_contenedor;

            // Obtener campo carga de la tabla contenedor
            $contenedor = DB::table('carga_consolidada_contenedor')
                ->select('carga')
                ->where('id', $idContenedor)
                ->first();

            if (!$contenedor) {
                Log::error('Contenedor no encontrado con ID: ' . $idContenedor);
                return [];
            }

            $carga = $contenedor->carga;

            // Completar a 2 dígitos si se puede convertir a número, sino usar últimos 2 caracteres
            $count = is_numeric($carga) ? str_pad($carga, 2, "0", STR_PAD_LEFT) : substr($carga, -2);
            $stop = false;

            // Buscar la columna "TOTALES"
            while (!$stop) {
                $cell = $sheet2->getCell($columnStart . "3")->getValue();
                if (strtoupper(trim($cell)) == "TOTALES") {
                    $columnTotales = $columnStart;
                    $stop = true;
                } else {
                    $columnStart = $this->incrementColumn($columnStart);
                }
            }

            $rowCodeSupplier = 3;
            $rowCajasProveedor = 5;
            $rowPesoProveedor = 6;
            $rowVolProveedor = 8;

            // Iterar desde C hasta $columnTotales y obtener valores de las filas 5,6,8
            $columnStart = "C"; // Columna inicial
            $stop = false;
            $provider = 1;
            $currentRange = null;
            $processedRanges = []; // Almacena los rangos procesados
            $proveedores = []; // Lista de proveedores

            while (!$stop) {
                // Verifica si la columna actual es la última
                if ($columnStart == $columnTotales) {
                    $stop = true;
                } else {
                    // Obtiene el rango combinado de la celda actual
                    $cell = $sheet2->getCell($columnStart . $rowProveedores);
                    $currentRange = $cell->getMergeRange();

                    // Si el rango ya fue procesado, pasa a la siguiente columna
                    if ($currentRange && in_array($currentRange, $processedRanges)) {
                        $columnStart = $this->incrementColumn($columnStart);
                        continue;
                    }

                    // Agrega el rango actual a los rangos procesados
                    if ($currentRange) {
                        $processedRanges[] = $currentRange;
                    }

                    $codeSupplier = $this->getDataCell($sheet2, $columnStart . $rowCodeSupplier);
                    $qtyBox = $this->getDataCell($sheet2, $columnStart . $rowCajasProveedor);
                    $peso = $this->getDataCell($sheet2, $columnStart . $rowPesoProveedor);
                    $cbmTotal = $this->getDataCell($sheet2, $columnStart . $rowVolProveedor);

                    // Solo agregar proveedor si tiene datos válidos
                    if ($qtyBox > 0 || $peso > 0 || $cbmTotal > 0) {
                        $proveedores[] = [
                            'qty_box' => $qtyBox,
                            'peso' => $peso,
                            'cbm_total' => $cbmTotal,
                            'id_cotizacion' => $data->id_cotizacion,
                            'id_contenedor' => $data->id_contenedor,
                            'code_supplier' => $codeSupplier,
                            'volumen_doc' => 0,
                            'send_rotulado_status' => 'PENDING',
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }

                    // Incrementa la columna y el contador del proveedor
                    $columnStart = $this->incrementColumn($columnStart);
                    $provider++;
                }
            }

            Log::info('Proveedores modificados extraídos: ' . count($proveedores));
            return $proveedores;
        } catch (\Exception $e) {
            Log::error('Error en getEmbarqueDataModified: ' . $e->getMessage());
            return [];
        }
    }
    public function generateCodeSupplier($string, $carga, $rowCount, $index)
    {

        $words = explode(" ", trim($string));
        $code = "";

        // Primeras 2 letras de las primeras 2 palabras (protegido)
        foreach ($words as $word) {
            if (strlen($code) >= 4) break; // Ya tenemos 4 caracteres (2 palabras)
            if (strlen($word) >= 2) { // Solo si la palabra tiene 2+ caracteres
                $code .= strtoupper(substr($word, 0, 2));
            }
        }

        // Completar con ceros y retornar
        return $code . $carga . "-" . $index;
    }
    /**
     * Obtiene el valor de una celda, manejando diferentes tipos de datos
     */
    private function getDataCell($sheet, $cell)
    {
        $value = "";
        $value = $sheet->getCell($cell)->getValue();
        if ($value == "") {
            $value = $sheet->getCell($cell)->getOldCalculatedValue();
        }
        if ($value == "") {
            $value = $sheet->getCell($cell)->getCalculatedValue();
        }
        return $value;
    }

    public function deleteCotizacionFile($id)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return false;
            }

            $oldFileUrl = $cotizacion->cotizacion_file_url;
            if ($oldFileUrl) {
                // Convertir la URL pública a ruta de storage
                $storagePath = str_replace('/storage/', 'public/', parse_url($oldFileUrl, PHP_URL_PATH));
                Storage::delete($storagePath);
            }

            $cotizacion->update(['cotizacion_file_url' => null]);
            return response()->json(['message' => 'Cotizacion file deleted successfully', 'success' => true]);
        } catch (Exception $e) {
            Log::error('Error en deleteCotizacionFile: ' . $e->getMessage());
            return false;
        }
    }

    public function exportarCotizacion(Request $request, $idContenedor)
    {
        try {
            return $this->cotizacionExportService->exportarCotizacion($request, $idContenedor);
        } catch (\Exception $e) {
            Log::error('Error en exportarCotizacion: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea una notificación para el perfil de Coordinación cuando se crea una nueva cotización
     */
    private function crearNotificacionCoordinacion($cotizacion, $contenedor)
    {
        try {
            // Obtener el usuario que creó la cotización
            $usuarioCreador = Usuario::find($cotizacion->id_usuario);

            if (!$usuarioCreador) {
                Log::warning('Usuario creador no encontrado para la cotización: ' . $cotizacion->id);
                return;
            }

            // Crear la notificación para Coordinación
            $notificacion = Notificacion::create([
                'titulo' => 'Nueva Cotización Creada',
                'mensaje' => "El usuario {$usuarioCreador->No_Nombres_Apellidos} ha creado una nueva cotización para {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor: {$contenedor->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COORDINACION,
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:file-document-plus',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'activa' => true,
                'creado_por' => $usuarioCreador->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COORDINACION => [
                        'titulo' => 'Nueva Cotización - Revisar',
                        'mensaje' => "Nueva cotización de {$cotizacion->nombre} requiere revisión",
                        'descripcion' => "Cotización #{$cotizacion->id} para contenedor {$contenedor->carga}"
                    ]
                ])
            ]);

            // Crear la notificación para Jefe de Ventas
            $notificacionJefeVentas = Notificacion::create([
                'titulo' => 'Nueva Cotización Creada',
                'mensaje' => "El usuario {$usuarioCreador->No_Nombres_Apellidos} ha creado una nueva cotización para {$cotizacion->nombre}",
                'descripcion' => "Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor: {$contenedor->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'usuario_destinatario' => Usuario::ID_JEFE_VENTAS,
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:file-document-plus',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'activa' => true,
                'creado_por' => $usuarioCreador->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Nueva Cotización - Supervisión',
                        'mensaje' => "Nueva cotización de {$cotizacion->nombre} creada por {$usuarioCreador->No_Nombres_Apellidos}",
                        'descripcion' => "Cotización #{$cotizacion->id} para contenedor {$contenedor->carga} - Supervisión requerida"
                    ]
                ])
            ]);

            Log::info('Notificaciones creadas para Coordinación y Jefe de Ventas:', [
                'notificacion_coordinacion_id' => $notificacion->id,
                'notificacion_jefe_ventas_id' => $notificacionJefeVentas->id,
                'cotizacion_id' => $cotizacion->id,
                'contenedor_id' => $contenedor->id,
                'usuario_creador' => $usuarioCreador->No_Nombres_Apellidos
            ]);

            return [$notificacion, $notificacionJefeVentas];
        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones para Coordinación y Jefe de Ventas: ' . $e->getMessage());
            // No lanzar excepción para no afectar el flujo principal de creación de cotización
            return null;
        }
    }

    /**
     * Crea una notificación para Coordinación cuando una cotización se confirma
     */
    private function crearNotificacionCotizacionConfirmada($cotizacion)
    {
        try {
            // Obtener el contenedor
            $contenedor = $cotizacion->contenedor;
            if (!$contenedor) {
                Log::warning('Contenedor no encontrado para la cotización: ' . $cotizacion->id);
                return;
            }

            // Obtener el usuario que confirmó la cotización
            $usuarioActual = Auth::user();
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al confirmar cotización: ' . $cotizacion->id);
                return;
            }

            // Crear la notificación para Coordinación
            $notificacion = Notificacion::create([
                'titulo' => 'Cotización Confirmada',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} confirmó la cotización del cliente {$cotizacion->nombre}",
                'descripcion' => "Cotización #{$cotizacion->id} confirmada | Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor: {$contenedor->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COORDINACION,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $contenedor->id,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_SUCCESS,
                'icono' => 'mdi:check-circle',
                'prioridad' => Notificacion::PRIORIDAD_ALTA,
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COORDINACION => [
                        'titulo' => 'Cotización Confirmada - Acción Requerida',
                        'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} confirmó la cotización de {$cotizacion->nombre} - Requiere seguimiento",
                        'descripcion' => "Cotización #{$cotizacion->id} para contenedor {$contenedor->carga} confirmada por el usuario {$usuarioActual->No_Nombres_Apellidos}"
                    ]
                ])
            ]);

            // Crear la notificación para Jefe de Ventas
            $notificacionJefeVentas = Notificacion::create([
                'titulo' => 'Cotización Confirmada',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} confirmó la cotización del cliente {$cotizacion->nombre}",
                'descripcion' => "Cotización #{$cotizacion->id} confirmada | Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor: {$contenedor->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'usuario_destinatario' => Usuario::ID_JEFE_VENTAS,
                'rol_destinatario' => Usuario::ROL_COTIZADOR,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $contenedor->id,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_SUCCESS,
                'icono' => 'mdi:check-circle',
                'prioridad' => Notificacion::PRIORIDAD_ALTA,
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Cotización Confirmada - Supervisión',
                        'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} confirmó la cotización de {$cotizacion->nombre} - Seguimiento requerido",
                        'descripcion' => "Cotización #{$cotizacion->id} para contenedor {$contenedor->carga} confirmada por {$usuarioActual->No_Nombres_Apellidos}"
                    ]
                ])
            ]);

            Log::info('Notificaciones de cotización confirmada creadas para Coordinación y Jefe de Ventas:', [
                'notificacion_coordinacion_id' => $notificacion->id,
                'notificacion_jefe_ventas_id' => $notificacionJefeVentas->id,
                'cotizacion_id' => $cotizacion->id,
                'contenedor_id' => $contenedor->id,
                'usuario_actual' => $usuarioActual->No_Nombres_Apellidos
            ]);

            return [$notificacion, $notificacionJefeVentas];
        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones de cotización confirmada para Coordinación y Jefe de Ventas: ' . $e->getMessage());
            // No lanzar excepción para no afectar el flujo principal de actualización de estado
            return null;
        }
    }
}
