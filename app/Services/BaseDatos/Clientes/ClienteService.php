<?php

namespace App\Services\BaseDatos\Clientes;

use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\Usuario;
use App\Models\Provincia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClienteService
{
    /**
     * Obtener clientes con paginación y filtros
     */
    public function obtenerClientes(Request $request, $page = 1, $perPage = 100)
    {
        $query = Cliente::query();

        // Aplicar filtros
        $this->aplicarFiltros($query, $request);

    // Ordenar de manera ascendente por fecha (sobrescribe cualquier orden previo)
    $query->reorder()->orderBy('fecha', 'desc');

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

            // Resolver entidad/provincia/comocomo_entero_empresa asociada (si existe)
            $provinciaName = null;
            $provinciaId = null;
            $nuComoEnteroEmpresa = null;
            $nuOtrosComoEnteroEmpresa = null;
            $userRecord = null;
            try {
                $servicioNombre = $primerServicio['servicio'] ?? null;

                // Si es Curso: preferir entidad, luego pedido_curso
                if ($servicioNombre && strtolower($servicioNombre) === 'curso') {
                    // 1) entidad heurística
                    if (method_exists($cliente, 'resolveEntidad')) {
                        $ent = $cliente->resolveEntidad();
                        if ($ent) {
                            $provinciaId = $ent->ID_Provincia ?? null;
                            if ($provinciaId && isset($ent->provincia) && isset($ent->provincia->No_Provincia)) {
                                $provinciaName = $ent->provincia->No_Provincia;
                            }
                            if (isset($ent->Nu_Como_Entero_Empresa)) {
                                $nuComoEnteroEmpresa = $ent->Nu_Como_Entero_Empresa;
                            }
                        }
                    }

                    // 2) fallback: buscar en pedido_curso -> entidad
                    if (!$provinciaName) {
                        try {
                            $pedido = DB::table('pedido_curso as pc')
                                ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
                                ->where('pc.Nu_Estado', 2)
                                ->where('pc.id_cliente', $cliente->id)
                                ->select('e.ID_Provincia', 'e.Nu_Como_Entero_Empresa')
                                ->orderBy('e.Fe_Registro', 'asc')
                                ->first();

                            if ($pedido) {
                                $provinciaId = $pedido->ID_Provincia ?? $provinciaId;
                                if ($provinciaId) {
                                    $prov = Provincia::find($provinciaId);
                                    $provinciaName = $prov ? $prov->No_Provincia : null;
                                }
                                if (isset($pedido->Nu_Como_Entero_Empresa) && !$nuComoEnteroEmpresa) {
                                    $nuComoEnteroEmpresa = $pedido->Nu_Como_Entero_Empresa;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('obtenerClientePorId: error consultando pedido_curso - ' . $e->getMessage());
                        }
                    }

                } elseif ($servicioNombre && strtolower($servicioNombre) === 'consolidado') {
                    // Consolidado: prioridad entidad -> cotizaciones -> usuario
                    // 1) entidad heurística
                    if (method_exists($cliente, 'resolveEntidad')) {
                        $ent = $cliente->resolveEntidad();
                        if ($ent) {
                            $provinciaId = $ent->ID_Provincia ?? null;
                            if ($provinciaId && isset($ent->provincia) && isset($ent->provincia->No_Provincia)) {
                                $provinciaName = $ent->provincia->No_Provincia;
                            }
                            if (isset($ent->Nu_Como_Entero_Empresa)) {
                                $nuComoEnteroEmpresa = $ent->Nu_Como_Entero_Empresa;
                            }
                        }
                    }

                    // 2) cotizaciones -> usuario (si no hay provincia todavía)
                    if (!$provinciaName) {
                        try {
                            $cotizacionQuery = DB::table('contenedor_consolidado_cotizacion as CC')
                                ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
                                ->where('CC.estado_cotizador', 'CONFIRMADO')
                                ->whereNotNull('CC.estado_cliente');

                            if (!empty($cliente->telefono)) {
                                $telefonoLimpio = preg_replace('/[^0-9]/', '', $cliente->telefono);
                                $cotizacionQuery->where(function($q) use ($telefonoLimpio) {
                                    $q->where(DB::raw('REPLACE(REPLACE(CC.telefono, " ", ""), "-", "")'), 'LIKE', "%{$telefonoLimpio}%")
                                      ->orWhere(DB::raw('REPLACE(REPLACE(CC.telefono, " ", ""), "-", "")'), 'LIKE', "%" . preg_replace('/^51/', '', $telefonoLimpio) . "%");
                                });
                            }

                            if (!empty($cliente->documento)) {
                                $cotizacionQuery->orWhere('CC.documento', $cliente->documento);
                            }

                            if (!empty($cliente->correo)) {
                                $cotizacionQuery->orWhere(function($q2) use ($cliente) {
                                    $q2->whereNotNull('CC.correo')
                                       ->where('CC.correo', '!=', '')
                                       ->where('CC.correo', $cliente->correo);
                                });
                            }

                            $cotizacion = $cotizacionQuery->select('CC.*')
                                ->orderBy('CC.fecha', 'asc')
                                ->orderByRaw('CAST(C.carga AS UNSIGNED)')
                                ->first();

                            if ($cotizacion && isset($cotizacion->id_usuario)) {
                                $usuario = Usuario::find($cotizacion->id_usuario);
                                if ($usuario) {
                                    // Usuario puede tener ID_Provincia o provincia relation
                                    $usuarioProvinciaId = $usuario->ID_Provincia ?? ($usuario->provincia_id ?? null);
                                    if ($usuarioProvinciaId) {
                                        $provinciaId = $usuarioProvinciaId;
                                        $prov = Provincia::find($provinciaId);
                                        $provinciaName = $prov ? $prov->No_Provincia : null;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('obtenerClientePorId: error resolviendo provincia desde cotizacion/usuario - ' . $e->getMessage());
                        }
                    }

                    // 3) users table (último fallback para consolidado si aún no hay provincia o campos empresa)
                    if (!$provinciaName || empty($nuComoEnteroEmpresa)) {
                        try {
                            $userQuery = DB::table('users');

                            if (!empty($cliente->correo)) {
                                $userQuery->where('email', $cliente->correo);
                            } elseif (!empty($cliente->telefono)) {
                                $telefonoLimpio = preg_replace('/[^0-9]/', '', $cliente->telefono);
                                $userQuery->where(function($q) use ($telefonoLimpio) {
                                    $q->where(DB::raw('REPLACE(REPLACE(whatsapp, " ", ""), "-", "")'), 'LIKE', "%{$telefonoLimpio}%")
                                      ->orWhere(DB::raw('REPLACE(REPLACE(phone, " ", ""), "-", "")'), 'LIKE', "%{$telefonoLimpio}%");
                                });
                            } elseif (!empty($cliente->documento)) {
                                $userQuery->where('dni', $cliente->documento);
                            }

                            $user = $userQuery->first();

                            if ($user) {
                                $userRecord = $user;
                                // User puede tener provincia_id o idcity
                                if (!$provinciaName) {
                                    $userProvinciaId = $user->provincia_id ?? ($user->idcity ?? null);
                                    if ($userProvinciaId) {
                                        $provinciaId = $userProvinciaId;
                                        $prov = Provincia::find($provinciaId);
                                        $provinciaName = $prov ? $prov->No_Provincia : null;
                                    }
                                }
                                // Obtener campos de empresa desde users
                                if (empty($nuComoEnteroEmpresa) && isset($user->no_como_entero)) {
                                    $nuComoEnteroEmpresa = $user->no_como_entero;
                                }
                                if (empty($nuOtrosComoEnteroEmpresa) && isset($user->no_otros_como_entero_empresa)) {
                                    $nuOtrosComoEnteroEmpresa = $user->no_otros_como_entero_empresa;
                                }
                                Log::info('obtenerClientePorId: nuComoEnteroEmpresa: ' . $nuComoEnteroEmpresa);
                                Log::info('obtenerClientePorId: nuOtrosComoEnteroEmpresa: ' . $nuOtrosComoEnteroEmpresa);
                            }
                        } catch (\Exception $e) {
                            Log::warning('obtenerClientePorId: error resolviendo provincia/campos empresa desde users - ' . $e->getMessage());
                        }
                    }
                } else {
                    // Sin primer servicio definido: intentar entidad como fallback
                    if (method_exists($cliente, 'resolveEntidad')) {
                        $ent = $cliente->resolveEntidad();
                        if ($ent) {
                            $provinciaId = $ent->ID_Provincia ?? null;
                            if ($provinciaId && isset($ent->provincia) && isset($ent->provincia->No_Provincia)) {
                                $provinciaName = $ent->provincia->No_Provincia;
                            }
                            if (isset($ent->Nu_Como_Entero_Empresa)) {
                                $nuComoEnteroEmpresa = $ent->Nu_Como_Entero_Empresa;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('obtenerClientePorId: error resolviendo entidad/provincia - ' . $e->getMessage());
            }

            // preparar campos de empresa según prioridad
            $primaryCode = $cliente->No_Como_Entero_Empresa ?? ($nuComoEnteroEmpresa ?? null);
            $no_otros_como_entero = $cliente->No_Otros_Como_Entero_Empresa ?? null;
            // intentar tomar desde entidad si aún no está definido
            try {
                if (empty($primaryCode) && isset($ent) && isset($ent->Nu_Como_Entero_Empresa)) {
                    $primaryCode = $ent->Nu_Como_Entero_Empresa;
                }
                if (empty($no_otros_como_entero) && isset($ent) && isset($ent->No_Otros_Como_Entero_Empresa)) {
                    $no_otros_como_entero = $ent->No_Otros_Como_Entero_Empresa;
                }
                // fallback a users para consolidado si aún no hay valores
                if (empty($no_otros_como_entero) && !empty($nuOtrosComoEnteroEmpresa)) {
                    $no_otros_como_entero = $nuOtrosComoEnteroEmpresa;
                }
            } catch (\Exception $e) {
                // ignore
            }

            // Map numeric codes to labels. If code is 6 or 8, prefer the 'otros' free-text field.
            $sourceMap = [
                0 => 'No especificado',
                1 => 'TikTok',
                2 => 'Facebook',
                3 => 'Instagram',
                4 => 'YouTube',
                5 => 'Familiares/Amigos',
                6 => 'Otros'
            ];

            $no_como_entero_final = null;
            if (!is_null($primaryCode) && $primaryCode !== '') {
                $codeInt = (int) $primaryCode;
                // If code indicates 'Otros' (6 or 8) and we don't yet have the free-text, try to resolve entidad now
                if (($codeInt === 6 || $codeInt === 8) && empty($no_otros_como_entero)) {
                    try {
                        if (!isset($ent) && method_exists($cliente, 'resolveEntidad')) {
                            $ent = $cliente->resolveEntidad();
                        }
                        if (isset($ent) && !empty($ent->No_Otros_Como_Entero_Empresa)) {
                            $no_otros_como_entero = $ent->No_Otros_Como_Entero_Empresa;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                if (($codeInt === 6 || $codeInt === 8) && !empty($no_otros_como_entero)) {
                    $no_como_entero_final = $no_otros_como_entero;
                } elseif (isset($sourceMap[$codeInt])) {
                    $no_como_entero_final = $sourceMap[$codeInt];
                } else {
                    $no_como_entero_final = $primaryCode;
                }
            }

            // Buscar usuario en tabla users por whatsapp, email o dni
            $idUser = null;
            try {
                $userQuery = DB::table('users');
                
                $telefonoLimpio = null;
                $telefonoVariantes = [];
                
                if (!empty($cliente->telefono)) {
                    $telefonoLimpio = preg_replace('/[^0-9]/', '', $cliente->telefono);
                    $telefonoVariantes[] = $telefonoLimpio;
                    
                    // Si tiene prefijo 51, también buscar sin prefijo
                    if (strlen($telefonoLimpio) >= 11 && substr($telefonoLimpio, 0, 2) === '51') {
                        $telefonoSinPrefijo = substr($telefonoLimpio, 2);
                        $telefonoVariantes[] = $telefonoSinPrefijo;
                    } else {
                        // Si no tiene prefijo, también buscar con prefijo
                        $telefonoConPrefijo = '51' . $telefonoLimpio;
                        $telefonoVariantes[] = $telefonoConPrefijo;
                    }
                }
                
                if (!empty($cliente->correo) || !empty($telefonoLimpio) || !empty($cliente->documento)) {
                    $userQuery->where(function($q) use ($cliente, $telefonoVariantes) {
                        if (!empty($cliente->correo)) {
                            $q->where('email', $cliente->correo);
                        }
                        if (!empty($telefonoVariantes)) {
                            $q->orWhere(function($q2) use ($telefonoVariantes) {
                                foreach ($telefonoVariantes as $telefono) {
                                    $q2->orWhere(function($q3) use ($telefono) {
                                        $q3->where(DB::raw('REPLACE(REPLACE(REPLACE(whatsapp, " ", ""), "-", ""), "+", "")'), 'LIKE', "%{$telefono}%")
                                           ->orWhere(DB::raw('REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "")'), 'LIKE', "%{$telefono}%");
                                    });
                                }
                            });
                        }
                        if (!empty($cliente->documento)) {
                            $q->orWhere('dni', $cliente->documento);
                        }
                    });
                    
                    $user = $userQuery->first();
                    if ($user) {
                        $idUser = $user->id;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('obtenerClientePorId: error buscando usuario en tabla users - ' . $e->getMessage());
            }

            $data = [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'provincia' => $provinciaName ?? ($cliente->provincia ?? null),
                'id_provincia' => $provinciaId,
                'origen' => $no_como_entero_final,
                'ruc' => $cliente->ruc,
                'empresa' => $cliente->empresa,
                'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                'id_user' => $idUser,
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
                        'monto' => $servicio['monto'],
                        'carga' => $servicio['carga'] ?? null,
                        'empresa' => $servicio['empresa'] ?? null
                    ];
                })->values()
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
        //filter clientes where id_cliente_importacion es nulo
       
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
            //if $primerServicio is null, then continue
            if ($primerServicio === null) {
                continue;
            }
            // intentar resolver provincia por entidad para cada cliente (si aplica)
            $provinciaName = null;
            try {
                $provinciaName = null;
                $nuComoEnteroEmpresa = null;
                $nuOtrosComoEnteroEmpresa = null;
                $userRecord = null;
                $primer = $cliente->primer_servicio;
                $servicioNombre = $primer['servicio'] ?? null;

                if ($servicioNombre && strtolower($servicioNombre) === 'curso') {
                    // Curso: entidad -> pedido_curso
                    if (method_exists($cliente, 'resolveEntidad')) {
                        $ent = $cliente->resolveEntidad();
                        if ($ent) {
                            $provinciaName = $ent->provincia->No_Provincia ?? null;
                            if (isset($ent->Nu_Como_Entero_Empresa)) {
                                $nuComoEnteroEmpresa = $ent->Nu_Como_Entero_Empresa;
                            }
                        }
                    }

                    if (!$provinciaName) {
                        try {
                            $pedido = DB::table('pedido_curso as pc')
                                ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
                                ->where('pc.Nu_Estado', 2)
                                ->where('pc.id_cliente', $cliente->id)
                                ->select('e.ID_Provincia', 'e.Nu_Como_Entero_Empresa')
                                ->orderBy('e.Fe_Registro', 'asc')
                                ->first();

                            if ($pedido) {
                                if (isset($pedido->ID_Provincia)) {
                                    $prov = Provincia::find($pedido->ID_Provincia);
                                    $provinciaName = $prov ? $prov->No_Provincia : null;
                                }
                                if (isset($pedido->Nu_Como_Entero_Empresa)) {
                                    $nuComoEnteroEmpresa = $pedido->Nu_Como_Entero_Empresa;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('transformarDatosClientes: error consultando pedido_curso para cliente ' . $cliente->id . ' - ' . $e->getMessage());
                        }
                    }

                } elseif ($servicioNombre && strtolower($servicioNombre) === 'consolidado') {
                    // Consolidado: entidad -> cotizacion->usuario
                    if (method_exists($cliente, 'resolveEntidad')) {
                        $ent = $cliente->resolveEntidad();
                        if ($ent) {
                            $provinciaName = $ent->provincia->No_Provincia ?? null;
                            if (isset($ent->Nu_Como_Entero_Empresa)) {
                                $nuComoEnteroEmpresa = $ent->Nu_Como_Entero_Empresa;
                            }
                        }
                    }

                    if (!$provinciaName) {
                        try {
                            $cotizacionQuery = DB::table('contenedor_consolidado_cotizacion as CC')
                                ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
                                ->where('CC.estado_cotizador', 'CONFIRMADO')
                                ->whereNotNull('CC.estado_cliente');

                            if (!empty($cliente->telefono)) {
                                $telefonoLimpio = preg_replace('/[^0-9]/', '', $cliente->telefono);
                                $cotizacionQuery->where(function($q) use ($telefonoLimpio) {
                                    $q->where(DB::raw('REPLACE(REPLACE(CC.telefono, " ", ""), "-", "")'), 'LIKE', "%{$telefonoLimpio}%")
                                      ->orWhere(DB::raw('REPLACE(REPLACE(CC.telefono, " ", ""), "-", "")'), 'LIKE', "%" . preg_replace('/^51/', '', $telefonoLimpio) . "%");
                                });
                            }

                            if (!empty($cliente->documento)) {
                                $cotizacionQuery->orWhere('CC.documento', $cliente->documento);
                            }

                            if (!empty($cliente->correo)) {
                                $cotizacionQuery->orWhere(function($q2) use ($cliente) {
                                    $q2->whereNotNull('CC.correo')
                                       ->where('CC.correo', '!=', '')
                                       ->where('CC.correo', $cliente->correo);
                                });
                            }

                            $cotizacion = $cotizacionQuery->select('CC.*')
                                ->orderBy('CC.fecha', 'asc')
                                ->orderByRaw('CAST(C.carga AS UNSIGNED)')
                                ->first();

                            if ($cotizacion && isset($cotizacion->id_usuario)) {
                                $usuario = Usuario::find($cotizacion->id_usuario);
                                if ($usuario) {
                                    $usuarioProvinciaId = $usuario->ID_Provincia ?? ($usuario->provincia_id ?? null);
                                    if ($usuarioProvinciaId) {
                                        $prov = Provincia::find($usuarioProvinciaId);
                                        $provinciaName = $prov ? $prov->No_Provincia : null;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('transformarDatosClientes: error resolviendo provincia desde cotizacion/usuario para cliente ' . $cliente->id . ' - ' . $e->getMessage());
                        }
                    }

                    // 3) users table (último fallback para consolidado si aún no hay provincia o campos empresa)
                    if (!$provinciaName || empty($nuComoEnteroEmpresa)) {
                        try {
                            $userQuery = DB::table('users');

                            if (!empty($cliente->correo)) {
                                $userQuery->where('email', $cliente->correo);
                            } elseif (!empty($cliente->telefono)) {
                                $telefonoLimpio = preg_replace('/[^0-9]/', '', $cliente->telefono);
                                $userQuery->where(function($q) use ($telefonoLimpio) {
                                    $q->where(DB::raw('REPLACE(REPLACE(whatsapp, " ", ""), "-", "")'), 'LIKE', "%{$telefonoLimpio}%")
                                      ->orWhere(DB::raw('REPLACE(REPLACE(phone, " ", ""), "-", "")'), 'LIKE', "%{$telefonoLimpio}%");
                                });
                            } elseif (!empty($cliente->documento)) {
                                $userQuery->where('dni', $cliente->documento);
                            }

                            $user = $userQuery->first();

                            if ($user) {
                                $userRecord = $user;
                                // User puede tener provincia_id o idcity
                                if (!$provinciaName) {
                                    $userProvinciaId = $user->provincia_id ?? ($user->idcity ?? null);
                                    if ($userProvinciaId) {
                                        $prov = Provincia::find($userProvinciaId);
                                        $provinciaName = $prov ? $prov->No_Provincia : null;
                                    }
                                }
                                // Obtener campos de empresa desde users
                                if (empty($nuComoEnteroEmpresa) && isset($user->no_como_entero)) {
                                    $nuComoEnteroEmpresa = $user->no_como_entero;
                                }
                                if (empty($nuOtrosComoEnteroEmpresa) && isset($user->no_otros_como_entero_empresa)) {
                                    $nuOtrosComoEnteroEmpresa = $user->no_otros_como_entero_empresa;
                                }
                                Log::info('transformarDatosClientes: nuComoEnteroEmpresa: ' . $nuComoEnteroEmpresa);
                                Log::info('transformarDatosClientes: nuOtrosComoEnteroEmpresa: ' . $nuOtrosComoEnteroEmpresa);
                            }
                        } catch (\Exception $e) {
                            Log::warning('transformarDatosClientes: error resolviendo provincia/campos empresa desde users para cliente ' . $cliente->id . ' - ' . $e->getMessage());
                        }
                    }
                } else {
                    // fallback general: entidad
                    if (method_exists($cliente, 'resolveEntidad')) {
                        $ent = $cliente->resolveEntidad();
                        if ($ent) {
                            $provinciaName = $ent->provincia->No_Provincia ?? null;
                            if (isset($ent->Nu_Como_Entero_Empresa)) {
                                $nuComoEnteroEmpresa = $ent->Nu_Como_Entero_Empresa;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('transformarDatosClientes: error resolviendo entidad para cliente ' . $cliente->id . ' - ' . $e->getMessage());
            }

            // preparar y mapear campos de empresa para el listado
            $primaryCode = $cliente->No_Como_Entero_Empresa ?? ($nuComoEnteroEmpresa ?? null);
            $no_otros_val = $cliente->No_Otros_Como_Entero_Empresa ?? null;
            try {
                if (empty($no_otros_val) && isset($ent) && isset($ent->No_Otros_Como_Entero_Empresa)) {
                    $no_otros_val = $ent->No_Otros_Como_Entero_Empresa;
                }
                // fallback a users para consolidado si aún no hay valores
                if (empty($no_otros_val) && !empty($nuOtrosComoEnteroEmpresa)) {
                    $no_otros_val = $nuOtrosComoEnteroEmpresa;
                }
            } catch (\Exception $e) {
                // ignore
            }

            $sourceMap = [
                0 => 'No especificado',
                1 => 'TikTok',
                2 => 'Facebook',
                3 => 'Instagram',
                4 => 'YouTube',
                5 => 'Familiares/Amigos',
                6 => 'Otros',
                8 => 'Otros'
            ];

            $no_como_entero_final = null;
            if (!is_null($primaryCode) && $primaryCode !== '') {
                $codeInt = (int) $primaryCode;
                // If code indicates 'Otros' (6 or 8) and we don't yet have the free-text, try to resolve entidad now
                if (($codeInt === 6 || $codeInt === 8) && empty($no_otros_val)) {
                    try {
                        if (!isset($ent) && method_exists($cliente, 'resolveEntidad')) {
                            $ent = $cliente->resolveEntidad();
                        }
                        if (isset($ent) && !empty($ent->No_Otros_Como_Entero_Empresa)) {
                            $no_otros_val = $ent->No_Otros_Como_Entero_Empresa;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                if (($codeInt === 6 || $codeInt === 8) && !empty($no_otros_val)) {
                    $no_como_entero_final = $no_otros_val;
                } elseif (isset($sourceMap[$codeInt])) {
                    $no_como_entero_final = $sourceMap[$codeInt];
                } else {
                    $no_como_entero_final = $primaryCode;
                }
            }

            $datosTransformados[] = [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'documento' => $cliente->documento,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'provincia' => $provinciaName ?? ($cliente->provincia ?? null),
                    'origen' => $no_como_entero_final,
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
                    'label' => 'Clientes'
                ],
                'total_clientes_curso' => [
                    'value' => 0,
                    'label' => 'Curso'
                ],
                'total_clientes_consolidado' => [
                    'value' => 0,
                    'label' => 'Consolidado'
                ]
            ];
        }

        // Obtener servicios para calcular estadísticas
        $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

        $totalClientes = count($clientesFiltrados);
        $clientesCurso = 0;
        $clientesConsolidado = 0;
        ///count clientes
        $clientes = count($clientesFiltrados);
        $index = 0;
        foreach ($clientesFiltrados as $cliente) {
            Log::info("Procesando cliente {$index} de {$totalClientes}");
            $servicios = $serviciosPorCliente[$cliente->id] ?? [];
            Log::info("Servicios del cliente {$cliente->id}: " . json_encode($servicios));
            $primerServicio = !empty($servicios) ? $servicios[0] : null;

            if ($primerServicio) {
                // Determinar tipo basado en el primer servicio
                if (strpos(strtolower($primerServicio['servicio']), 'curso') !== false) {
                    Log::info("Cliente {$cliente->id} es cliente curso");
                    $clientesCurso++;
                    $index++;

                } elseif (strpos(strtolower($primerServicio['servicio']), 'consolidado') !== false) {
                    Log::info("Cliente {$cliente->id} es cliente consolidado");
                    $clientesConsolidado++;
                    $index++;
                    
                }
            }

        }

        return [
            'total_clientes' => [
                'value' => $index,
                'label' => 'Clientes'
            ],
            'total_clientes_curso' => [
                'value' => $clientesCurso,
                'label' => 'Curso'
            ],
            'total_clientes_consolidado' => [
                'value' => $clientesConsolidado,
                'label' => 'Consolidado'
            ]
        ];
    }

    /**
     * Obtener estadísticas del header para un contenedor específico
     */
    public function getClientesHeader($idContenedor)
    {
        try {
            // Consulta para cbm_total_china usando DISTINCT para evitar duplicación
            $cbmTotalChina = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
                ->join('contenedor_consolidado_cotizacion as cc', 'cccp.id_cotizacion', '=', 'cc.id')
                ->where('cccp.id_contenedor', $idContenedor)
                ->where('cccp.estados_proveedor', 'LOADED')
                ->where('cc.estado_cotizador', 'CONFIRMADO')
                ->sum('cccp.cbm_total_china');

            // Subconsulta para cbm_total
            $cbmTotal = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->where('id_contenedor', $idContenedor)
                ->whereIn('id_cotizacion', function ($query) {
                    $query->select('id')
                        ->from('contenedor_consolidado_cotizacion')
                        ->where('estado_cotizador', 'CONFIRMADO');
                })
                ->sum('cbm_total');

            // Subconsulta para total_logistica
            $totalLogistica = DB::table('contenedor_consolidado_cotizacion')
                ->whereIn('id', function ($query) use ($idContenedor) {
                    $query->select(DB::raw('DISTINCT id_cotizacion'))
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id_contenedor', $idContenedor);
                })
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereIn('id', function ($query) use ($idContenedor) {
                    $query->select('id_cotizacion')
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id_contenedor', $idContenedor)
                        ->where('estados_proveedor', 'LOADED');
                })
                ->sum('monto');

            // Subconsulta para total_fob
            $totalFob = DB::table('contenedor_consolidado_cotizacion')
                ->whereIn('id', function ($query) use ($idContenedor) {
                    $query->select(DB::raw('DISTINCT id_cotizacion'))
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id_contenedor', $idContenedor);
                })
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereIn('id', function ($query) use ($idContenedor) {
                    $query->select('id_cotizacion')
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id_contenedor', $idContenedor)
                        ->where('estados_proveedor', 'LOADED');
                })
                ->sum('valor_doc');

            // Subconsulta para total_qty_items
            $totalQtyItems = DB::table('contenedor_consolidado_cotizacion')
                ->whereIn('id', function ($query) use ($idContenedor) {
                    $query->select(DB::raw('DISTINCT id_cotizacion'))
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id_contenedor', $idContenedor);
                })
                ->where('estado_cotizador', 'CONFIRMADO')
                ->sum('qty_item');

            // Subconsulta para total_logistica_pagado
            $totalLogisticaPagado = DB::table('contenedor_consolidado_cotizacion_coordinacion_pagos')
                ->join('pagos_concept', 'contenedor_consolidado_cotizacion_coordinacion_pagos.id_concept', '=', 'pagos_concept.id')
                ->where('contenedor_consolidado_cotizacion_coordinacion_pagos.id_contenedor', $idContenedor)
                ->where('pagos_concept.name', 'LOGISTICA')
                ->sum('contenedor_consolidado_cotizacion_coordinacion_pagos.monto');

            // Obtener carga del contenedor
            $carga = DB::table('contenedor_consolidado')
                ->where('id', $idContenedor)
                ->value('carga');

            // Obtener bl_file_url y lista_empaque_file_url del contenedor
            $contenedorInfo = DB::table('contenedor_consolidado')
                ->where('id', $idContenedor)
                ->select('bl_file_url', 'lista_embarque_url')
                ->first();

            // Obtener total de impuestos
            $totalImpuestos = DB::table('contenedor_consolidado_cotizacion')
                ->where('estado_cotizador', 'CONFIRMADO')
                ->where('id_contenedor', $idContenedor)
                ->sum('impuestos');

            return [
                'cbm_total_china' => $cbmTotalChina,
                'cbm_total' => $cbmTotal,
                'cbm_total_pendiente' => 0, // No se calcula en el original
                'total_logistica' => $totalLogistica,
                'total_logistica_pagado' => round($totalLogisticaPagado, 2),
                'qty_items' => $totalQtyItems,
                'bl_file_url' => $contenedorInfo ? $contenedorInfo->bl_file_url : '',
                'carga' => $carga ?: '',
                'lista_embarque_url' => $contenedorInfo ? $contenedorInfo->lista_embarque_url : '',
                'total_fob' => $totalFob,
                'total_impuestos' => $totalImpuestos
            ];

        } catch (\Exception $e) {
            Log::error('Error en getClientesHeader: ' . $e->getMessage());
            return [
                'status' => "error",
                'error' => true,
                "data" => [
                    'cbm_total_china' => 0,
                    'cbm_total_pendiente' => 0,
                    'total_logistica' => 0,
                    'total_logistica_pagado' => 0,
                    'qty_items' => 0,
                    'cbm_total' => 0,
                    'total_fob' => 0,
                    'total_impuestos' => 0,
                    'bl_file_url' => '',
                    'carga' => '',
                    'lista_embarque_url' => '',
                    'total_fob' => 0,
                    'total_impuestos' => 0
                ]
            ];
        }
    }
} 