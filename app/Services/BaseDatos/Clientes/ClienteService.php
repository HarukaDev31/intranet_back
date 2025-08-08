<?php

namespace App\Services\BaseDatos\Clientes;

use App\Models\BaseDatos\Clientes\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClienteService
{
    /**
     * Obtener clientes con paginación y filtros
     */
    public function obtenerClientes(Request $request, $page = 1, $perPage = 15)
    {
        $query = Cliente::query();

        // Aplicar filtros
        $this->aplicarFiltros($query, $request);

        // Verificar si hay filtro de categoría
        $filtroCategoria = $request->has('categoria') && !empty($request->categoria) && $request->categoria != 'todos'
            ? $request->categoria : null;

        // Obtener datos paginados
        if ($filtroCategoria) {
            [$clientesData, $paginationData] = $this->obtenerClientesConFiltroCategoria($query, $filtroCategoria, $page, $perPage);
        } else {
            [$clientesData, $paginationData] = $this->obtenerClientesSinFiltroCategoria($query, $page, $perPage);
        }

        // Obtener estadísticas para el header
        $headerStats = $this->obtenerEstadisticasHeader($request);

        return [
            'data' => $clientesData,
            'pagination' => $paginationData,
            'headers' => $headerStats
        ];
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function obtenerEstadisticas()
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

            return [
                'data' => [
                    'total_clientes' => $totalClientes,
                    'por_categoria' => $categorias,
                    'por_servicio' => $servicios
                ]
            ];
        } catch (\Exception $e) {
            return [
                'data' => [
                    'total_clientes' => 0,
                    'por_categoria' => ['Cliente' => 0, 'Recurrente' => 0, 'Premium' => 0],
                    'por_servicio' => ['Curso' => 0, 'Consolidado' => 0]
                ]
            ];
        }
    }

    /**
     * Obtener cliente por ID
     */
    public function obtenerClientePorId($id)
    {
        try {
            $cliente = Cliente::find($id);

            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                    'status' => 404
                ];
            }

            $primerServicio = $cliente->primer_servicio;
            $servicios = $cliente->servicios;

            $data = [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'ruc' => $cliente->ruc,
                'empresa' => $cliente->empresa,
                'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                'primer_servicio' => $primerServicio ? [
                    'servicio' => $primerServicio['servicio'],
                    'fecha' => Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                    'categoria' => $primerServicio['categoria']
                ] : null,
                'total_servicios' => count($servicios),
                'servicios' => collect($servicios)->map(function ($servicio) {
                    return [
                        'is_imported' => $servicio['is_imported'],
                        'id' => $servicio['id'],
                        'detalle' => $servicio['detalle'],
                        'servicio' => $servicio['servicio'],
                        'fecha' => Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                        'categoria' => $servicio['categoria'],
                        'monto' => $servicio['monto']
                    ];
                })
            ];

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener cliente: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Buscar clientes por término
     */
    public function buscarClientes($termino)
    {
        try {
            if (empty($termino)) {
                return ['data' => collect()];
            }

            $clientes = Cliente::buscar($termino)
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
                        'fecha' => Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                        'categoria' => $primerServicio['categoria']
                    ] : null
                ];
            });

            return ['data' => $clientes];
        } catch (\Exception $e) {
            return ['data' => collect()];
        }
    }

    /**
     * Obtener clientes por servicio
     */
    public function obtenerClientesPorServicio($servicio)
    {
        try {
            $clientes = Cliente::porServicio($servicio)
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
                        'fecha' => Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                        'categoria' => $primerServicio['categoria']
                    ] : null
                ];
            }

            return [
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
                ]
            ];
        } catch (\Exception $e) {
            return [
                'data' => [
                    'current_page' => 1,
                    'data' => [],
                    'first_page_url' => null,
                    'from' => null,
                    'last_page' => 1,
                    'last_page_url' => null,
                    'next_page_url' => null,
                    'path' => null,
                    'per_page' => 15,
                    'prev_page_url' => null,
                    'to' => null,
                    'total' => 0,
                ]
            ];
        }
    }

    /**
     * Aplicar filtros a la query base
     */
    private function aplicarFiltros($query, Request $request)
    {
        // Aplicar búsqueda si se proporciona
        if ($request->has('search') && !empty($request->search)) {
            $query->buscar($request->search);
        }

        // Aplicar filtros de servicio
        if ($request->has('servicio') && !empty($request->servicio) && $request->servicio != 'todos') {
            $query->porServicio($request->servicio);
        }

        // Filtro por rango de fechas
        if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
            $fechaInicio = Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
            $query->where('fecha', '>=', $fechaInicio);
        }

        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $fechaFin = Carbon::createFromFormat('d/m/Y', $request->fecha_fin)->endOfDay();
            $query->where('fecha', '<=', $fechaFin);
        }

        // Ordenar por fecha de creación
        $query->orderBy('created_at', 'desc');
    }

    /**
     * Obtener clientes con filtro de categoría
     */
    private function obtenerClientesConFiltroCategoria($query, $filtroCategoria, $page, $perPage)
    {
        $todosLosClientes = $query->get();
        $todosLosIds = $todosLosClientes->pluck('id')->toArray();
        $serviciosPorCliente = $this->obtenerServiciosEnLote($todosLosIds);

        // Filtrar por categoría
        $clientesFiltrados = [];
        foreach ($todosLosClientes as $cliente) {
            $servicios = $serviciosPorCliente[$cliente->id] ?? [];
            $categoria = $this->determinarCategoriaCliente($servicios);

            if ($categoria === $filtroCategoria) {
                $primerServicio = !empty($servicios) ? $servicios[0] : null;
                
                $clientesFiltrados[] = [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                    'categoria' => $categoria,
                    'primer_servicio' => $primerServicio ? [
                        'servicio' => $primerServicio['servicio'],
                        'fecha' => Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                        'categoria' => $categoria
                    ] : null,
                    'total_servicios' => count($servicios),
                    'servicios' => collect($servicios)->map(function ($servicio) use ($categoria) {
                        return [
                            'servicio' => $servicio['servicio'],
                            'fecha' => Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                            'categoria' => $categoria
                        ];
                    })
                ];
            }
        }

        // Paginar manualmente
        $total = count($clientesFiltrados);
        $offset = ($page - 1) * $perPage;
        $clientesPaginados = array_slice($clientesFiltrados, $offset, $perPage);

        return [$clientesPaginados, [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ]];
    }

    /**
     * Obtener clientes sin filtro de categoría
     */
    private function obtenerClientesSinFiltroCategoria($query, $page, $perPage)
    {
        $clientes = $query->paginate($perPage, ['*'], 'page', $page);
        $clienteIds = $clientes->pluck('id')->toArray();
        $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

        $datosTransformados = $this->transformarDatosClientes($clientes->items(), $serviciosPorCliente);

        return [$datosTransformados, [
            'total' => $clientes->total(),
            'per_page' => $clientes->perPage(),
            'current_page' => $clientes->currentPage(),
            'last_page' => $clientes->lastPage()
        ]];
    }

    /**
     * Obtener servicios en lote para múltiples clientes
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

    /**
     * Transformar datos de clientes para la respuesta
     */
    private function transformarDatosClientes($clientes, $serviciosPorCliente)
    {
        $datosTransformados = [];
        
        foreach ($clientes as $cliente) {
            $servicios = $serviciosPorCliente[$cliente->id] ?? [];
            $categoria = $this->determinarCategoriaCliente($servicios);
            $primerServicio = !empty($servicios) ? $servicios[0] : null;
            
            $datosTransformados[] = [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                'categoria' => $categoria,
                'primer_servicio' => $primerServicio ? [
                    'servicio' => $primerServicio['servicio'],
                    'fecha' => Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                    'categoria' => $categoria
                ] : null,
                'total_servicios' => count($servicios),
                'servicios' => collect($servicios)->map(function ($servicio) use ($categoria) {
                    return [
                        'servicio' => $servicio['servicio'],
                        'fecha' => Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                        'categoria' => $categoria
                    ];
                })
            ];
        }
        
        return $datosTransformados;
    }

    /**
     * Obtener estadísticas para el header de la tabla
     */
    private function obtenerEstadisticasHeader(Request $request)
    {
        // Aplicar TODOS los filtros incluyendo categoría
        $queryBase = Cliente::query();
        $this->aplicarFiltros($queryBase, $request);

        // Verificar si hay filtro de categoría
        $filtroCategoria = $request->has('categoria') && !empty($request->categoria) && $request->categoria != 'todos'
            ? $request->categoria : null;

        if ($filtroCategoria) {
            // Con filtro de categoría - aplicar lógica especial
            $todosLosClientes = $queryBase->get();
            $todosLosIds = $todosLosClientes->pluck('id')->toArray();
            $serviciosPorCliente = $this->obtenerServiciosEnLote($todosLosIds);

            // Filtrar por categoría
            $clientesFiltrados = [];
            foreach ($todosLosClientes as $cliente) {
                $servicios = $serviciosPorCliente[$cliente->id] ?? [];
                $categoria = $this->determinarCategoriaCliente($servicios);

                if ($categoria === $filtroCategoria) {
                    $clientesFiltrados[] = $cliente;
                }
            }
        } else {
            // Sin filtro de categoría - obtener todos los clientes filtrados
            $clientesFiltrados = $queryBase->get();
        }

        // Obtener IDs de clientes (manejar tanto arrays como colecciones)
        if (is_array($clientesFiltrados)) {
            $clienteIds = array_column($clientesFiltrados, 'id');
        } else {
            $clienteIds = $clientesFiltrados->pluck('id')->toArray();
        }

        if (empty($clienteIds)) {
            return [
                'total_clientes' => [
                    'value' => 0,
                    'label' => 'Total Clientes'
                ],
                'total_clientes_curso' => [
                    'value' => 0,
                    'label' => 'Total Clientes Curso'
                ],
                'total_clientes_consolidado' => [
                    'value' => 0,
                    'label' => 'Total Clientes Consolidado'
                ]
            ];
        }

        // Obtener servicios para calcular estadísticas
        $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

        $totalClientes = count($clientesFiltrados);
        $clientesCurso = 0;
        $clientesConsolidado = 0;

        foreach ($clientesFiltrados as $cliente) {
            $servicios = $serviciosPorCliente[$cliente->id] ?? [];
            $primerServicio = !empty($servicios) ? $servicios[0] : null;

            if ($primerServicio) {
                // Determinar tipo basado en el primer servicio
                if (strpos(strtolower($primerServicio['servicio']), 'curso') !== false) {
                    $clientesCurso++;
                } elseif (strpos(strtolower($primerServicio['servicio']), 'consolidado') !== false) {
                    $clientesConsolidado++;
                }
            }
        }

        return [
            'total_clientes' => [
                'value' => $totalClientes,
                'label' => 'Total Clientes'
            ],
            'total_clientes_curso' => [
                'value' => $clientesCurso,
                'label' => 'Total Clientes Curso'
            ],
            'total_clientes_consolidado' => [
                'value' => $clientesConsolidado,
                'label' => 'Total Clientes Consolidado'
            ]
        ];
    }
} 