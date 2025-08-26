<?php

namespace App\Http\Controllers\CalculadoraImportacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Clientes\Cliente;
use App\Services\BaseDatos\Clientes\ClienteService;
use App\Models\CalculadoraTarifasConsolidado;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculadoraImportacionController extends Controller
{
    protected $clienteService;

    public function __construct(ClienteService $clienteService)
    {
        $this->clienteService = $clienteService;
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
                    'value' => $cliente->id,
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
            return 'Inactivo';
        }

        if ($totalServicios === 1) {
            return 'Cliente';
        }

        // Obtener la fecha del último servicio
        $ultimoServicio = end($servicios);
        $fechaUltimoServicio = Carbon::parse($ultimoServicio['fecha']);
        $hoy = Carbon::now();
        $mesesDesdeUltimaCompra = $fechaUltimoServicio->diffInMonths($hoy);

        // Si la última compra fue hace más de 6 meses, es Inactivo
        if ($mesesDesdeUltimaCompra > 6) {
            return 'Inactivo';
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
                return 'Premium';
            }
            // Si tiene múltiples compras Y la última fue hace ≤ 6 meses
            else if ($mesesDesdeUltimaCompra <= 6) {
                return 'Recurrente';
            }
        }

        return 'Inactivo';
    }
    public function getTarifas()
    {
        try {
            $tarifas = CalculadoraTarifasConsolidado::with('tipoCliente')->get();
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
}
