<?php

namespace App\Http\Controllers\CalculadoraImportacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\CalculadoraImportacion;
use App\Services\BaseDatos\Clientes\ClienteService;
use App\Services\CalculadoraImportacionService;
use App\Services\ResumenCostosImageService;
use App\Models\CalculadoraTarifasConsolidado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Traits\WhatsappTrait;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Http\Controllers\CargaConsolidada\CotizacionController;
use Illuminate\Support\Str;

class CalculadoraImportacionController extends Controller
{
    use WhatsappTrait;
    protected $clienteService;
    protected $calculadoraImportacionService;

    public function __construct(
        ClienteService $clienteService,
        CalculadoraImportacionService $calculadoraImportacionService
    ) {
        $this->clienteService = $clienteService;
        $this->calculadoraImportacionService = $calculadoraImportacionService;
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
            // Obtener clientes con teléfono
            $whatsapp = $request->whatsapp;

            // Normalizar el número de búsqueda
            $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $whatsapp);

            // Si empieza con 51 y tiene más de 9 dígitos, remover prefijo
            if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $matches)) {
                $telefonoNormalizado = $matches[1];
            }

            $clientes = Cliente::where('telefono', '!=', null)
                ->where('telefono', '!=', '')
                ->where(function ($query) use ($whatsapp, $telefonoNormalizado) {
                    $query->where('telefono', 'like', '%' . $whatsapp . '%');

                    if (!empty($telefonoNormalizado)) {
                        $query->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"])
                            ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%51{$telefonoNormalizado}%"]);
                    }
                })
                ->limit(100)
                ->get();

            if ($clientes->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No se encontraron clientes con teléfono'
                ]);
            }

            // Obtener IDs de clientes
            $clienteIds = $clientes->pluck('id')->toArray();

            // Obtener servicios en lote usando la lógica del ClienteService
            $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

            // Transformar datos de clientes con categoría
            $clientesTransformados = [];
            foreach ($clientes as $cliente) {
                $servicios = $serviciosPorCliente[$cliente->id] ?? [];
                $categoria = $this->determinarCategoriaCliente($servicios);

                $clientesTransformados[] = [
                    'id' => $cliente->id,
                    'value' => $cliente->telefono,
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento,
                    'correo' => $cliente->correo,
                    'label' => $cliente->telefono,
                    'ruc' => $cliente->ruc,
                    'empresa' => $cliente->empresa,
                    'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                    'categoria' => $categoria,
                    'total_servicios' => count($servicios),
                    'primer_servicio' => !empty($servicios) ? [
                        'servicio' => $servicios[0]['servicio'],
                        'fecha' => Carbon::parse($servicios[0]['fecha'])->format('d/m/Y'),
                        'categoria' => $categoria
                    ] : null,
                    'servicios' => collect($servicios)->map(function ($servicio) use ($categoria) {
                        return [
                            'servicio' => $servicio['servicio'],
                            'fecha' => Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                            'categoria' => $categoria,
                            'monto' => $servicio['monto'] ?? null
                        ];
                    })
                ];
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
     * Obtener servicios en lote para múltiples clientes
     * Copiado del ClienteService para mantener consistencia
     */
    private function obtenerServiciosEnLote($clienteIds)
    {
        if (empty($clienteIds)) {
            return [];
        }

        $serviciosPorCliente = [];

        // Obtener servicios de pedido_curso
        $pedidosCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2)
            ->whereIn('pc.id_cliente', $clienteIds)
            ->select(
                'pc.id_cliente',
                'e.Fe_Registro as fecha',
                DB::raw("'Curso' as servicio"),
                DB::raw('NULL as monto')
            )
            ->get();

        // Obtener servicios de contenedor_consolidado_cotizacion
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereIn('id_cliente', $clienteIds)
            ->select(
                'id_cliente',
                'fecha',
                DB::raw("'Consolidado' as servicio"),
                'monto'
            )
            ->get();

        // Combinar y organizar por cliente
        foreach ($pedidosCurso as $pedido) {
            $serviciosPorCliente[$pedido->id_cliente][] = [
                'servicio' => $pedido->servicio,
                'fecha' => $pedido->fecha,
                'monto' => $pedido->monto
            ];
        }

        foreach ($cotizaciones as $cotizacion) {
            $serviciosPorCliente[$cotizacion->id_cliente][] = [
                'servicio' => $cotizacion->servicio,
                'fecha' => $cotizacion->fecha,
                'monto' => $cotizacion->monto
            ];
        }

        // Ordenar servicios por fecha para cada cliente
        foreach ($serviciosPorCliente as $clienteId => &$servicios) {
            usort($servicios, function ($a, $b) {
                return strtotime($a['fecha']) - strtotime($b['fecha']);
            });
        }

        return $serviciosPorCliente;
    }

    /**
     * Determinar categoría del cliente basada en sus servicios
     * Copiado del ClienteService para mantener consistencia
     */
    private function determinarCategoriaCliente($servicios)
    {
        $totalServicios = count($servicios);

        if ($totalServicios === 0) {
            return 'NUEVO';
        }

        if ($totalServicios === 1) {
            return 'RECURRENTE';
        }

        // Obtener la fecha del último servicio
        $ultimoServicio = end($servicios);
        $fechaUltimoServicio = Carbon::parse($ultimoServicio['fecha']);
        $hoy = Carbon::now();
        $mesesDesdeUltimaCompra = $fechaUltimoServicio->diffInMonths($hoy);

        // Si la última compra fue hace más de 6 meses, es Inactivo
        if ($mesesDesdeUltimaCompra > 6) {
            return 'INACTIVO';
        }

        // Para clientes con múltiples servicios
        if ($totalServicios >= 2) {
            // Calcular frecuencia promedio de compras
            $primerServicio = $servicios[0];
            $fechaPrimerServicio = Carbon::parse($primerServicio['fecha']);
            $mesesEntrePrimeraYUltima = $fechaPrimerServicio->diffInMonths($fechaUltimoServicio);
            $frecuenciaPromedio = $mesesEntrePrimeraYUltima / ($totalServicios - 1);

            // Si compra cada 2 meses o menos Y la última compra fue hace ≤ 2 meses
            if ($frecuenciaPromedio <= 2 && $mesesDesdeUltimaCompra <= 2) {
                return 'PREMIUM';
            }
            // Si tiene múltiples compras Y la última fue hace ≤ 6 meses
            else if ($mesesDesdeUltimaCompra <= 6) {
                return 'RECURRENTE';
            }
        }

        return 'INACTIVO';
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
                    'value' => $tarifa->tipoCliente->nombre
                ];
            });
            return response()->json([
                'success' => true,
                'data' => $tarifas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tarifas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los cálculos de importación
     */
    public function index(Request $request)
    {
        try {
            $query = CalculadoraImportacion::with(['proveedores.productos', 'cliente', 'contenedor', 'creador', 'vendedor', 'cotizacion']);

            //filter optional campania=54&estado_calculadora=PENDIENTE
            if ($request->has('campania') && $request->campania) {
                $query->where('id_carga_consolidada_contenedor', $request->campania);
            }
            if ($request->has('estado_calculadora') && $request->estado_calculadora) {
                $query->where('estado', $request->estado_calculadora);
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
                $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);
                $calculadora->totales = $totales;
                $calculadora->url_cotizacion = $this->generateUrl($calculadora->url_cotizacion);
                $calculadora->url_cotizacion_pdf = $this->generateUrl($calculadora->url_cotizacion_pdf);
                $calculadora->nombre_creador = optional($calculadora->creador)->No_Nombres_Apellidos;
                //vendedor id_usuario
                $calculadora->nombre_vendedor = optional($calculadora->vendedor)->No_Nombres_Apellidos;
                $calculadora->carga_contenedor = '  #' . optional($calculadora->contenedor)->carga . '-' . ($calculadora->contenedor ? Carbon::parse($calculadora->contenedor->f_inicio)->format('Y') : '2025');
                $calculadora->estado_cotizador=optional($calculadora->cotizacion)->estado_cotizador;
                $calculadora->cod_contract=optional($calculadora->cotizacion)->cod_contract;
            }
            //get filters estado calculadora, all contenedores carga id,
            //get all containers label=carga value=id (solo del año actual)
            $anioActual = Carbon::now()->year;
            $contenedores = Contenedor::whereYear('f_inicio', $anioActual)->get();
            $contenedores = $contenedores->map(function ($contenedor) {
                return [
                    'id' => $contenedor->id,
                    'label' => $contenedor->carga,
                    'value' => $contenedor->id
                ];
            });
            //get all estados calculadora label=estado value=estado
            $estadoCalculadora = CalculadoraImportacion::getEstadosDisponiblesFilter();

            // Contar cotizaciones realizadas (estado COTIZADO y CONFIRMADO)
            $cotizacionesRealizadas = CalculadoraImportacion::whereIn('estado', ['COTIZADO', 'CONFIRMADO'])->count();

            //Contar cotizaciones pendientes (estado PENDIENTE)
            $cotizacionesPendientes = CalculadoraImportacion::where('estado', 'PENDIENTE')->count();

            //Contar cotizaciones vendidas (estado CONFIRMADO)
            $cotizacionesVendidas = CalculadoraImportacion::where('estado', 'CONFIRMADO')->count();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $calculos->currentPage(),
                    'last_page' => $calculos->lastPage(),
                    'per_page' => $calculos->perPage(),
                    'total' => $calculos->total(),
                    'from' => $calculos->firstItem(),
                    'to' => $calculos->lastItem()
                ],
                'headers' => [
                    'cotizaciones_pendientes' => [
                        'value' => $cotizacionesPendientes,
                        'label' => 'Cotizaciones Pendientes'
                    ],
                    'cotizaciones_realizadas' => [
                        'value' => $cotizacionesRealizadas,
                        'label' => 'Cotizaciones Realizadas'
                    ],
                    'cotizaciones_vendidas' => [
                        'value' => $cotizacionesVendidas,
                        'label' => 'Cotizaciones Vendidas'
                    ],
                ],
                'filters' => [
                    'contenedores' => $contenedores,
                    'estadoCalculadora' => $estadoCalculadora
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cálculos: ' . $e->getMessage()
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
            // Convertir strings vacíos a null en campos numéricos de productos
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
            $request->merge($data);

            $request->validate([
                'id' => 'nullable|integer|exists:calculadora_importacion,id',
                'clienteInfo.nombre' => 'required|string',
                'clienteInfo.tipoDocumento' => 'required|string|in:DNI,RUC',
                'clienteInfo.dni' => 'sometimes:clienteInfo.tipoDocumento,DNI|string|nullable',
                'clienteInfo.ruc' => 'sometimes:clienteInfo.tipoDocumento,RUC|string|nullable',
                'clienteInfo.empresa' => 'required_if:clienteInfo.tipoDocumento,RUC|string|nullable',
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
                'proveedores.*.productos.*.cantidad' => 'required|integer|min:1',
                'proveedores.*.productos.*.antidumpingCU' => 'nullable|numeric|min:0',
                'proveedores.*.productos.*.adValoremP' => 'nullable|numeric|min:0',
                'tarifaTotalExtraProveedor' => 'nullable|numeric|min:0',
                'tarifaTotalExtraItem' => 'nullable|numeric|min:0'
            ]);

            $data = $request->all();
            $data['created_by'] = auth()->id();

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
                            $this->escribirCodigosExistentesEnExcel($calculadora);
                        }
                    }
                    
                    // Actualizar la cotización relacionada
                    $this->actualizarCotizacionDesdeCalculadora($calculadora);
                }

                // Modificar el Excel para agregar fechas de pago (también al actualizar)
                if ($calculadora->url_cotizacion && $calculadora->id_carga_consolidada_contenedor) {
                    $this->modificarExcelConFechas($calculadora);
                }

                $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);

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
                $this->modificarExcelConFechas($calculadora);
            }

            $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);

            return response()->json([
                'success' => true,
                'message' => 'Cálculo guardado exitosamente',
                'data' => [
                    'calculadora' => $calculadora,
                    'totales' => $totales
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cotización existente desde calculadora
     */
    private function actualizarCotizacionDesdeCalculadora($calculadora)
    {
        try {
            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::error('No se pudo descargar archivo para actualizar cotización', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
                return false;
            }

            // Crear archivo temporal
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('calculadora_update_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            // Preparar datos del archivo
            $fileData = [
                'name' => basename($fileUrl),
                'type' => mime_content_type($tempFilePath),
                'tmp_name' => $tempFilePath,
                'error' => 0,
                'size' => filesize($tempFilePath)
            ];

            // Llamar a updateFromCalculadora del CotizacionController (método específico para calculadora)
            $cotizacionController = app(CotizacionController::class);
            $result = $cotizacionController->updateFromCalculadora($calculadora->id_cotizacion, $fileData);

            // Limpiar archivo temporal
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            if ($result === "success") {
                // Actualizar from_calculator y id_usuario
                Cotizacion::where('id', $calculadora->id_cotizacion)->update([
                    'id_usuario' => $calculadora->id_usuario,
                    'from_calculator' => true
                ]);

                Log::info('Cotización actualizada desde calculadora', [
                    'calculadora_id' => $calculadora->id,
                    'cotizacion_id' => $calculadora->id_cotizacion
                ]);
                return true;
            }

            Log::error('Error al actualizar cotización desde calculadora', [
                'calculadora_id' => $calculadora->id,
                'cotizacion_id' => $calculadora->id_cotizacion,
                'result' => $result
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Excepción al actualizar cotización: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id
            ]);
            return false;
        }
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
            $calculadora = $this->calculadoraImportacionService->obtenerCalculo($id);

            if (!$calculadora) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cálculo no encontrado'
                ], 404);
            }

            $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);

            return response()->json([
                'success' => true,
                'data' => [
                    'calculadora' => $calculadora,
                    'totales' => $totales
                ]
            ]);
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

            $calculos = $this->calculadoraImportacionService->obtenerCalculosPorCliente($request->dni);

            return response()->json([
                'success' => true,
                'data' => $calculos,
                'total' => $calculos->count()
            ]);
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
            $eliminado = $this->calculadoraImportacionService->eliminarCalculo($id);


            if ($eliminado) {
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

                            // Actualizar la cotización con el id_usuario de la calculadora y marcar from_calculator
                            Cotizacion::where('id', $cotizacionId)->update([
                                'id_usuario' => $calculadora->id_usuario ?? $currentUserId,
                                'from_calculator' => true
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
            }
            $calculadora->save();
            return response()->json(['success' => true, 'message' => 'Estado cambiado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cambiar el estado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Enviar mensajes de WhatsApp cuando el estado cambie a COTIZADO
     */
    private function sendWhatsAppMessage($whatsappCliente, $calculadora)
    {
        try {
            if (!$whatsappCliente) {
                Log::warning('No se puede enviar WhatsApp: número no disponible', [
                    'calculadora_id' => $calculadora->id,
                    'cliente' => $calculadora->nombre_cliente
                ]);
                return;
            }

            // Formatear número de WhatsApp
            $phoneNumberId = $this->formatWhatsAppNumber($whatsappCliente);

            // Primer mensaje: Información de la cotización
            $primerMensaje = "Bien, Te envío la cotización de tu importación, en el documento podrás ver el detalle de los costos.\n\n⚠️ Nota: Leer Términos y Condiciones.\n\n🎥 Video Explicativo:\n▶️ https://youtu.be/H7U-_5wCWd4";

            $this->sendMessage($primerMensaje, $phoneNumberId, 2);
            Log::info('Primer mensaje de WhatsApp enviado', ['calculadora_id' => $calculadora->id]);



            // Segundo mensaje: Enviar PDF de la cotización
            if ($calculadora->url_cotizacion_pdf) {
                $pdfPath = $this->getPdfPathFromUrl($calculadora->url_cotizacion_pdf);
                if ($pdfPath && file_exists($pdfPath)) {
                    $this->sendMedia($pdfPath, 'application/pdf', null, $phoneNumberId, 3);
                    Log::info('PDF de cotización enviado por WhatsApp', ['calculadora_id' => $calculadora->id]);
                } else {
                    Log::warning('No se pudo enviar PDF: archivo no encontrado', [
                        'calculadora_id' => $calculadora->id,
                        'url' => $calculadora->url_cotizacion_pdf,
                        'path' => $pdfPath
                    ]);
                }
            }



            // Tercer mensaje: Información sobre pagos
            $tercerMensaje = "📊 Aquí te paso el resumen de cuánto te saldría cada modelo y el total de inversión\n\n💰 El primer pago es el SERVICIO DE IMPORTACIÓN y se realiza antes del zarpe de buque 🚢";

            $this->sendMessage($tercerMensaje, $phoneNumberId, 2);
            Log::info('Tercer mensaje de WhatsApp enviado', ['calculadora_id' => $calculadora->id]);

            // Cuarto mensaje: Enviar imagen del resumen de costos
            $resumenCostosService = new ResumenCostosImageService();
            $imagenResumen = $resumenCostosService->generateResumenCostosImage($calculadora);

            if ($imagenResumen) {
                $this->sendMedia($imagenResumen['path'], 'image/png', '📊 Resumen detallado de costos y pagos', $phoneNumberId, 4);
                Log::info('Imagen de resumen de costos enviada por WhatsApp', [
                    'calculadora_id' => $calculadora->id,
                    'image_path' => $imagenResumen['path']
                ]);
            } else {
                Log::warning('No se pudo generar la imagen del resumen de costos', [
                    'calculadora_id' => $calculadora->id
                ]);
            }

            Log::info('Secuencia de mensajes de WhatsApp completada exitosamente', [
                'calculadora_id' => $calculadora->id,
                'cliente' => $calculadora->nombre_cliente,
                'whatsapp' => $whatsappCliente
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar mensajes de WhatsApp: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'cliente' => $calculadora->nombre_cliente,
                'whatsapp' => $whatsappCliente,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Formatear número de WhatsApp para la API
     */
    private function formatWhatsAppNumber($whatsapp)
    {
        // Remover caracteres no numéricos
        $cleanNumber = preg_replace('/[^0-9]/', '', $whatsapp);

        // Si empieza con 0, removerlo
        if (substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
        }

        // Si no empieza con 51 (código de Perú), agregarlo
        if (substr($cleanNumber, 0, 2) !== '51') {
            $cleanNumber = '51' . $cleanNumber;
        }

        return $cleanNumber . '@c.us';
    }

    /**
     * Obtener ruta del archivo PDF desde la URL
     */
    private function getPdfPathFromUrl($url)
    {
        try {
            // Si es una URL completa, extraer la ruta relativa
            if (strpos($url, 'http') === 0) {
                $parsedUrl = parse_url($url);
                $path = $parsedUrl['path'] ?? '';

                // Remover /storage/ del inicio si existe
                if (strpos($path, '/storage/') === 0) {
                    $path = substr($path, 9); // Remover '/storage/'
                }

                return storage_path('app/public/' . $path);
            }

            // Si es una ruta relativa
            if (strpos($url, '/storage/') === 0) {
                $path = substr($url, 9); // Remover '/storage/'
                return storage_path('app/public/' . $path);
            }

            // Si es solo el nombre del archivo
            return storage_path('app/public/boletas/' . $url);
        } catch (\Exception $e) {
            Log::error('Error al obtener ruta del PDF: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * Descargar archivo desde URL (local o remota)
     */
    private function downloadFileFromUrl($fileUrl)
    {
        try {
            // Si es una URL completa
            if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                // Intentar con file_get_contents
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 60,
                        'method' => 'GET',
                        'header' => 'User-Agent: Mozilla/5.0'
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);

                $content = @file_get_contents($fileUrl, false, $context);
                if ($content !== false && strlen($content) > 0) {
                    return $content;
                }

                // Fallback con cURL
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $fileUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                    $content = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($content !== false && $httpCode == 200 && strlen($content) > 0) {
                        return $content;
                    }
                }
            }

            // Si es una ruta de storage
            if (strpos($fileUrl, '/storage/') !== false) {
                $path = preg_replace('#^.*/storage/#', '', $fileUrl);
                $storagePath = storage_path('app/public/' . $path);
                if (file_exists($storagePath)) {
                    return file_get_contents($storagePath);
                }
            }

            // Si es una ruta local directa
            if (file_exists($fileUrl)) {
                return file_get_contents($fileUrl);
            }

            // Intentar en storage público
            $publicPath = storage_path('app/public/' . ltrim($fileUrl, '/'));
            if (file_exists($publicPath)) {
                return file_get_contents($publicPath);
            }

            Log::error('No se pudo encontrar el archivo: ' . $fileUrl);
            return null;
        } catch (\Exception $e) {
            Log::error('Error al descargar archivo: ' . $e->getMessage(), ['url' => $fileUrl]);
            return null;
        }
    }

    /**
     * Generar códigos de proveedor y escribirlos en el Excel (fila 3)
     * Usa la misma estructura que CotizacionController
     */
    private function generarCodigosProveedorEnExcel($calculadora)
    {
        try {
            // Cargar relaciones necesarias - ordenar proveedores por ID para mantener orden
            $calculadora->load(['contenedor']);
            $proveedores = $calculadora->proveedores()->orderBy('id')->get();
            
            if ($proveedores->isEmpty()) {
                Log::warning('No hay proveedores para generar códigos', ['calculadora_id' => $calculadora->id]);
                return;
            }

            // Obtener el contenedor
            $contenedor = $calculadora->contenedor;
            if (!$contenedor) {
                Log::warning('No se encontró contenedor para generar códigos', ['calculadora_id' => $calculadora->id]);
                return;
            }

            $carga = $contenedor->carga;
            // Completar a 2 dígitos si se puede convertir a número, sino usar últimos 2 caracteres
            $count = is_numeric($carga) ? str_pad($carga, 2, "0", STR_PAD_LEFT) : substr($carga, -2);
            
            $nameCliente = $calculadora->nombre_cliente;

            // Obtener el archivo Excel
            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::error('No se pudo descargar el archivo Excel para generar códigos', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
                return;
            }

            // Crear archivo temporal
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('excel_codes_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            // Abrir el archivo Excel con PhpSpreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFilePath);
            $sheet2 = $spreadsheet->getSheet(1); // Hoja de cálculos (índice 1)

            // Buscar la columna "TOTALES"
            $columnStart = "C";
            $columnTotales = "";
            $stop = false;
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
            $rowProveedores = 4;
            $columnStart = "C";
            $provider = 1;
            $currentRange = null;
            $processedRanges = [];

            // Iterar por cada proveedor de la calculadora
            $proveedorIndex = 0;
            $columnStart = "C";
            $stop = false;

            while (!$stop && $proveedorIndex < $proveedores->count()) {
                // Verifica si la columna actual es la última
                if ($columnStart == $columnTotales) {
                    $stop = true;
                    break;
                }

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

                // Obtener el proveedor de la calculadora (usar la colección ordenada)
                $proveedorCalculadora = $proveedores[$proveedorIndex];
                
                // Generar código usando la misma función que CotizacionController
                $codeSupplier = $this->generateCodeSupplier($nameCliente, $carga, $count, $provider);

                // Determinar columnas del rango (ej: "C4:F4" => C..F)
                $startCol = $columnStart;
                $endCol = $columnStart;
                if ($currentRange) {
                    $parts = explode(':', $currentRange);
                    if (count($parts) === 2) {
                        $startCol = preg_replace('/\d+/', '', $parts[0]);
                        $endCol = preg_replace('/\d+/', '', $parts[1]);
                    }
                }

                // Escribir código en la fila 3, en la primera columna del rango
                $sheet2->setCellValue($startCol . $rowCodeSupplier, $codeSupplier);

                // Si el proveedor tiene múltiples columnas (múltiples productos), hacer merge
                if ($startCol != $endCol) {
                    $sheet2->mergeCells($startCol . $rowCodeSupplier . ':' . $endCol . $rowCodeSupplier);
                }

                // Guardar código en la base de datos - usar update directo para asegurar que se guarde
                $proveedorId = $proveedorCalculadora->id;
                $updated = \App\Models\CalculadoraImportacionProveedor::where('id', $proveedorId)
                    ->update(['code_supplier' => $codeSupplier]);
                
                if ($updated) {
                    Log::info('Código de proveedor guardado en BD', [
                        'calculadora_id' => $calculadora->id,
                        'proveedor_id' => $proveedorId,
                        'code_supplier' => $codeSupplier,
                        'columna' => $startCol,
                        'updated_rows' => $updated
                    ]);
                } else {
                    Log::error('Error al guardar código de proveedor en BD', [
                        'calculadora_id' => $calculadora->id,
                        'proveedor_id' => $proveedorId,
                        'code_supplier' => $codeSupplier
                    ]);
                }

                // Avanzar a la siguiente columna (después del rango del proveedor)
                $columnStart = $this->incrementColumn($endCol);
                $provider++;
                $proveedorIndex++;
            }

            // Guardar el archivo modificado
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            // Obtener la ruta de destino del archivo original
            $destinoPath = $this->getFilePathFromUrl($fileUrl);
            if ($destinoPath && file_exists(dirname($destinoPath))) {
                // Copiar el archivo modificado a la ubicación original
                copy($tempFilePath, $destinoPath);
                Log::info('Códigos de proveedor escritos en Excel exitosamente', [
                    'calculadora_id' => $calculadora->id,
                    'total_proveedores' => $proveedorIndex,
                    'proveedores_procesados' => $proveedorIndex
                ]);
                
                // Verificar que los códigos se guardaron correctamente en la BD
                $proveedoresConCodigo = \App\Models\CalculadoraImportacionProveedor::where('id_calculadora_importacion', $calculadora->id)
                    ->whereNotNull('code_supplier')
                    ->count();
                Log::info('Verificación: Proveedores con código en BD', [
                    'calculadora_id' => $calculadora->id,
                    'total_con_codigo' => $proveedoresConCodigo,
                    'total_proveedores' => $proveedores->count()
                ]);
                
                // Verificar que los códigos se guardaron correctamente
                $proveedoresConCodigo = \App\Models\CalculadoraImportacionProveedor::where('id_calculadora_importacion', $calculadora->id)
                    ->whereNotNull('code_supplier')
                    ->count();
                Log::info('Verificación: Proveedores con código en BD', [
                    'calculadora_id' => $calculadora->id,
                    'total_con_codigo' => $proveedoresConCodigo,
                    'total_proveedores' => $proveedores->count()
                ]);
            } else {
                Log::warning('No se pudo determinar la ruta de destino del archivo', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
            }

            // Limpiar archivo temporal
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        } catch (\Exception $e) {
            Log::error('Error al generar códigos de proveedor en Excel: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Escribir códigos existentes en el Excel (sin generar nuevos)
     * Solo se usa cuando se actualiza una calculadora que ya tiene códigos
     */
    private function escribirCodigosExistentesEnExcel($calculadora)
    {
        try {
            // Cargar relaciones necesarias
            $calculadora->load(['contenedor', 'proveedores.productos']);

            // Verificar que tenga proveedores con códigos
            $proveedoresConCodigo = $calculadora->proveedores()->whereNotNull('code_supplier')->get();
            if ($proveedoresConCodigo->isEmpty()) {
                Log::info('No hay proveedores con códigos para escribir en Excel', ['calculadora_id' => $calculadora->id]);
                return;
            }

            // Obtener el archivo Excel
            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::error('No se pudo descargar el archivo Excel para escribir códigos', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
                return;
            }

            // Crear archivo temporal
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('excel_write_codes_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            // Abrir el archivo Excel con PhpSpreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFilePath);
            $sheet2 = $spreadsheet->getSheet(1); // Hoja de cálculos (índice 1)

            // Buscar la columna "TOTALES"
            $columnStart = "C";
            $columnTotales = "";
            $stop = false;
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
            $rowProveedores = 4;
            $columnStart = "C";
            $currentRange = null;
            $processedRanges = [];

            // Mapear códigos de proveedores por orden
            $codigosPorOrden = $proveedoresConCodigo->pluck('code_supplier')->toArray();

            // Iterar por cada proveedor de la calculadora que tenga código
            $proveedorIndex = 0;
            $columnStart = "C";
            $stop = false;

            while (!$stop && $proveedorIndex < count($codigosPorOrden)) {
                // Verifica si la columna actual es la última
                if ($columnStart == $columnTotales) {
                    $stop = true;
                    break;
                }

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

                // Obtener el código del proveedor
                $codeSupplier = $codigosPorOrden[$proveedorIndex];

                // Determinar columnas del rango (ej: "C4:F4" => C..F)
                $startCol = $columnStart;
                $endCol = $columnStart;
                if ($currentRange) {
                    $parts = explode(':', $currentRange);
                    if (count($parts) === 2) {
                        $startCol = preg_replace('/\d+/', '', $parts[0]);
                        $endCol = preg_replace('/\d+/', '', $parts[1]);
                    }
                }

                // Escribir código en la fila 3, en la primera columna del rango
                $sheet2->setCellValue($startCol . $rowCodeSupplier, $codeSupplier);

                // Si el proveedor tiene múltiples columnas (múltiples productos), hacer merge
                if ($startCol != $endCol) {
                    $sheet2->mergeCells($startCol . $rowCodeSupplier . ':' . $endCol . $rowCodeSupplier);
                }

                Log::info('Código existente escrito en Excel', [
                    'calculadora_id' => $calculadora->id,
                    'code_supplier' => $codeSupplier,
                    'columna' => $startCol
                ]);

                // Avanzar a la siguiente columna (después del rango del proveedor)
                $columnStart = $this->incrementColumn($endCol);
                $proveedorIndex++;
            }

            // Guardar el archivo modificado
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            // Obtener la ruta de destino del archivo original
            $destinoPath = $this->getFilePathFromUrl($fileUrl);
            if ($destinoPath && file_exists(dirname($destinoPath))) {
                // Copiar el archivo modificado a la ubicación original
                copy($tempFilePath, $destinoPath);
                Log::info('Códigos existentes escritos en Excel exitosamente', [
                    'calculadora_id' => $calculadora->id,
                    'total_proveedores' => $proveedorIndex
                ]);
            } else {
                Log::warning('No se pudo determinar la ruta de destino del archivo', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
            }

            // Limpiar archivo temporal
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        } catch (\Exception $e) {
            Log::error('Error al escribir códigos existentes en Excel: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Generar código de proveedor usando la misma estructura que CotizacionController
     */
    private function generateCodeSupplier($string, $carga, $rowCount, $index)
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
     * Incrementar columna (helper) - misma implementación que CotizacionController
     */
    private function incrementColumn($column, $increment = 1)
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

        // Convertir el número de vuelta a columna
        $result = '';
        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intval($number / 26);
        }

        return $result;
    }

    /**
     * Modificar Excel para agregar fechas de pago en columna P
     */
    private function modificarExcelConFechas($calculadora)
    {
        try {
            // Cargar relaciones necesarias
            $calculadora->load(['contenedor', 'proveedores.productos']);

            // Obtener el contenedor
            $contenedor = $calculadora->contenedor;
            if (!$contenedor) {
                Log::warning('No se encontró contenedor para la calculadora', ['calculadora_id' => $calculadora->id]);
                return;
            }

            // Obtener fechas del contenedor
            $fechaCorte = $contenedor->f_cierre ? Carbon::parse($contenedor->f_cierre)->format('d/m/Y') : null;
            $fechaArribo = $contenedor->f_puerto ? Carbon::parse($contenedor->f_puerto)->format('d/m/Y') : null;
            Log::info('Fechas del contenedor: ' . $fechaCorte . ' - ' . $fechaArribo);
            if (!$fechaCorte || !$fechaArribo) {
                Log::warning('Fechas del contenedor no disponibles', [
                    'calculadora_id' => $calculadora->id,
                    'fecha_corte' => $fechaCorte,
                    'fecha_arribo' => $fechaArribo
                ]);
                return;
            }

            // Contar número de items (productos de todos los proveedores)
            $totalItems = 0;
            foreach ($calculadora->proveedores as $proveedor) {
                $totalItems += $proveedor->productos->count();
            }

            // Obtener el archivo Excel
            $fileUrl = $calculadora->url_cotizacion;
            $fileContents = $this->downloadFileFromUrl($fileUrl);

            if (!$fileContents) {
                Log::error('No se pudo descargar el archivo Excel para modificar', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
                return;
            }

            // Crear archivo temporal
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $extension = pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'xlsx';
            $tempFileName = uniqid('excel_modify_') . '.' . $extension;
            $tempFilePath = $tempPath . '/' . $tempFileName;
            file_put_contents($tempFilePath, $fileContents);

            // Abrir el archivo Excel con PhpSpreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFilePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Escribir en D7: COTIZACION N° cod_cotizacion cuando existe
            if (!empty($calculadora->cod_cotizacion)) {
                $sheet->setCellValue('D7', 'COTIZACION N° ' . $calculadora->cod_cotizacion);
            }

            // Calcular las filas
            $filaServicioConsolidado = 37 + $totalItems + 4;
            $filaPagoImpuestos = 37 + $totalItems + 5;
            // Escribir en las celdas de la columna P
            $sheet->setCellValue('P' . $filaServicioConsolidado, 'Servicio de Consolidado antes de la Fecha de Corte ' . $fechaCorte);
            $sheet->setCellValue('P' . $filaPagoImpuestos, 'Pago de Impuestos antes del Arribo ' . $fechaArribo);

            // Guardar el archivo modificado
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            // Obtener la ruta de destino del archivo original
            $destinoPath = $this->getFilePathFromUrl($fileUrl);
            if ($destinoPath && file_exists(dirname($destinoPath))) {
                // Copiar el archivo modificado a la ubicación original
                copy($tempFilePath, $destinoPath);
                Log::info('Excel modificado exitosamente', [
                    'calculadora_id' => $calculadora->id,
                    'fila_servicio' => $filaServicioConsolidado,
                    'fila_impuestos' => $filaPagoImpuestos,
                    'total_items' => $totalItems
                ]);
            } else {
                Log::warning('No se pudo determinar la ruta de destino del archivo', [
                    'calculadora_id' => $calculadora->id,
                    'url' => $fileUrl
                ]);
            }

            // Limpiar archivo temporal
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        } catch (\Exception $e) {
            Log::error('Error al modificar Excel con fechas: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Obtener ruta del archivo desde URL
     */
    private function getFilePathFromUrl($url)
    {
        try {
            // Si es una URL completa, extraer la ruta relativa
            if (strpos($url, 'http') === 0) {
                $parsedUrl = parse_url($url);
                $path = $parsedUrl['path'] ?? '';

                // Remover /storage/ del inicio si existe
                if (strpos($path, '/storage/') === 0) {
                    $path = substr($path, 9); // Remover '/storage/'
                }

                return storage_path('app/public/' . $path);
            }

            // Si es una ruta relativa
            if (strpos($url, '/storage/') === 0) {
                $path = substr($url, 9); // Remover '/storage/'
                return storage_path('app/public/' . $path);
            }

            // Si es solo el nombre del archivo o ruta directa
            if (file_exists($url)) {
                return $url;
            }

            // Intentar en storage público
            $publicPath = storage_path('app/public/' . ltrim($url, '/'));
            if (file_exists($publicPath)) {
                return $publicPath;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener ruta del archivo: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }
}
