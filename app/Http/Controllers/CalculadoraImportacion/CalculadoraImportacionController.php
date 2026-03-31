<?php

namespace App\Http\Controllers\CalculadoraImportacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\CalculadoraImportacion;
use App\Services\BaseDatos\Clientes\ClienteService;
use App\Services\CalculadoraImportacionService;
use App\Services\ResumenCostosImageService;
use App\Services\CalculadoraImportacion\ClienteWhatsappLookupService;
use App\Services\CalculadoraImportacion\CalculadoraImportacionExcelService;
use App\Services\CalculadoraImportacion\CalculadoraImportacionWhatsappService;
use App\Services\CalculadoraImportacion\CalculadoraImportacionCotizacionSyncService;
use App\Services\CalculadoraImportacion\CalculadoraImportacionCacheService;
use App\Models\CalculadoraTarifasConsolidado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\UserCotizacionExport;
use App\Http\Controllers\CargaConsolidada\CotizacionController;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CalculadoraImportacionExport;

class CalculadoraImportacionController extends Controller
{
    use WhatsappTrait;
    protected $clienteService;
    protected $calculadoraImportacionService;
    protected ClienteWhatsappLookupService $clienteWhatsappLookupService;
    protected CalculadoraImportacionExcelService $excelService;
    protected CalculadoraImportacionWhatsappService $whatsappService;
    protected CalculadoraImportacionCotizacionSyncService $cotizacionSyncService;
    protected CalculadoraImportacionCacheService $cacheService;

    public function __construct(
        ClienteService $clienteService,
        CalculadoraImportacionService $calculadoraImportacionService,
        ClienteWhatsappLookupService $clienteWhatsappLookupService,
        CalculadoraImportacionExcelService $excelService,
        CalculadoraImportacionWhatsappService $whatsappService,
        CalculadoraImportacionCotizacionSyncService $cotizacionSyncService,
        CalculadoraImportacionCacheService $cacheService
    ) {
        $this->clienteService = $clienteService;
        $this->calculadoraImportacionService = $calculadoraImportacionService;
        $this->clienteWhatsappLookupService = $clienteWhatsappLookupService;
        $this->excelService = $excelService;
        $this->whatsappService = $whatsappService;
        $this->cotizacionSyncService = $cotizacionSyncService;
        $this->cacheService = $cacheService;
    }

