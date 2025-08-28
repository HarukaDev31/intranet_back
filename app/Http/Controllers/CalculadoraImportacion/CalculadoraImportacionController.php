<?php

namespace App\Http\Controllers\CalculadoraImportacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\CalculadoraImportacion;
use App\Services\BaseDatos\Clientes\ClienteService;
use App\Services\CalculadoraImportacionService;
use App\Models\CalculadoraTarifasConsolidado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CalculadoraImportacionController extends Controller
{
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
            $clientes = Cliente::where('telefono', '!=', null)
                ->where('telefono', '!=', '')
                ->where('telefono', 'like', '%' . $whatsapp . '%')
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
            $query = CalculadoraImportacion::with(['proveedores.productos', 'cliente']);

            // Filtros opcionales
            if ($request->has('tipo_cliente') && $request->tipo_cliente) {
                $query->where('tipo_cliente', $request->tipo_cliente);
            }

            if ($request->has('dni_cliente') && $request->dni_cliente) {
                $query->where('dni_cliente', 'like', '%' . $request->dni_cliente . '%');
            }

            if ($request->has('nombre_cliente') && $request->nombre_cliente) {
                $query->where('nombre_cliente', 'like', '%' . $request->nombre_cliente . '%');
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $calculos = $query->paginate($perPage);

            // Calcular totales para cada cálculo
            $data = $calculos->items();
            foreach ($data as $calculadora) {
                $totales = $this->calculadoraImportacionService->calcularTotales($calculadora);
                $calculadora->totales = $totales;
            }

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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cálculos: ' . $e->getMessage()
            ], 500);
        }
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
                'proveedores.*.qtyCaja' => 'required|integer|min:1',
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



  
}
