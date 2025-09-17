<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\CalculadoraImportacion;
use App\Models\Notificacion;


class ContenedorController extends Controller
{
    private $defaultAgenteSteps = [];

    private $defautlAgenteChinaSteps = [];
    private $defaultJefeChina = [];
    private $defaultCotizador = [];
    private $defaultDocumentacion = [];
    public function __construct()
    {
        $this->defaultAgenteSteps = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "PAGOS", "iconURL" => env('APP_URL') . "assets/icons/pagos.png"],
            ["name" => "RECEPCION DE CARGA", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "BOOKING", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "DOCUMENTACION", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defautlAgenteChinaSteps = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "PAGOS Y COORDINACION", "iconURL" => env('APP_URL') . "assets/icons/coordinacion.png"],
            ["name" => "RECEPCION DE CARGA", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "BOOKING", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "DOCUMENTACION", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defaultJefeChina = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "PAGOS Y COORDINACION", "iconURL" => env('APP_URL') . "assets/icons/coordinacion.png"],
            ["name" => "RECEPCION E INSPECCION", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "BOOKING", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "DOCUMENTACION", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defaultCotizador = array(
            ["name" => "COTIZACION", "iconURL" => env('APP_URL') . "assets/icons/cotizacion.png"],
            ["name" => "CLIENTES", "iconURL" => env('APP_URL') . "assets/icons/clientes.png"],
            ["name" => "DOCUMENTACION", "iconURL" => env('APP_URL') . "assets/icons/cdocumentacion.png"],
            ["name" => "COTIZACION FINAL", "iconURL" => env('APP_URL') . "assets/icons/cotizacion_final.png"],
            ["name" => "FACTURA Y GUIA", "iconURL" => env('APP_URL') . "assets/icons/factura.png"]
        );
        $this->defaultDocumentacion = array(
            ["name" => "COTIZACION", "iconURL" => env('APP_URL') . "assets/icons/cotizacion.png"],
            ["name" => "DOCUMENTACION", "iconURL" => env('APP_URL') . "assets/icons/cdocumentacion.png"],
            ["name" => "ADUANA", "iconURL" => env('APP_URL') . "assets/icons/aduana.png"],
        );
    }
    public function index(Request $request)
    {
        try {

            $query = Contenedor::with('pais');
            $user = JWTAuth::parseToken()->authenticate();
            $completado = $request->completado ?? false;
            if ($user->getNombreGrupo() == Usuario::ROL_DOCUMENTACION) {
                if ($completado) {
                    $query->where('estado_documentacion', '=', Contenedor::CONTEDOR_CERRADO);
                } else {
                    $query->where('estado_documentacion', '!=', Contenedor::CONTEDOR_CERRADO);
                }
            } else {
                if ($completado) {
                    $query->where('estado_china', '=', Contenedor::CONTEDOR_CERRADO);
                } else {
                    $query->where('estado_china', '!=', Contenedor::CONTEDOR_CERRADO);
                }
            }
            //where empresa is 1
            $query->where('empresa', '!=', 1);
            //filtrar por los que f_cierre no este vacio
            $query->whereNotNull('f_cierre');

            //filtrar por buscador
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('carga', 'LIKE', "%$search%")
                        ->orWhere('mes', 'LIKE', "%$search%");
                });
            }

            //order by int(carga) desc
            $query->orderBy(DB::raw('CAST(carga AS UNSIGNED)'), 'desc');
            $data = $query->paginate(10);

                // Optimización: obtener todos los ids de la página y hacer agregaciones en lote.
                $pageIds = collect($data->items())->pluck('id')->all();
                $cbmVendidos = [];
                $cbmEmbarcados = [];
                if ($pageIds) {
                    // Vendidos (usa lógica proporcionada: china = suma confirmados de proveedores, peru = subconsulta volumen confirmados)
                    $vendRows = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
                        ->join('contenedor_consolidado_cotizacion as cc','cccp.id_cotizacion','=','cc.id')
                        ->whereIn('cccp.id_contenedor', $pageIds)
                        ->select([
                            'cccp.id_contenedor',
                            DB::raw('COALESCE(SUM(IF(cc.estado_cotizador = "CONFIRMADO", cccp.cbm_total_china, 0)),0) as cbm_total_china'),
                            DB::raw('(
                                SELECT COALESCE(SUM(volumen),0) FROM contenedor_consolidado_cotizacion
                                WHERE id IN (
                                    SELECT DISTINCT id_cotizacion FROM contenedor_consolidado_cotizacion_proveedores
                                    WHERE id_contenedor = cccp.id_contenedor
                                ) AND estado_cotizador = "CONFIRMADO"
                            ) as cbm_total_peru')
                        ])
                        ->groupBy('cccp.id_contenedor')
                        ->get();
                    foreach ($vendRows as $r) {
                        $cbmVendidos[$r->id_contenedor] = [
                            'peru' => (float)$r->cbm_total_peru,
                            'china' => (float)$r->cbm_total_china,
                        ];
                    }
                    // Embarcados (estado proveedor EMBARCADO) - solo tenemos cbm_total_china, Peru se debe derivar igual que vendidos pero restringido a EMBARCADO.
                    $embRows = DB::table('contenedor_consolidado_cotizacion_proveedores as p')
                        ->join('contenedor_consolidado_cotizacion as cc','p.id_cotizacion','=','cc.id')
                        ->whereIn('p.id_contenedor', $pageIds)
                        ->select([
                            'p.id_contenedor',
                            DB::raw('SUM(IF(estado_cotizador = "CONFIRMADO", p.cbm_total_china, 0)) as sum_china'),
                            // Subconsulta para Peru sólo de confirmados y embarcados (si aplica misma condición de confirmación)
                            DB::raw('(
                                SELECT COALESCE(SUM(volumen),0) FROM contenedor_consolidado_cotizacion
                                WHERE id IN (
                                    SELECT DISTINCT id_cotizacion FROM contenedor_consolidado_cotizacion_proveedores
                                    WHERE id_contenedor = p.id_contenedor AND estados_proveedor = "LOADED"
                                ) AND estado_cotizador = "CONFIRMADO"
                            ) as sum_peru')
                        ])
                        ->where('p.estados_proveedor', 'LOADED')
                        ->whereNull('id_cliente_importacion')
                        ->groupBy('p.id_contenedor')
                        ->get();
                    foreach ($embRows as $r) {
                        $cbmEmbarcados[$r->id_contenedor] = [
                            'peru' => (float)$r->sum_peru,
                            'china' => (float)$r->sum_china,
                        ];
                    }
                }

                $items = collect($data->items())->map(function($c) use ($cbmVendidos, $cbmEmbarcados) {
                    $cbm_total_peru = 0; $cbm_total_china = 0;
                    if ($c->estado_china === Contenedor::CONTEDOR_CERRADO) {
                        $vals = $cbmEmbarcados[$c->id] ?? ['peru'=>0,'china'=>0];
                        $cbm_total_peru = $vals['peru'];
                        $cbm_total_china = $vals['china'];
                    } else {
                        $vals = $cbmVendidos[$c->id] ?? ['peru'=>0,'china'=>0];
                        $cbm_total_peru = $vals['peru'];
                        $cbm_total_china = $vals['china'];
                    }
                    return [
                        'id' => $c->id,
                        'carga' => $c->carga,
                        'mes' => $c->mes,
                        'f_cierre' => $c->f_cierre,
                        'f_puerto' => $c->f_puerto,
                        'f_entrega' => $c->f_entrega,
                        'empresa' => $c->empresa,
                        'estado_documentacion' => $c->estado_documentacion,
                        'estado_china' => $c->estado_china,
                        'pais' => $c->pais,
                        //colocar decimales
                        'cbm_total_peru' => number_format($cbm_total_peru, 2),
                        'cbm_total_china' => number_format($cbm_total_china, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $items,
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
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contenedores: ' . $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)

    {
        try {
            $data = $request->all();
            if ($data['id']) {
                $contenedor = Contenedor::find($data['id']);
                $contenedor->update($data);
            } else {
                $contenedor = Contenedor::create($data);
                $this->generateSteps($contenedor->id);
            }


            return response()->json([
                "status"         => true,
                'id'             => $contenedor->id,
                "socketResponse" => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear contenedor: ' . $e->getMessage()
            ], 500);
        }
    }
    public function generateSteps($idContenedor)
    {
        $cotizadorSteps = $this->getCotizacionSteps($idContenedor);
        $documentacionSteps = $this->getDocumentacionSteps($idContenedor);
        $this->insertSteps($cotizadorSteps, $documentacionSteps);
    }
    public function getCotizacionSteps($idContenedor)
    {
        $stepCotizador = [];
        $idContenedor = intval($idContenedor);
        $index = 1;
        foreach ($this->defaultCotizador as $step) {
            $stepCotizador[] = [
                "id_pedido" => $idContenedor,
                'id_order' => $index,
                'name' => $step['name'],
                'iconURL' => $step['iconURL'],
                'tipo' => 'COTIZADOR',
                'status' => 'PENDING'
            ];
            $index++;
        }
        return $stepCotizador;
    }
    public function getDocumentacionSteps($idContenedor)
    {
        $stepDocumentacion = [];
        $idContenedor = intval($idContenedor);
        $index = 1;
        foreach ($this->defaultDocumentacion as $step) {
            $stepDocumentacion[] = [
                "id_pedido" => $idContenedor,
                'id_order' => $index,
                'tipo' => 'DOCUMENTACION',
                'name' => $step['name'],
                'iconURL' => $step['iconURL'],
                'status' => 'PENDING'
            ];
            $index++;
        }
        return $stepDocumentacion;
    }
    public function insertSteps($steps, $stepsDocumentacion)
    {
        try {
            ContenedorPasos::insert($steps);
            ContenedorPasos::insert($stepsDocumentacion);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al insertar pasos: ' . $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        // Implementación básica
        $query = Contenedor::where('id', $id);
        $data = $query->first();

        return response()->json(['data' => $data, 'success' => true]);
    }

    public function update(Request $request, $id)
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor update']);
    }

    public function destroy($id)
    {
        try {
            //set foreign key check to 0
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $steps = ContenedorPasos::where('id_pedido', $id);
            $steps->delete();
            $cotizaciones = Cotizacion::where('id_contenedor', $id);
            $cotizaciones->delete();
            $contenedor = Contenedor::find($id);
            $contenedor->delete();
            //set foreign key check to 1
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            return response()->json(['message' => 'Contenedor borrado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar contenedor: ' . $e->getMessage()], 500);
        }
    }

    public function filterOptions()
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor filter options']);
    }
    public function getContenedorPasos($idContenedor)
    {
        try {
            $user = JWTAuth::user();
            $role = $user->getNombreGrupo();
            $query = ContenedorPasos::where('id_pedido', $idContenedor)->orderBy('id_order', 'asc');

            switch ($role) {
                case Usuario::ROL_COTIZADOR:
                    // Aseguramos que sólo consultamos pasos del cotizador
                    $query->where('tipo', 'COTIZADOR');
                    if ($user->ID_Usuario == 28791) {
                        $query->limit(2);
                        break;
                    }
                    $query->limit(1);
                    break;
                case Usuario::ROL_DOCUMENTACION:
                    $query->where('tipo', 'DOCUMENTACION');
                    break;
                default:
                    $query->where('tipo', 'COTIZADOR');
                    break;
            }
            $data = $query->select('id', 'name', 'status', 'iconURL')->get();
            //FOR EACH DATA, IF ICONURL IS NOT NULL, REPLACE THE ICONURL WITH THE URL OF THE ICON
            foreach ($data as $item) {
                $item->iconURL = $this->generateImageUrl($item->iconURL);
            }

            return response()->json(['data' => $data, 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'success' => false, 'message' => 'Error al obtener pasos del contenedor: ' . $e->getMessage()]);
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
    public function getValidContainers()
    {
        // Obtener los contenedores existentes
        $existingContainers = Contenedor::where('empresa', '!=', '1')->pluck('carga')->toArray();
        Log::info('existingContainers', $existingContainers);
        // Crear array con todos los contenedores (1-50) indicando cuáles están deshabilitados
        $data = [];
        for ($i = 1; $i <= 50; $i++) {
            $data[] = [
                'value' => $i,
                'label' => "Contenedor #" . $i,
                'disabled' => in_array($i, $existingContainers)
            ];
        }

        return response()->json(['data' => $data, 'success' => true]);
    }
    public function getCargasDisponibles()
    {
        $hoy = date('Y-m-d');
        $query = Contenedor::where('empresa', '!=', 1)->orderBy('carga', 'desc');
        return $query->get();
    }
    public function moveCotizacionToConsolidado(Request $request)
    {
        try {
            $data = $request->all();
            $idCotizacion = $data['idCotizacion'];
            $idContenedorDestino = $data['idContenedorDestino'];
            // Actualiza la cotización principal
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }
            $cotizacion->id_contenedor = $idContenedorDestino;
            $cotizacion->estado_cotizador = 'CONFIRMADO';
            $cotizacion->updated_at = date('Y-m-d H:i:s');
            $cotizacion->save();

            // Actualiza los proveedores asociados
            $proveedores = CotizacionProveedor::where('id_cotizacion', $idCotizacion)->get();
            if (!$proveedores || $proveedores->isEmpty()) {
                return response()->json(['message' => 'Proveedores no encontrados', 'success' => false], 404);
            }
            foreach ($proveedores as $proveedor) {
                Log::info('proveedor'.json_encode($proveedor));
                $proveedor->id_contenedor = $idContenedorDestino;
                $proveedor->save();
            }

            // Crear notificaciones para Coordinación y Jefe de Ventas
            $this->crearNotificacionesMovimientoConsolidado($cotizacion, $idContenedorDestino);

            return response()->json(['message' => 'Cotización movida a consolidado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al mover cotización a consolidado: ' . $e->getMessage(), 'success' => false], 500);
        }
    }
    public function moveCotizacionToCalculadora(Request $request)
    {
        try {
            $data = $request->all();
            $idCotizacion = $data['idCotizacion'];
            $idContenedorDestino = $data['idContenedorDestino'];
            //set calculadora importacion id_carga_consolidada_contenedor = idContenedorDestino where id=idCotizacion
            $calculadora = CalculadoraImportacion::where('id', $idCotizacion)->first();
            if (!$calculadora) {
                return response()->json(['message' => 'Calculadora importación no encontrada', 'success' => false], 404);
            }
            $calculadora->id_carga_consolidada_contenedor = $idContenedorDestino;
            $calculadora->save();
            return response()->json(['message' => 'Cotización movida a calculadora correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al mover cotización a calculadora: ' . $e->getMessage(), 'success' => false], 500);
        }
    }
    public function updateEstadoDocumentacion(Request $request)
    {
        try {
            $id = $request->id;
            $estado = $request->estado_documentacion;
            $contenedor = Contenedor::find($id);
            if (!$contenedor) {
                return response()->json(['message' => 'Contenedor no encontrado', 'success' => false], 404);
            }
            $contenedor->estado_documentacion = $estado;
            $contenedor->save();
            if ($contenedor) {
                return [
                    'success' => true,
                    'message' => 'Estado actualizado correctamente'
                ];
            }
            return response()->json(['message' => 'Estado actualizado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el estado: ' . $e->getMessage()
            ];
        }
    }
    public function uploadPackingList(Request $request)
    {
        try {
            $idContenedor = $request->input('idContenedor');

            // Validar que se haya enviado un archivo
            if (!$request->hasFile('file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se ha enviado ningún archivo'
                ], 400);
            }

            $file = $request->file('file');

            // Validar tamaño del archivo (1MB = 1000000 bytes)
            $maxFileSize = 1000000;
            if ($file->getSize() > $maxFileSize) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El archivo excede el tamaño máximo permitido (1MB)'
                ], 400);
            }

            // Validar extensión del archivo
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (!in_array($fileExtension, $allowedExtensions)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de archivo no permitido'
                ], 400);
            }

            // Generar nombre único para el archivo
            $filename = time() . '_' . uniqid() . '.' . $fileExtension;

            // Ruta de almacenamiento
            $path = 'assets/images/agentecompra/';

            // Guardar archivo usando Laravel Storage
            $fileUrl = $file->storeAs($path, $filename, 'public');

            // Actualizar el contenedor usando Eloquent
            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }

            $contenedor->update(['lista_embarque_url' => $fileUrl]);

            // Verificar si el contenedor está completado
            $this->verifyContainerIsCompleted($idContenedor);

            return response()->json([
                'success' => true,
                'message' => 'Lista de embarque actualizada correctamente',
                'data' => [
                    'file_url' => $fileUrl,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en uploadListaEmbarque: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al subir la lista de embarque: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyContainerIsCompleted($idcontenedor)
    {
        try {
            // Obtener la lista de embarque del contenedor
            $contenedor = Contenedor::find($idcontenedor);
            if (!$contenedor) {
                Log::error('Contenedor no encontrado con ID: ' . $idcontenedor);
                return;
            }

            $listaEmbarque = $contenedor->lista_embarque_url;

            // Buscar si existe proveedor con estado "DATOS PROVEEDOR"
            $estadoProveedores = Cotizacion::where('id_contenedor', $idcontenedor)
                ->select('estado')
                ->get();

            $estado = null;
            foreach ($estadoProveedores as $estadoProveedor) {
                if ($estadoProveedor->estado == "DATOS PROVEEDOR") {
                    $estado = "DATOS PROVEEDOR";
                    break;
                }
            }

            // Preparar los datos para actualizar
            $updateData = [];

            if ($listaEmbarque != null) {
                $updateData['estado_china'] = 'COMPLETADO';
            } else if ($estado == "DATOS PROVEEDOR") {
                // No hacer nada
            } else {
                // Obtener el usuario autenticado
                $user = JWTAuth::parseToken()->authenticate();
                if ($user && $user->No_Grupo == 'Coordinación') {
                    $updateData['estado'] = 'RECIBIENDO';
                }
            }

            // Solo actualizar si hay datos para actualizar
            if (!empty($updateData)) {
                $contenedor->update($updateData);
                Log::info('Contenedor actualizado:', [
                    'id' => $idcontenedor,
                    'update_data' => $updateData
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en verifyContainerIsCompleted: ' . $e->getMessage());
        }
    }
    public function deletePackingList($idContenedor)
    {
        try {
            $idContenedor = $idContenedor;
            $contenedor = Contenedor::find($idContenedor);
            $contenedor->lista_embarque_url = null;
            $contenedor->save();
            $this->verifyContainerIsCompleted($idContenedor);
            return response()->json([
                'success' => true,
                'message' => 'Packing list eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar packing list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea notificaciones para Coordinación y Jefe de Ventas cuando se mueve una cotización a consolidado
     */
    private function crearNotificacionesMovimientoConsolidado($cotizacion, $idContenedorDestino)
    {
        try {
            // Obtener el contenedor destino
            $contenedorDestino = Contenedor::find($idContenedorDestino);
            if (!$contenedorDestino) {
                Log::warning('Contenedor destino no encontrado: ' . $idContenedorDestino);
                return;
            }

            // Obtener el usuario actual que realiza la acción
            $usuarioActual = Auth::user();
            if (!$usuarioActual) {
                Log::warning('Usuario actual no encontrado al mover cotización a consolidado');
                return;
            }

            // Crear la notificación para Coordinación
            $notificacionCoordinacion = Notificacion::create([
                'titulo' => 'Cotización Movida a Consolidado',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} movió la cotización de {$cotizacion->nombre} al contenedor {$contenedorDestino->carga}",
                'descripcion' => "Cotización #{$cotizacion->id} | Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor destino: {$contenedorDestino->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'rol_destinatario' => Usuario::ROL_COORDINACION,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $idContenedorDestino,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:swap-horizontal',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COORDINACION => [
                        'titulo' => 'Cotización Movida - Revisar',
                        'mensaje' => "Cotización de {$cotizacion->nombre} movida al contenedor {$contenedorDestino->carga}",
                        'descripcion' => "Cotización #{$cotizacion->id} movida por {$usuarioActual->No_Nombres_Apellidos}"
                    ]
                ])
            ]);

            // Crear la notificación para Jefe de Ventas
            $notificacionJefeVentas = Notificacion::create([
                'titulo' => 'Cotización Movida a Consolidado',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} movió la cotización de {$cotizacion->nombre} al contenedor {$contenedorDestino->carga}",
                'descripcion' => "Cotización #{$cotizacion->id} | Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor destino: {$contenedorDestino->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                'usuario_destinatario' => Usuario::ID_JEFE_VENTAS,
                'rol_destinatario' => Usuario::ROL_COTIZADOR,
                'navigate_to' => 'cargaconsolidada/abiertos/cotizaciones',
                'navigate_params' => json_encode([
                    'idContenedor' => $idContenedorDestino,
                    'tab' => 'prospectos',
                    'idCotizacion' => $cotizacion->id
                ]),
                'tipo' => Notificacion::TIPO_INFO,
                'icono' => 'mdi:swap-horizontal',
                'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'activa' => true,
                'creado_por' => $usuarioActual->ID_Usuario,
                'configuracion_roles' => json_encode([
                    Usuario::ROL_COTIZADOR => [
                        'titulo' => 'Cotización Movida - Supervisión',
                        'mensaje' => "Cotización de {$cotizacion->nombre} movida al contenedor {$contenedorDestino->carga} por {$usuarioActual->No_Nombres_Apellidos}",
                        'descripcion' => "Cotización #{$cotizacion->id} movida - Supervisión requerida"
                    ]
                ])
            ]);

            Log::info('Notificaciones de movimiento a consolidado creadas para Coordinación y Jefe de Ventas:', [
                'notificacion_coordinacion_id' => $notificacionCoordinacion->id,
                'notificacion_jefe_ventas_id' => $notificacionJefeVentas->id,
                'cotizacion_id' => $cotizacion->id,
                'contenedor_destino_id' => $idContenedorDestino,
                'contenedor_destino_carga' => $contenedorDestino->carga,
                'usuario_actual' => $usuarioActual->No_Nombres_Apellidos
            ]);

            return [$notificacionCoordinacion, $notificacionJefeVentas];

        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones de movimiento a consolidado para Coordinación y Jefe de Ventas: ' . $e->getMessage());
            // No lanzar excepción para no afectar el flujo principal de movimiento
            return null;
        }
    }
}