    /**
     * @OA\Get(
     *     path="/calculadora-importacion/clientes",
     *     tags={"Calculadora Importación"},
     *     summary="Buscar clientes por WhatsApp",
     *     description="Obtiene la lista de clientes que coinciden con un número de WhatsApp",
     *     operationId="getClientesByWhatsapp",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="whatsapp",
     *         in="query",
     *         description="Número de WhatsApp a buscar",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Clientes encontrados",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getClientesByWhatsapp(Request $request)
    {
        try {
            $request->validate([
                'whatsapp' => 'required|string',
            ]);

            $whatsapp = (string) $request->whatsapp;

            $clientesTransformados = $this->cacheService->rememberClientesByWhatsapp($whatsapp, function () use ($whatsapp) {
                return $this->clienteWhatsappLookupService->searchClientesByWhatsapp($whatsapp);
            });

            if (empty($clientesTransformados)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No se encontraron clientes con teléfono',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $clientesTransformados,
                'total' => count($clientesTransformados)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/calculadora-importacion/tarifas",
     *     tags={"Calculadora Importación"},
     *     summary="Obtener tarifas",
     *     description="Obtiene la lista de tarifas",
     *     operationId="getTarifas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Tarifas obtenidas exitosamente")
     * )
     */
    public function getTarifas()
    {
        try {
            $payload = $this->cacheService->rememberTarifas(function () {
                $tarifas = CalculadoraTarifasConsolidado::with('tipoCliente')
                    ->whereHas('tipoCliente')
                    ->get();

                $tarifas = $tarifas->map(function ($tarifa) {
                    return [
                        'id' => $tarifa->id,
                        'limit_inf' => $tarifa->limit_inf,
                        'limit_sup' => $tarifa->limit_sup,
                        'type' => $tarifa->type,
                        'tarifa' => $tarifa->value,
                        'label' => $tarifa->tipoCliente->nombre,
                        'id_tipo_cliente' => $tarifa->tipoCliente->id,
                        'value' => $tarifa->tipoCliente->nombre,
                        'created_at' => $tarifa->created_at ? $tarifa->created_at->toIso8601String() : null,
                        'updated_at' => $tarifa->updated_at ? $tarifa->updated_at->toIso8601String() : null,
                    ];
                })->values()->all();

                return [
                    'success' => true,
                    'data' => $tarifas,
                ];
            });

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tarifas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar monto (value) y tipo (PLAIN|STANDARD) de una tarifa. Rangos CBM no se modifican.
     */
    public function updateTarifa(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'value' => 'required|numeric|min:0',
                'type' => 'required|in:PLAIN,STANDARD',
            ]);

            $tarifa = CalculadoraTarifasConsolidado::whereNull('deleted_at')->findOrFail((int) $id);
            $tarifa->value = $validated['value'];
            $tarifa->type = $validated['type'];
            $tarifa->save();
            $tarifa->refresh();

            $this->cacheService->flushTarifas();

            return response()->json([
                'success' => true,
                'message' => 'Tarifa actualizada correctamente.',
                'data' => [
                    'id' => $tarifa->id,
                    'tarifa' => (float) $tarifa->value,
                    'type' => $tarifa->type,
                    'created_at' => $tarifa->created_at ? $tarifa->created_at->toIso8601String() : null,
                    'updated_at' => $tarifa->updated_at ? $tarifa->updated_at->toIso8601String() : null,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la tarifa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todos los cálculos de importación
     */
    public function index(Request $request)
    {
        try {
            $params = array_merge($request->query(), [
                'user_id' => auth()->id(),
            ]);

            $payload = $this->cacheService->rememberIndex($params, function () use ($request) {
                $query = CalculadoraImportacion::with(['proveedores.productos', 'cliente', 'contenedor', 'creador', 'vendedor', 'cotizacion']);

                //filter optional campania=54&estado_calculadora=PENDIENTE&vendedor=id_usuario
                if ($request->has('campania') && $request->campania) {
                    $query->where('id_carga_consolidada_contenedor', $request->campania);
                }
                if ($request->has('estado_calculadora') && $request->estado_calculadora) {
                    $query->where('estado', $request->estado_calculadora);
                }
                if ($request->has('vendedor') && $request->vendedor) {
                    $query->where('id_usuario', $request->vendedor);
                }

                // Filtro por vinculación de proveedores (cotización desvinculada/mapeo incompleto)
                // - desvinculadas: existe al menos un proveedor con code_supplier vacío o id_proveedor null
                // - vinculadas: todos los proveedores tienen code_supplier no vacío y id_proveedor no null
                if ($request->has('proveedores_vinculados') && $request->proveedores_vinculados) {
                    $vinculacion = (string) $request->proveedores_vinculados;

                    $invalidProveedor = function ($q) {
                        $q->where(function ($q2) {
                            $q2->whereNull('id_proveedor')
                                ->orWhereNull('code_supplier')
                                ->orWhereRaw('TRIM(code_supplier) = \'\'');
                        });
                    };

                    if ($vinculacion === 'desvinculadas') {
                        $query->whereHas('proveedores', $invalidProveedor);
                    } elseif ($vinculacion === 'vinculadas') {
                        $query->whereHas('proveedores')
                            ->whereDoesntHave('proveedores', $invalidProveedor);
                    }
                }

                if ($request->has('fecha_inicio') && $request->fecha_inicio) {
                    $query->whereDate('created_at', '>=', $request->fecha_inicio);
                }
                if ($request->has('fecha_fin') && $request->fecha_fin) {
                    $query->whereDate('created_at', '<=', $request->fecha_fin);
                }

                // Ordenamiento
                $sortBy = $request->get('sort_by', 'created_at');
                $sortOrder = $request->get('sort_order', 'desc');
                $query->orderBy($sortBy, $sortOrder);

                $search = $request->get('search', '');
                $perPage = $request->get('per_page', 10);
                $page = (int) $request->get('page', 1);
                $calculos = $query->where('nombre_cliente', 'like', '%' . $search . '%')->paginate($perPage, ['*'], 'page', $page);

                // Calcular totales para cada cálculo
                $data = $calculos->items();
                foreach ($data as $calculadora) {
                    $this->ordenarProveedoresPorId($calculadora);
                    $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);
                    $calculadora->totales = $totales;
                    $calculadora->url_cotizacion = $this->generateUrl($calculadora->url_cotizacion);
                    $calculadora->url_cotizacion_pdf = $this->generateUrl($calculadora->url_cotizacion_pdf);
                    $calculadora->nombre_creador = optional($calculadora->creador)->No_Nombres_Apellidos;
                    $calculadora->nombre_vendedor = optional($calculadora->vendedor)->No_Nombres_Apellidos;
                    $calculadora->carga_contenedor = '  #' . optional($calculadora->contenedor)->carga . '-' . ($calculadora->contenedor ? Carbon::parse($calculadora->contenedor->f_inicio)->format('Y') : '2025');
                    $calculadora->estado_cotizador = optional($calculadora->cotizacion)->estado_cotizador;
                    $calculadora->cod_contract = optional($calculadora->cotizacion)->cod_contract;
                }

                $anioActual = Carbon::now()->year;
                $contenedores = Contenedor::whereYear('f_inicio', $anioActual)->get();
                $contenedores = $contenedores->map(function ($contenedor) {
                    return [
                        'id' => $contenedor->id,
                        'label' => $contenedor->carga,
                        'value' => $contenedor->id,
                    ];
                });

                $estadoCalculadora = CalculadoraImportacion::getEstadosDisponiblesFilter();

                $vendedoresIds = CalculadoraImportacion::whereNotNull('id_usuario')->distinct()->pluck('id_usuario');
                $vendedores = \App\Models\Usuario::whereIn('ID_Usuario', $vendedoresIds)
                    ->get()
                    ->map(function ($u) {
                        return ['id' => $u->ID_Usuario, 'label' => $u->No_Nombres_Apellidos ?? 'Usuario ' . $u->ID_Usuario, 'value' => $u->ID_Usuario];
                    });

                $cotizacionesRealizadas = CalculadoraImportacion::whereIn('estado', ['COTIZADO', 'CONFIRMADO'])->count();
                $cotizacionesPendientes = CalculadoraImportacion::where('estado', 'PENDIENTE')->count();
                $cotizacionesVendidas = CalculadoraImportacion::where('estado', 'CONFIRMADO')->count();

                return [
                    'success' => true,
                    'data' => $data,
                    'pagination' => [
                        'current_page' => $calculos->currentPage(),
                        'last_page' => $calculos->lastPage(),
                        'per_page' => $calculos->perPage(),
                        'total' => $calculos->total(),
                        'from' => $calculos->firstItem(),
                        'to' => $calculos->lastItem(),
                    ],
                    'headers' => [
                        'cotizaciones_pendientes' => [
                            'value' => $cotizacionesPendientes,
                            'label' => 'Cotizaciones Pendientes',
                        ],
                        'cotizaciones_realizadas' => [
                            'value' => $cotizacionesRealizadas,
                            'label' => 'Cotizaciones Realizadas',
                        ],
                        'cotizaciones_vendidas' => [
                            'value' => $cotizacionesVendidas,
                            'label' => 'Cotizaciones Vendidas',
                        ],
                    ],
                    'filters' => [
                        'contenedores' => $contenedores,
                        'estadoCalculadora' => $estadoCalculadora,
                        'vendedores' => $vendedores,
                    ],
                ];
            });

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cálculos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar listado de cotizaciones calculadora a XLSX (mismos filtros que index).
     */
    public function exportList(Request $request)
    {
        try {
            $query = CalculadoraImportacion::with(['proveedores.productos', 'cliente', 'contenedor', 'creador', 'vendedor', 'cotizacion']);

            if ($request->has('campania') && $request->campania) {
                $query->where('id_carga_consolidada_contenedor', $request->campania);
            }
            if ($request->has('estado_calculadora') && $request->estado_calculadora) {
                $query->where('estado', $request->estado_calculadora);
            }
            if ($request->has('vendedor') && $request->vendedor) {
                $query->where('id_usuario', $request->vendedor);
            }

            // Filtro por vinculación de proveedores (cotización desvinculada/mapeo incompleto)
            if ($request->has('proveedores_vinculados') && $request->proveedores_vinculados) {
                $vinculacion = (string) $request->proveedores_vinculados;

                $invalidProveedor = function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNull('id_proveedor')
                            ->orWhereNull('code_supplier')
                            ->orWhereRaw('TRIM(code_supplier) = \'\'');
                    });
                };

                if ($vinculacion === 'desvinculadas') {
                    $query->whereHas('proveedores', $invalidProveedor);
                } elseif ($vinculacion === 'vinculadas') {
                    $query->whereHas('proveedores')
                        ->whereDoesntHave('proveedores', $invalidProveedor);
                }
            }
            if ($request->has('fecha_inicio') && $request->fecha_inicio) {
                $query->whereDate('created_at', '>=', $request->fecha_inicio);
            }
            if ($request->has('fecha_fin') && $request->fecha_fin) {
                $query->whereDate('created_at', '<=', $request->fecha_fin);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $search = $request->get('search', '');
            if ($search !== '') {
                $query->where('nombre_cliente', 'like', '%' . $search . '%');
            }

            $calculos = $query->limit(10000)->get();

            foreach ($calculos as $calculadora) {
                $calculadora->totales = $this->calculadoraImportacionService->calcularTotales($calculadora);
                $calculadora->nombre_creador = optional($calculadora->creador)->No_Nombres_Apellidos;
                $calculadora->nombre_vendedor = optional($calculadora->vendedor)->No_Nombres_Apellidos;
                $calculadora->carga_contenedor = '  #' . optional($calculadora->contenedor)->carga . '-' . ($calculadora->contenedor ? Carbon::parse($calculadora->contenedor->f_inicio)->format('Y') : '2025');
            }

            $filename = 'cotizaciones_calculadora_' . Carbon::now()->format('Y-m-d') . '.xlsx';

            return Excel::download(
                new CalculadoraImportacionExport($calculos),
                $filename,
                \Maatwebsite\Excel\Excel::XLSX
            );
        } catch (\Exception $e) {
            Log::error('Error al exportar cotizaciones calculadora: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateUrl($ruta)
    {
        if ($ruta) {
            return env('APP_URL') . $ruta;
        }
        return null;
    }
    /**
     * Guardar o actualizar cálculo de importación
     */
    public function store(Request $request)
    {
        try {
            // Normalizar campos numéricos (null, "" o no numérico -> valor por defecto) para evitar "data was invalid"
            $data = $request->all();
            if (isset($data['proveedores']) && is_array($data['proveedores'])) {
                foreach ($data['proveedores'] as $i => $proveedor) {
                    $p = &$data['proveedores'][$i];
                    if (!isset($p['peso']) || $p['peso'] === '' || $p['peso'] === null || !is_numeric($p['peso'])) {
                        $p['peso'] = 0;
                    }
                    if (!isset($p['cbm']) || $p['cbm'] === '' || $p['cbm'] === null || !is_numeric($p['cbm'])) {
                        $p['cbm'] = 0;
                    }
                    if (!isset($p['qtyCaja']) || $p['qtyCaja'] === '' || $p['qtyCaja'] === null || !is_numeric($p['qtyCaja'])) {
                        $p['qtyCaja'] = 0;
                    }
                    if (isset($p['productos']) && is_array($p['productos'])) {
                        foreach ($p['productos'] as $j => $producto) {
                            $prod = &$data['proveedores'][$i]['productos'][$j];
                            if (!isset($prod['antidumpingCU']) || $prod['antidumpingCU'] === '') {
                                $prod['antidumpingCU'] = null;
                            }
                            if (!isset($prod['adValoremP']) || $prod['adValoremP'] === '') {
                                $prod['adValoremP'] = null;
                            }
                            if (!isset($prod['cantidad']) || $prod['cantidad'] === '' || $prod['cantidad'] === null || !is_numeric($prod['cantidad'])) {
                                $prod['cantidad'] = 1;
                            }
                            if (!isset($prod['valoracion']) || $prod['valoracion'] === '' || $prod['valoracion'] === null || !is_numeric($prod['valoracion'])) {
                                $prod['valoracion'] = 0;
                            }
                        }
                    }
                }
            }
            $request->replace($data);

            $request->validate([
                'id' => 'nullable|integer|exists:calculadora_importacion,id',
                'clienteInfo.nombre' => 'required_if:clienteInfo.tipoDocumento,DNI|nullable|string',
                'clienteInfo.tipoDocumento' => 'required|string|in:DNI,RUC',
                'clienteInfo.dni' => 'nullable|string',
                'clienteInfo.ruc' => 'required_if:clienteInfo.tipoDocumento,RUC|nullable|string',
                'clienteInfo.empresa' => 'required_if:clienteInfo.tipoDocumento,RUC|nullable|string',
                'clienteInfo.whatsapp' => 'nullable|string',
                'clienteInfo.correo' => 'nullable|string',
                'clienteInfo.tipoCliente' => 'required|string',
                'clienteInfo.qtyProveedores' => 'required|integer|min:1',
                'proveedores' => 'required|array|min:1',
                'proveedores.*.cbm' => 'required|numeric|min:0',
                'proveedores.*.peso' => 'required|numeric|min:0',
                'proveedores.*.productos' => 'required|array|min:1',
                'proveedores.*.productos.*.nombre' => 'required|string',
                'proveedores.*.productos.*.precio' => 'required|numeric|min:0',
                'proveedores.*.productos.*.cantidad' => 'required|numeric|min:0',
                'proveedores.*.productos.*.valoracion' => 'nullable|numeric|min:0',
                'proveedores.*.productos.*.antidumpingCU' => 'nullable|numeric|min:0',
                'proveedores.*.productos.*.adValoremP' => 'nullable|numeric|min:0',
                'tarifaTotalExtraProveedor' => 'nullable|numeric|min:0',
                'tarifaTotalExtraItem' => 'nullable|numeric|min:0',
                'es_imo' => 'nullable|boolean',
            ]);

            $data = $request->all();
            $data['created_by'] = auth()->id();

            // Validar límite de CBM IMO por contenedor (si aplica)
            if (!empty($data['es_imo']) && !empty($data['id_carga_consolidada_contenedor'])) {
                $contenedorId = (int) $data['id_carga_consolidada_contenedor'];
                $contenedor = Contenedor::find($contenedorId);

                if ($contenedor && $contenedor->limite_cbm_imo !== null) {
                    // CBM total del payload actual
                    $nuevoCbm = 0.0;
                    if (!empty($data['proveedores']) && is_array($data['proveedores'])) {
                        foreach ($data['proveedores'] as $proveedor) {
                            $nuevoCbm += (float) ($proveedor['cbm'] ?? 0);
                        }
                    }

                    // CBM IMO ya registrado en otras calculadoras para este contenedor
                    $query = CalculadoraImportacion::query()
                        ->join('calculadora_importacion_proveedores as cip', 'calculadora_importacion.id', '=', 'cip.id_calculadora_importacion')
                        ->where('calculadora_importacion.id_carga_consolidada_contenedor', $contenedorId)
                        ->where('calculadora_importacion.es_imo', true);

                    // En edición, excluir la propia calculadora de la suma
                    if (!empty($data['id'])) {
                        $query->where('calculadora_importacion.id', '!=', (int) $data['id']);
                    }

                    $cbmExistente = (float) $query->sum('cip.cbm');
                    $cbmTotal = $cbmExistente + $nuevoCbm;

                    if ($cbmTotal > (float) $contenedor->limite_cbm_imo) {
                        return response()->json([
                            'success' => false,
                            'message' => sprintf(
                                'No se puede guardar la cotización IMO: el volumen total IMO (%.2f CBM) supera el límite del consolidado (%.2f CBM).',
                                $cbmTotal,
                                (float) $contenedor->limite_cbm_imo
                            ),
                        ], 422);
                    }
                }
            }

            // Si viene ID, es una actualización
            if ($request->has('id') && $request->id) {
                $calculadora = CalculadoraImportacion::find($request->id);

                if (!$calculadora) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Calculadora no encontrada'
                    ], 404);
                }

                // Actualizar usando el servicio
                $calculadora = $this->calculadoraImportacionService->actualizarCalculo($calculadora, $data);

                // Si tiene cotización asignada, actualizar también la cotización usando los códigos
                if ($calculadora->id_cotizacion && $calculadora->url_cotizacion) {
                    // Recargar proveedores para obtener códigos actualizados
                    $calculadora->load(['proveedores', 'contenedor']);
                    
                    // NO regenerar códigos - solo asegurar que estén escritos en el Excel si ya existen
                    // Los códigos solo se generan al pasar a COTIZADO, no al actualizar
                    if ($calculadora->id_carga_consolidada_contenedor) {
                        // Verificar si los proveedores tienen códigos
                        $proveedoresConCodigo = $calculadora->proveedores()->whereNotNull('code_supplier')->count();
                        if ($proveedoresConCodigo > 0) {
                            // Solo escribir códigos existentes en el Excel (no generar nuevos)
                            $this->excelService->escribirCodigosExistentesEnExcel($calculadora);
                        }
                    }
                    
                    // Actualizar la cotización relacionada
                    $this->cotizacionSyncService->actualizarCotizacionDesdeCalculadora($calculadora);
                }

                // Modificar el Excel para agregar fechas de pago (también al actualizar)
                if ($calculadora->url_cotizacion && $calculadora->id_carga_consolidada_contenedor) {
                    $this->excelService->modificarExcelConFechas($calculadora);
                }

                // Regenerar PDF de la cotización para que refleje el Excel actual (con cod, fechas, etc.)
                if ($calculadora->url_cotizacion) {
                    $boletaInfo = $this->calculadoraImportacionService->regenerarBoletaPdf($calculadora);
                    if ($boletaInfo && !empty($boletaInfo['url'])) {
                        $calculadora->url_cotizacion_pdf = $boletaInfo['url'];
                        $calculadora->save();
                    }
                }

                $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);
                $this->ordenarProveedoresPorId($calculadora);

                // Invalidate caches relacionados a esta calculadora
                $this->cacheService->invalidateAfterWrite($calculadora, [
                    'dni_cliente' => $calculadora->dni_cliente ?? null,
                    'whatsapp' => $calculadora->whatsapp_cliente ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Cálculo actualizado exitosamente',
                    'data' => [
                        'calculadora' => $calculadora,
                        'totales' => $totales
                    ]
                ]);
            }

            // Si no viene ID, es una creación
            $calculadora = $this->calculadoraImportacionService->guardarCalculo($data);

            // Modificar el Excel para agregar fechas de pago si ya tiene URL y contenedor
            if ($calculadora->url_cotizacion && $calculadora->id_carga_consolidada_contenedor) {
                $this->excelService->modificarExcelConFechas($calculadora);
            }

            $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);
            $this->ordenarProveedoresPorId($calculadora);

            // Invalidate caches globales/listado y caches por cliente/whatsapp
            $this->cacheService->invalidateAfterWrite($calculadora, [
                'dni_cliente' => $calculadora->dni_cliente ?? null,
                'whatsapp' => $calculadora->whatsapp_cliente ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cálculo guardado exitosamente',
                'data' => [
                    'calculadora' => $calculadora,
                    'totales' => $totales
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $lista = [];
            foreach ($errors as $campo => $mensajes) {
                $lista[] = $campo . ': ' . implode(' ', $mensajes);
            }
            $mensajeDescriptivo = 'Error de validación. ' . implode(' | ', $lista);
            Log::warning('Calculadora validación fallida', ['errors' => $errors]);
            return response()->json([
                'success' => false,
                'message' => $mensajeDescriptivo,
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al guardar el cálculo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar cotización sin guardar en calculadora: genera Excel + PDF y guarda registro en user_cotizacion_exports.
     * Para flujo n8n/WhatsApp: acordar datos por WhatsApp y luego llamar a esta ruta para obtener el PDF.
     * Tarifa principal, extra por proveedores y extra por ítems se calculan en backend si no se envían:
     * - Tipo de cliente: por whatsapp (busca en BD de clientes y servicios → NUEVO, RECURRENTE, etc.); si no hay cliente, NUEVO.
     * - Tarifa: por tipo de cliente + CBM total (calculadora_tarifas_consolidado).
     * - Extra proveedor: (proveedores - 3) × 50 USD.
     * - Extra ítem: por CBM total y cantidad de ítems (tabla TARIFAS_EXTRA_ITEM_PER_CBM).
     */
    public function exportCotizacion(Request $request)
    {
        try {
            $data = $request->all();
            if (isset($data['proveedores']) && is_array($data['proveedores'])) {
                foreach ($data['proveedores'] as $i => $proveedor) {
                    if (isset($proveedor['productos']) && is_array($proveedor['productos'])) {
                        foreach ($proveedor['productos'] as $j => $producto) {
                            if (isset($producto['antidumpingCU']) && $producto['antidumpingCU'] === '') {
                                $data['proveedores'][$i]['productos'][$j]['antidumpingCU'] = null;
                            }
                            if (isset($producto['adValoremP']) && $producto['adValoremP'] === '') {
                                $data['proveedores'][$i]['productos'][$j]['adValoremP'] = null;
                            }
                        }
                    }
                }
            }
            if (empty($data['clienteInfo']['tipoDocumento'])) {
                $data['clienteInfo']['tipoDocumento'] = 'DNI';
            }
            if (!isset($data['clienteInfo']['qtyProveedores']) && !empty($data['proveedores'])) {
                $data['clienteInfo']['qtyProveedores'] = count($data['proveedores']);
            }
            $request->merge($data);

            $request->validate([
                'clienteInfo.nombre' => 'required|string',
                'clienteInfo.tipoDocumento' => 'nullable|string|in:DNI,RUC',
                'clienteInfo.dni' => 'nullable|string',
                'clienteInfo.ruc' => 'nullable|string',
                'clienteInfo.empresa' => 'nullable|string',
                'clienteInfo.whatsapp' => 'required|string', // Para resolver tipo de cliente en backend
                'clienteInfo.correo' => 'nullable|string',
                'clienteInfo.tipoCliente' => 'nullable|string', // Opcional; si no se envía se calcula por whatsapp
                'clienteInfo.qtyProveedores' => 'nullable|integer|min:1',
                'proveedores' => 'required|array|min:1',
                'proveedores.*.cbm' => 'required|numeric|min:0',
                'proveedores.*.peso' => 'required|numeric|min:0',
                'proveedores.*.productos' => 'required|array|min:1',
                'proveedores.*.productos.*.nombre' => 'required|string',
                'proveedores.*.productos.*.precio' => 'required|numeric|min:0',
                'proveedores.*.productos.*.cantidad' => 'required|integer|min:1',
                'proveedores.*.productos.*.antidumpingCU' => 'nullable|numeric|min:0',
                'proveedores.*.productos.*.adValoremP' => 'nullable|numeric|min:0',
                'tarifa' => 'nullable', // Opcional; si no se envía se calcula por tipo cliente + CBM
                'tarifaTotalExtraProveedor' => 'nullable|numeric|min:0',
                'tarifaTotalExtraItem' => 'nullable|numeric|min:0',
                'tarifaDescuento' => 'nullable|numeric|min:0',
                'tipo_cambio' => 'nullable|numeric|min:0'
            ]);

            $data = $request->all();
            $result = $this->calculadoraImportacionService->generarCotizacionParaExport($data);

            if (!$result || empty($result['url']) || empty($result['boleta'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo generar la cotización (Excel/PDF)'
                ], 500);
            }

            $boleta = $result['boleta'];
            $excelUrl = $result['url'];
            $pdfUrl = isset($boleta['url']) ? $boleta['url'] : null;
            $pdfPathRel = isset($boleta['filename']) ? 'boletas/' . $boleta['filename'] : null;
            $excelPathRel = preg_replace('#^/?(storage/)?#', '', $excelUrl);
            if (strpos($excelPathRel, 'templates/') !== 0) {
                $excelPathRel = 'templates/' . basename($excelUrl);
            }

            $export = UserCotizacionExport::create([
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'file_path' => $pdfPathRel,
                'file_url' => $pdfUrl ? (strpos($pdfUrl, 'http') === 0 ? $pdfUrl : url($pdfUrl)) : null,
                'excel_path' => $excelPathRel,
                'excel_url' => strpos($excelUrl, 'http') === 0 ? $excelUrl : url($excelUrl),
                'cliente_nombre' => $data['clienteInfo']['nombre'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cotización exportada',
                'data' => [
                    'export_id' => $export->id,
                    'pdf_url' => $export->file_url,
                    'excel_url' => $export->excel_url,
                    'totalfob' => $result['totalfob'] ?? null,
                    'totalimpuestos' => $result['totalimpuestos'] ?? null,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en exportCotizacion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cotización existente desde calculadora
     */
    private function actualizarCotizacionDesdeCalculadora($calculadora)
    {
        return $this->cotizacionSyncService->actualizarCotizacionDesdeCalculadora($calculadora);
    }

    /**
     * @OA\Get(
     *     path="/calculadora-importacion/{id}",
     *     summary="Obtener cálculo por ID",
     *     tags={"Calculadora Importación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cálculo encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="calculadora", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="nombre_cliente", type="string"),
     *                     @OA\Property(property="tipo_cliente", type="string"),
     *                     @OA\Property(property="qty_proveedores", type="integer"),
     *                     @OA\Property(property="proveedores", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="nombre", type="string"),
     *                             @OA\Property(property="productos", type="array",
     *                                 @OA\Items(type="object",
     *                                     @OA\Property(property="id", type="integer"),
     *                                     @OA\Property(property="nombre", type="string"),
     *                                     @OA\Property(property="precio", type="number"),
     *                                     @OA\Property(property="cantidad", type="integer"),
     *                                     @OA\Property(property="antidumpingCU", type="number"),
     *                                     @OA\Property(property="adValoremP", type="number")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cálculo no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $idInt = (int) $id;
            $payload = $this->cacheService->rememberShow($idInt, function () use ($idInt) {
                $calculadora = $this->calculadoraImportacionService->obtenerCalculo($idInt);

                if (!$calculadora) {
                    return [
                        'success' => false,
                        'message' => 'Cálculo no encontrado',
                        '_http_code' => 404,
                    ];
                }

                $this->ordenarProveedoresPorId($calculadora);
                $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);

                $tcYuanActual = null;
                if ($calculadora->contenedor && $calculadora->contenedor->relationLoaded('tcYuan') && $calculadora->contenedor->tcYuan) {
                    $tcYuanActual = (float) $calculadora->contenedor->tcYuan->tc_yuan;
                }

                return [
                    'success' => true,
                    'data' => [
                        'calculadora' => $calculadora,
                        'totales' => $totales,
                        'tc_yuan_actual' => $tcYuanActual,
                    ],
                ];
            });

            if (isset($payload['_http_code'])) {
                $code = (int) $payload['_http_code'];
                unset($payload['_http_code']);
                return response()->json($payload, $code);
            }

            // Asegura orden consistente incluso cuando el payload viene desde caché previa.
            if (
                isset($payload['success'], $payload['data']['calculadora']) &&
                $payload['success'] === true &&
                is_object($payload['data']['calculadora'])
            ) {
                $this->ordenarProveedoresPorId($payload['data']['calculadora']);
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener cálculos por cliente
     */
    public function getCalculosPorCliente(Request $request)
    {
        try {
            $request->validate([
                'dni' => 'required|string'
            ]);

            $dni = (string) $request->dni;
            $payload = $this->cacheService->rememberCalculosPorCliente($dni, function () use ($dni) {
                $calculos = $this->calculadoraImportacionService->obtenerCalculosPorCliente($dni);
                return [
                    'success' => true,
                    'data' => $calculos,
                    'total' => $calculos->count(),
                ];
            });

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cálculos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cálculo
     */
    public function destroy($id)
    {
        try {
            $calculadora = CalculadoraImportacion::find($id);
            $eliminado = $this->calculadoraImportacionService->eliminarCalculo($id);


            if ($eliminado) {
                $this->cacheService->invalidateAfterWrite($calculadora, [
                    'dni_cliente' => $calculadora->dni_cliente ?? null,
                    'whatsapp' => $calculadora->whatsapp_cliente ?? null,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Cálculo eliminado exitosamente'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar el cálculo'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function duplicate($id)
    {
        try {
            Log::info("Iniciando duplicación de calculadora ID: {$id}");

            $calculadora = CalculadoraImportacion::with(['proveedores.productos'])->find($id);

            if (!$calculadora) {
                Log::warning("Calculadora no encontrada con ID: {$id}");
                return response()->json([
                    'success' => false,
                    'message' => 'Calculadora no encontrada'
                ], 404);
            }

            Log::info("Calculadora encontrada. Proveedores: " . $calculadora->proveedores->count());

            // Duplicar la calculadora principal
            $newCalculadora = $calculadora->replicate();
            $newCalculadora->id_carga_consolidada_contenedor = null;
            $newCalculadora->estado = 'PENDIENTE'; // Resetear estado
            $newCalculadora->id_cotizacion = null;
            $newCalculadora->save();

            Log::info("Nueva calculadora creada con ID: {$newCalculadora->id}");

            // Duplicar proveedores y sus productos
            foreach ($calculadora->proveedores as $proveedor) {
                Log::info("Duplicando proveedor ID: {$proveedor->id}");

                $newProveedor = $proveedor->replicate();
                $newProveedor->id_calculadora_importacion = $newCalculadora->id;
                $newProveedor->save();

                Log::info("Nuevo proveedor creado con ID: {$newProveedor->id}");

                // Duplicar productos del proveedor
                foreach ($proveedor->productos as $producto) {
                    Log::info("Duplicando producto ID: {$producto->id} del proveedor ID: {$proveedor->id}");

                    $newProducto = $producto->replicate();
                    $newProducto->id_proveedor = $newProveedor->id;
                    $newProducto->save();

                    Log::info("Nuevo producto creado con ID: {$newProducto->id}");
                }
            }

            Log::info("Duplicación completada exitosamente");

            $this->cacheService->invalidateAfterWrite($newCalculadora, [
                'dni_cliente' => $newCalculadora->dni_cliente ?? null,
                'whatsapp' => $newCalculadora->whatsapp_cliente ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cálculo duplicado exitosamente',
                'data' => [
                    'id_original' => $id,
                    'id_nuevo' => $newCalculadora->id
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error al duplicar calculadora ID {$id}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar el cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function changeEstado(Request $request, $id)
    {
        try {
            $estado = $request->estado;
            $calculadora = CalculadoraImportacion::find($id);
            $calculadora->estado = $estado;

            if ($estado === 'COTIZADO') {
                //validate if cod_cotizacion is not null
                if (!$calculadora->cod_cotizacion) {
                    $lastCotizacion = CalculadoraImportacion::where('cod_cotizacion', 'like', 'CO%')
                        ->where('id', '!=', $id)
                        ->orderBy('cod_cotizacion', 'desc')
                        ->first();

                    $lastSequentialNumber = 0;
                    if ($lastCotizacion && preg_match('/(\d{4})$/', $lastCotizacion->cod_cotizacion, $matches)) {
                        $lastSequentialNumber = intval($matches[1]);
                    }
                    $newSequentialNumber = $lastSequentialNumber ? $lastSequentialNumber + 1 : 1;
                    $calculadora->cod_cotizacion = 'CO' . date('m') . date('y') . str_pad($newSequentialNumber, 4, '0', STR_PAD_LEFT);

                    $calculadora->save();
                }

                // Modificar el Excel para agregar fechas de pago y código de cotización (D7)
                if ($calculadora->url_cotizacion && $calculadora->id_carga_consolidada_contenedor) {
                    $this->modificarExcelConFechas($calculadora);
                }

                // Regenerar boleta PDF con el código de cotización actualizado en el Excel (PLANTILLA_COTIZACION_INICIAL_CALCULADORA.html)
                if ($calculadora->url_cotizacion) {
                    $boletaInfo = $this->calculadoraImportacionService->regenerarBoletaPdf($calculadora);
                    if ($boletaInfo && !empty($boletaInfo['url'])) {
                        $calculadora->url_cotizacion_pdf = $boletaInfo['url'];
                        Log::info('Boleta PDF regenerada al pasar a COTIZADO', ['calculadora_id' => $calculadora->id]);
                    }
                }

                if (!$calculadora->id_cotizacion && $calculadora->id_carga_consolidada_contenedor && $calculadora->url_cotizacion) {
                    // Descargar el archivo Excel desde la URL
                    $fileUrl = $calculadora->url_cotizacion;
                    $fileContents = $this->downloadFileFromUrl($fileUrl);

                    if ($fileContents) {
                        // Crear archivo temporal
                        $tempPath = storage_path('app/temp');
                        if (!file_exists($tempPath)) {
                            mkdir($tempPath, 0755, true);
                        }

                        $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
                        $tempFileName = uniqid('calculadora_') . '.' . $extension;
                        $tempFilePath = $tempPath . '/' . $tempFileName;
                        file_put_contents($tempFilePath, $fileContents);

                        // Crear un UploadedFile simulado
                        $uploadedFile = new \Illuminate\Http\UploadedFile(
                            $tempFilePath,
                            basename($fileUrl),
                            mime_content_type($tempFilePath),
                            null,
                            true
                        );

                        // Crear Request con el archivo
                        $storeRequest = new Request();
                        $storeRequest->merge(['id_contenedor' => $calculadora->id_carga_consolidada_contenedor]);
                        $storeRequest->files->set('cotizacion', $uploadedFile);

                        // Guardar el usuario actual
                        $currentUserId = auth()->id();

                        // Llamar al método store del CotizacionController
                        $cotizacionController = app(CotizacionController::class);
                        $response = $cotizacionController->storeFromCalculadora($storeRequest);
                        $responseData = json_decode($response->getContent(), true);

                        // Limpiar archivo temporal
                        if (file_exists($tempFilePath)) {
                            unlink($tempFilePath);
                        }

                        if (isset($responseData['id']) && $responseData['status'] === 'success') {
                            $cotizacionId = $responseData['id'];

                            // Actualizar la cotización: id_usuario, from_calculator y Excel (cotizacion_file_url)
                            Cotizacion::where('id', $cotizacionId)->update([
                                'id_usuario' => $calculadora->id_usuario ?? $currentUserId,
                                'from_calculator' => true,
                                'cotizacion_file_url' => $calculadora->url_cotizacion,
                                'es_imo' => (bool) ($data['es_imo'] ?? $calculadora->es_imo ?? false),
                            ]);

                            $calculadora->id_cotizacion = $cotizacionId;
                            $calculadora->save();

                            Log::info('Cotización creada desde calculadora via store()', [
                                'calculadora_id' => $calculadora->id,
                                'cotizacion_id' => $cotizacionId,
                                'id_usuario_calculadora' => $calculadora->id_usuario
                            ]);
                        } else {
                            Log::error('Error al crear cotización desde calculadora', [
                                'calculadora_id' => $calculadora->id,
                                'response' => $responseData
                            ]);
                        }
                    } else {
                        Log::error('No se pudo descargar el archivo de cotización', [
                            'calculadora_id' => $calculadora->id,
                            'url' => $fileUrl
                        ]);
                    }
                }

                // Sincronizar Excel en contenedor_consolidado_cotizacion (ya existía id_cotizacion o recién creada)
                if ($calculadora->id_cotizacion && $calculadora->url_cotizacion) {
                    Cotizacion::where('id', $calculadora->id_cotizacion)->update([
                        'cotizacion_file_url' => $calculadora->url_cotizacion,
                    ]);
                    Log::info('cotizacion_file_url actualizado al pasar a COTIZADO', ['cotizacion_id' => $calculadora->id_cotizacion]);
                }

                // Si ya tenía cotización vinculada: asegurar que los proveedores de la calculadora
                // tengan code_supplier sincronizado desde cccp, para que al borrar uno se elimine el correcto
                if ($calculadora->id_cotizacion) {
                    $this->sincronizarCodeSupplierCalculadoraDesdeCotizacion($calculadora, $calculadora->id_cotizacion);
                }
            }
            $calculadora->save();

            $this->cacheService->invalidateAfterWrite($calculadora, [
                'dni_cliente' => $calculadora->dni_cliente ?? null,
                'whatsapp' => $calculadora->whatsapp_cliente ?? null,
            ]);
            return response()->json(['success' => true, 'message' => 'Estado cambiado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cambiar el estado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Vincula / crea la cotización en carga consolidada desde una fila de calculadora,
     * sin necesidad de cambiar manualmente el estado en el front a "COTIZADO".
     *
     * Esto permite que el front muestre un indicador de "desvinculada" y un botón
     * para ejecutar el sync de la cotización (y luego habilitar la vista de documentos).
     */
    public function vincularCotizacionDesdeCalculadora($id)
    {
        try {
            $id = (int) $id;
            $calculadora = CalculadoraImportacion::find($id);

            if (!$calculadora) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calculadora no encontrada'
                ], 404);
            }

            if (!$calculadora->url_cotizacion || !$calculadora->id_carga_consolidada_contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'La calculadora no tiene URL de cotización o contenedor consolidado asociado'
                ], 400);
            }

            // Generar cod_cotizacion si aún no existe (igual que en changeEstado -> COTIZADO)
            if (!$calculadora->cod_cotizacion) {
                $lastCotizacion = CalculadoraImportacion::where('cod_cotizacion', 'like', 'CO%')
                    ->where('id', '!=', $id)
                    ->orderBy('cod_cotizacion', 'desc')
                    ->first();

                $lastSequentialNumber = 0;
                if ($lastCotizacion && preg_match('/(\d{4})$/', $lastCotizacion->cod_cotizacion, $matches)) {
                    $lastSequentialNumber = intval($matches[1]);
                }

                $newSequentialNumber = $lastSequentialNumber ? $lastSequentialNumber + 1 : 1;
                $calculadora->cod_cotizacion = 'CO' . date('m') . date('y') . str_pad($newSequentialNumber, 4, '0', STR_PAD_LEFT);
                $calculadora->save();
            }

            // Modificar el Excel para agregar fechas de pago y código de cotización (D7)
            if ($calculadora->url_cotizacion && $calculadora->id_carga_consolidada_contenedor) {
                $this->modificarExcelConFechas($calculadora);
            }

            // Regenerar boleta PDF para reflejar el Excel actualizado (si aplica)
            if ($calculadora->url_cotizacion) {
                $boletaInfo = $this->calculadoraImportacionService->regenerarBoletaPdf($calculadora);
                if ($boletaInfo && !empty($boletaInfo['url'])) {
                    $calculadora->url_cotizacion_pdf = $boletaInfo['url'];
                    $calculadora->save();
                }
            }

            // Si no existe cotización vinculada, crear desde la Excel
            if (!$calculadora->id_cotizacion) {
                $this->cotizacionSyncService->crearCotizacionDesdeCalculadoraExcel($calculadora);
                $calculadora->refresh();
            }

            // Actualizar cotización relacionada con el nuevo Excel (archivo + datos mínimos)
            if ($calculadora->id_cotizacion && $calculadora->url_cotizacion) {
                Cotizacion::where('id', $calculadora->id_cotizacion)->update([
                    'cotizacion_file_url' => $calculadora->url_cotizacion,
                ]);
            }

            // Asegurar que los proveedores de la calculadora queden sincronizados desde la cotización (emparejando por orden)
            if ($calculadora->id_cotizacion) {
                $this->sincronizarCodeSupplierCalculadoraDesdeCotizacion($calculadora, $calculadora->id_cotizacion);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cotización vinculada correctamente',
                'data' => [
                    'id_cotizacion' => $calculadora->id_cotizacion
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en vincularCotizacionDesdeCalculadora: ' . $e->getMessage(), [
                'id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al vincular la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincroniza `code_supplier` y `id_proveedor` desde
     * `contenedor_consolidado_cotizacion_proveedores` (cccp) hacia
     * `calculadora_importacion_proveedores`, emparejando por orden (misma lógica usada
     * para `code_supplier`).
     */
    private function sincronizarCodeSupplierCalculadoraDesdeCotizacion(CalculadoraImportacion $calculadora, int $cotizacionId): void
    {
        $proveedoresCotizacion = \App\Models\CargaConsolidada\CotizacionProveedor::where('id_cotizacion', $cotizacionId)
            ->orderBy('id')
            ->get(['id', 'code_supplier']);

        $proveedoresCalculadora = $calculadora->proveedores()
            ->orderBy('id')
            ->get(['id', 'code_supplier', 'id_proveedor']);

        if ($proveedoresCotizacion->count() !== $proveedoresCalculadora->count()) {
            Log::warning('Sincronizar code_supplier: distinta cantidad de proveedores', [
                'calculadora_id' => $calculadora->id,
                'cotizacion_id' => $cotizacionId,
                'cccp' => $proveedoresCotizacion->count(),
                'calculadora' => $proveedoresCalculadora->count(),
            ]);
        }

        foreach ($proveedoresCotizacion as $index => $provCotizacion) {
            $codeSupplier = $provCotizacion->code_supplier;
            if (isset($proveedoresCalculadora[$index])) {
                $updateData = [
                    'id_proveedor' => $provCotizacion->id,
                ];

                if (!empty($codeSupplier)) {
                    $updateData['code_supplier'] = $codeSupplier;
                }

                $proveedoresCalculadora[$index]->update($updateData);

                Log::info('Proveedor sincronizado a calculadora_importacion_proveedores', [
                    'calculadora_proveedor_id' => $proveedoresCalculadora[$index]->id,
                    'code_supplier' => $codeSupplier,
                    'id_proveedor' => $provCotizacion->id,
                ]);
            }
        }
    }

    /**
     * Ordena proveedores y productos por ID para respuestas consistentes.
     */
    private function ordenarProveedoresPorId($calculadora): void
    {
        if (!$calculadora || !$calculadora->relationLoaded('proveedores')) {
            return;
        }

        $proveedoresOrdenados = $calculadora->proveedores->sortBy('id')->values();

        $proveedoresOrdenados->each(function ($proveedor) {
            if ($proveedor && $proveedor->relationLoaded('productos')) {
                $proveedor->setRelation(
                    'productos',
                    $proveedor->productos->sortBy('id')->values()
                );
            }
        });

        $calculadora->setRelation('proveedores', $proveedoresOrdenados);
    }

    /**
     * Enviar mensajes de WhatsApp cuando el estado cambie a COTIZADO
     */
    private function sendWhatsAppMessage($whatsappCliente, $calculadora)
    {
        $this->whatsappService->sendCotizacionSequence($whatsappCliente, $calculadora);
    }

    /**
     * Formatear número de WhatsApp para la API
     */
    private function formatWhatsAppNumber($whatsapp)
    {
        return $this->whatsappService->formatWhatsAppNumber($whatsapp);
    }

    /**
     * Obtener ruta del archivo PDF desde la URL
     */
    private function getPdfPathFromUrl($url)
    {
        return $this->whatsappService->getPdfPathFromUrl($url);
    }

    /**
     * Descargar archivo desde URL (local o remota)
     */
    private function downloadFileFromUrl($fileUrl)
    {
        return $this->excelService->downloadFileFromUrl($fileUrl);
    }

    /**
     * Generar códigos de proveedor y escribirlos en el Excel (fila 3)
     * Usa la misma estructura que CotizacionController
     */
    private function generarCodigosProveedorEnExcel($calculadora)
    {
        $this->excelService->generarCodigosProveedorEnExcel($calculadora);
    }

    /**
     * Escribir códigos existentes en el Excel (sin generar nuevos)
     * Solo se usa cuando se actualiza una calculadora que ya tiene códigos
     */
    private function escribirCodigosExistentesEnExcel($calculadora)
    {
        $this->excelService->escribirCodigosExistentesEnExcel($calculadora);
    }

    /**
     * Generar código de proveedor usando la misma estructura que CotizacionController
     */
    private function generateCodeSupplier($string, $carga, $rowCount, $index)
    {
        return $this->excelService->generateCodeSupplier($string, $carga, $rowCount, $index);
    }

    /**
     * Incrementar columna (helper) - misma implementación que CotizacionController
     */
    private function incrementColumn($column, $increment = 1)
    {
        return $this->excelService->incrementColumn($column, $increment);
    }

    /**
     * Modificar Excel para agregar fechas de pago en columna P
     */
    private function modificarExcelConFechas($calculadora)
    {
        $this->excelService->modificarExcelConFechas($calculadora);
    }

    /**
     * Obtener ruta del archivo desde URL
     */
    private function getFilePathFromUrl($url)
    {
        return $this->excelService->getFilePathFromUrl($url);
    }
}
