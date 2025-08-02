<?php

namespace App\Http\Controllers\BaseDatos;

use App\Models\BaseDatos\Clientes\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
class ClientesController extends Controller
{
    /**
     * Obtener lista de clientes con paginación
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            $query = Cliente::query();

            // Aplicar búsqueda si se proporciona
            if ($request->has('search') && !empty($request->search)) {
                $query->buscar($request->search);
            }

            // Aplicar filtros
            if ($request->has('servicio') && !empty($request->servicio)) {
                $query->porServicio($request->servicio);
            }

            if ($request->has('categoria') && !empty($request->categoria) && $request->categoria != 'todos') {
                $query->porCategoria($request->categoria);
            }

            // Filtro por cliente recurrente
          
            // Filtro por rango de fechas
            if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
                $fechaInicio = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
                $query->where('fecha', '>=', $fechaInicio);
            }

            if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
                $fechaFin = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_fin)->endOfDay();
                $query->where('fecha', '<=', $fechaFin);
            }

            // Ordenar por fecha de creación (más recientes primero)
            $query->orderBy('created_at', 'desc');

            $data = $query->paginate($perPage, ['*'], 'page', $page);

            // Transformar los datos de clientes
            $clientesData = [];
            foreach ($data->items() as $cliente) {
                $primerServicio = $cliente->primer_servicio;
                $servicios = $cliente->servicios;

                $clientesData[] = [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                    'primer_servicio' => $primerServicio ? [
                        'servicio' => $primerServicio['servicio'],
                        'fecha' => \Carbon\Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                        'categoria' => $primerServicio['categoria']
                    ] : null,
                    'total_servicios' => count($servicios),
                    'servicios' => collect($servicios)->map(function ($servicio) {
                        return [
                            'servicio' => $servicio['servicio'],
                            'fecha' => \Carbon\Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                            'categoria' => $servicio['categoria']
                        ];
                    })
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $clientesData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener clientes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un cliente específico con todos sus servicios
     */
    public function show($id): JsonResponse
    {
        try {
            $cliente = Cliente::find($id);

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            $primerServicio = $cliente->primer_servicio;
            $servicios = $cliente->servicios;

            $data = [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                'primer_servicio' => $primerServicio ? [
                    'servicio' => $primerServicio['servicio'],
                    'fecha' => \Carbon\Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                    'categoria' => $primerServicio['categoria']
                ] : null,
                'total_servicios' => count($servicios),
                'servicios' => collect($servicios)->map(function ($servicio) {
                    return [
                        'servicio' => $servicio['servicio'],
                        'fecha' => \Carbon\Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                        'categoria' => $servicio['categoria']
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Cliente obtenido exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar clientes por término
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'termino' => 'required|string|min:2'
            ]);

            $clientes = Cliente::buscar($request->termino)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $clientes->transform(function ($cliente) {
                $primerServicio = $cliente->primer_servicio;
                
                return [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                    'primer_servicio' => $primerServicio ? [
                        'servicio' => $primerServicio['servicio'],
                        'fecha' => \Carbon\Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                        'categoria' => $primerServicio['categoria']
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $clientes,
                'message' => 'Búsqueda completada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $totalClientes = Cliente::count();
            
            // Contar por categoría
            $clientes = Cliente::all();
            $categorias = [
                'Cliente' => 0,
                'Recurrente' => 0,
                'Premium' => 0
            ];

            foreach ($clientes as $cliente) {
                $primerServicio = $cliente->primer_servicio;
                if ($primerServicio) {
                    $categoria = $primerServicio['categoria'];
                    if (isset($categorias[$categoria])) {
                        $categorias[$categoria]++;
                    }
                }
            }

            // Contar por servicio
            $servicios = [
                'Curso' => 0,
                'Consolidado' => 0
            ];

            foreach ($clientes as $cliente) {
                $primerServicio = $cliente->primer_servicio;
                if ($primerServicio) {
                    $servicio = $primerServicio['servicio'];
                    if (isset($servicios[$servicio])) {
                        $servicios[$servicio]++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_clientes' => $totalClientes,
                    'por_categoria' => $categorias,
                    'por_servicio' => $servicios
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener clientes por servicio
     */
    public function porServicio(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'servicio' => 'required|in:Curso,Consolidado'
            ]);

            $clientes = Cliente::porServicio($request->servicio)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            // Transformar los datos de clientes
            $clientesData = [];
            foreach ($clientes->items() as $cliente) {
                $primerServicio = $cliente->primer_servicio;
                
                $clientesData[] = [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                    'primer_servicio' => $primerServicio ? [
                        'servicio' => $primerServicio['servicio'],
                        'fecha' => \Carbon\Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                        'categoria' => $primerServicio['categoria']
                    ] : null
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'current_page' => $clientes->currentPage(),
                    'data' => $clientesData,
                    'first_page_url' => $clientes->url(1),
                    'from' => $clientes->firstItem(),
                    'last_page' => $clientes->lastPage(),
                    'last_page_url' => $clientes->url($clientes->lastPage()),
                    'next_page_url' => $clientes->nextPageUrl(),
                    'path' => $clientes->path(),
                    'per_page' => $clientes->perPage(),
                    'prev_page_url' => $clientes->previousPageUrl(),
                    'to' => $clientes->lastItem(),
                    'total' => $clientes->total(),
                ],
                'message' => 'Clientes por servicio obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes por servicio: ' . $e->getMessage()
            ], 500);
        }
    }
} 