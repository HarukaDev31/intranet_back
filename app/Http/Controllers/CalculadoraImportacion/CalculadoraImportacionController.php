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
                ->where(function($query) use ($whatsapp, $telefonoNormalizado) {
                    $query->where('telefono', 'like', '%' . $whatsapp . '%');
                    
                    if (!empty($telefonoNormalizado)) {
                        $query->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"])
                            ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%51{$telefonoNormalizado}%"]);
                    }
                })
                ->limit(50)
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
    public function getTarifas()
    {
        try {
            $tarifas = CalculadoraTarifasConsolidado::with('tipoCliente')->get();
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
            $query = CalculadoraImportacion::with(['proveedores.productos', 'cliente', 'contenedor']);

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
            }
            //get filters estado calculadora, all contenedores carga id,
            //get all containers label=carga value=id
            $contenedores = Contenedor::all();
            $contenedores = $contenedores->map(function ($contenedor) {
                return [
                    'id' => $contenedor->id,
                    'label' => $contenedor->carga,
                    'value' => $contenedor->id
                ];
            });
            //get all estados calculadora label=estado value=estado
            $estadoCalculadora = CalculadoraImportacion::getEstadosDisponiblesFilter();
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
                    'total_clientes' => [
                        'value' => $calculos->total(),
                        'label' => 'Total Cotizaciones Realizadas'
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
     * Guardar cálculo de importación
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'clienteInfo.nombre' => 'required|string',
                'clienteInfo.dni' => 'required|string',
                'clienteInfo.whatsapp.value' => 'nullable|string',
                'clienteInfo.correo' => 'nullable|email',
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

            $calculadora = $this->calculadoraImportacionService->guardarCalculo($request->all());
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
     * Obtener cálculo por ID
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
            $calculadora->save();

            if ($estado === 'COTIZADO') {
                $this->sendWhatsAppMessage($calculadora->whatsapp_cliente, $calculadora);
            }

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
}
