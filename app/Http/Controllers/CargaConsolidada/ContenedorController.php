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
use App\Jobs\ProcessPackingListUploadJob;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\CalculadoraImportacion;
use App\Models\Notificacion;
use App\Events\CotizacionChangeContainer;
use App\Traits\WhatsappTrait;
use App\Traits\GoogleSheetsHelper;
use Carbon\Carbon;
use App\Models\CargaConsolidada\Pago;

class ContenedorController extends Controller
{
    use WhatsappTrait, GoogleSheetsHelper;
    private $defaultAgenteSteps = [];

    private $defautlAgenteChinaSteps = [];
    private $defaultJefeChina = [];
    private $defaultCotizador = [];
    private $defaultDocumentacion = [];
    private $defaultAdministracion = [];
    public function __construct()
    {
        $host = rtrim(config('app.url') ?? env('APP_URL'), '/');
        $this->defaultAgenteSteps = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => $host . '/assets/icons/orden.png'],
            ["name" => "PAGOS", "iconURL" => $host . '/assets/icons/pagos.png'],
            ["name" => "RECEPCION DE CARGA", "iconURL" => $host . '/assets/icons/recepcion.png'],
            ["name" => "BOOKING", "iconURL" => $host . '/assets/icons/inspeccion.png'],
            ["name" => "DOCUMENTACION", "iconURL" => $host . '/assets/icons/documentacion.png']
        );
        $this->defautlAgenteChinaSteps = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => $host . '/assets/icons/orden.png'],
            ["name" => "PAGOS Y COORDINACION", "iconURL" => $host . '/assets/icons/coordinacion.png'],
            ["name" => "RECEPCION DE CARGA", "iconURL" => $host . '/assets/icons/recepcion.png'],
            ["name" => "BOOKING", "iconURL" => $host . '/assets/icons/inspeccion.png'],
            ["name" => "DOCUMENTACION", "iconURL" => $host . '/assets/icons/documentacion.png']
        );
        $this->defaultJefeChina = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => $host . '/assets/icons/orden.png'],
            ["name" => "PAGOS Y COORDINACION", "iconURL" => $host . '/assets/icons/coordinacion.png'],
            ["name" => "RECEPCION E INSPECCION", "iconURL" => $host . '/assets/icons/recepcion.png'],
            ["name" => "BOOKING", "iconURL" => $host . '/assets/icons/inspeccion.png'],
            ["name" => "DOCUMENTACION", "iconURL" => $host . '/assets/icons/documentacion.png']
        );
        $this->defaultCotizador = array(
            ["name" => "COTIZACION", "iconURL" => $host . '/assets/icons/cotizacion.png'],
            ["name" => "CLIENTES", "iconURL" => $host . '/assets/icons/clientes.png'],
            ["name" => "DOCUMENTACION", "iconURL" => $host . '/assets/icons/cdocumentacion.png'],
            ["name" => "COTIZACION FINAL", "iconURL" => $host . '/assets/icons/cotizacion_final.png'],
            ["name" => "ENTREGA", "iconURL" => $host . '/assets/icons/entrega.png'],
            ["name" => "FACTURA Y GUIA", "iconURL" => $host . '/assets/icons/factura.png']
        );
        $this->defaultDocumentacion = array(
            ["name" => "CLIENTES", "iconURL" => $host . '/assets/icons/clientes.png'],
            ["name" => "DOCUMENTACION", "iconURL" => $host . '/assets/icons/cdocumentacion.png'],
            ["name" => "ADUANA", "iconURL" => $host . '/assets/icons/aduana.png'],
        );
        $this->defaultAdministracion = array(
            ["name" => "CLIENTES", "iconURL" => $host . '/assets/icons/clientes.png'],
            ["name" => "DOCUMENTACION", "iconURL" => $host . '/assets/icons/cdocumentacion.png'],
            ["name" => "COTIZACION FINAL", "iconURL" => $host . '/assets/icons/cotizacion_final.png'],
            ["name" => "ENTREGA", "iconURL" => $host . '/assets/icons/entrega.png'],
            ["name" => "FACTURA Y GUIA", "iconURL" => $host . '/assets/icons/factura.png']
        );
    }

    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedores",
     *     tags={"Carga Consolidada"},
     *     summary="Listar contenedores",
     *     description="Obtiene la lista de contenedores de carga consolidada",
     *     operationId="getContenedores",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="completado",
     *         in="query",
     *         description="Filtrar contenedores completados",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de contenedores obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     */
    public function index(Request $request)
    {
        try {

            $query = Contenedor::with('pais');
            $user = JWTAuth::parseToken()->authenticate();
            $completado = $request->completado ?? false;
            if ($user->getNombreGrupo() == Usuario::ROL_DOCUMENTACION || $user->getNombreGrupo() == Usuario::ROL_JEFE_IMPORTACION) {
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

            //filtrar por buscador
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('carga', 'LIKE', "%$search%")
                        ->orWhere('mes', 'LIKE', "%$search%");
                });
            }

            //order by int(carga) desc y en base al año y mes de f_inicio
            $query->orderBy(DB::raw('YEAR(f_inicio)'), 'DESC');
            $query->orderByRaw('CAST(carga AS UNSIGNED) DESC');
            $data = $query->paginate(100);

            // Optimización: obtener todos los ids de la página y hacer agregaciones en lote.
            $pageIds = collect($data->items())->pluck('id')->all();
            $cbmVendidos = [];
            $cbmEmbarcados = [];
            if ($pageIds) {
                // Vendidos (usa lógica proporcionada: china = suma confirmados de proveedores, peru = subconsulta volumen confirmados)
                $vendRows = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
                    ->join('contenedor_consolidado_cotizacion as cc', 'cccp.id_cotizacion', '=', 'cc.id')
                    ->whereIn('cccp.id_contenedor', $pageIds)
                    ->select([
                        'cccp.id_contenedor',
                        DB::raw('COALESCE(SUM(IF(cc.estado_cotizador = "CONFIRMADO", cccp.cbm_total_china, 0)),0) as cbm_total_china'),
                        DB::raw('(
                            SELECT COALESCE(SUM(volumen), 0)
                            FROM contenedor_consolidado_cotizacion
                            WHERE id_contenedor = cccp.id_contenedor
                            AND estado_cotizador = "CONFIRMADO"
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
                    ->join('contenedor_consolidado_cotizacion as cc', 'p.id_cotizacion', '=', 'cc.id')
                    ->whereIn('p.id_contenedor', $pageIds)
                    ->select([
                        'p.id_contenedor',
                        // China: sólo confirmados
                        DB::raw('SUM(IF(cc.estado_cotizador = "CONFIRMADO", p.cbm_total_china, 0)) as sum_china'),
                        // Perú: confirmados y embarcados (LOADED)
                        DB::raw('(SELECT COALESCE(SUM(cc2.volumen), 0)
                                    FROM contenedor_consolidado_cotizacion cc2
                                    WHERE cc2.id IN (
                                        SELECT DISTINCT p2.id_cotizacion
                                        FROM contenedor_consolidado_cotizacion_proveedores p2
                                        WHERE p2.id_contenedor = p.id_contenedor
                                    )
                                    AND cc2.estado_cotizador = "CONFIRMADO") as sum_peru')
                    ])
                    ->whereNull('cc.id_cliente_importacion')
                    ->groupBy('p.id_contenedor')
                    ->get();
                foreach ($embRows as $r) {
                    $cbmEmbarcados[$r->id_contenedor] = [
                        'peru' => (float)$r->sum_peru,
                        'china' => (float)$r->sum_china,
                    ];
                }
            }

            $items = collect($data->items())->map(function ($c) use ($cbmVendidos, $cbmEmbarcados) {
                $cbm_total_peru = 0;
                $cbm_total_china = 0;
                if ($c->estado_china === Contenedor::CONTEDOR_CERRADO) {
                    $vals = $cbmEmbarcados[$c->id] ?? ['peru' => 0, 'china' => 0];
                    $cbm_total_peru = $vals['peru'];
                    $cbm_total_china = $vals['china'];
                } else {
                    $vals = $cbmVendidos[$c->id] ?? ['peru' => 0, 'china' => 0];
                    $cbm_total_peru = $vals['peru'];
                    $cbm_total_china = $vals['china'];
                }
                return [
                    'id' => $c->id,
                    'carga' => $c->carga,
                    'mes' => $c->mes,
                    'anio' => date('Y', strtotime($c->f_inicio)),
                    'f_cierre' => $c->f_cierre,
                    'f_puerto' => $c->f_puerto,
                    'f_entrega' => $c->f_entrega,
                    'fecha_arribo' => $c->fecha_arribo,
                    'fecha_declaracion' => $c->fecha_declaracion,
                    'fecha_levante' => $c->fecha_levante,
                    'fecha_zarpe' => $c->fecha_zarpe,
                    'empresa' => $c->empresa,
                    'estado_documentacion' => $c->estado_documentacion,
                    'estado_china' => $c->estado_china,
                    'pais' => $c->pais,
                    'tipo_contenedor' => $c->tipo_contenedor,
                    'canal_control' => $c->canal_control,
                    'naviera' => $c->naviera,
                    'ajuste_valor' => $c->ajuste_valor,
                    'multa' => $c->multa,
                    'valor_fob' => $c->valor_fob,
                    'valor_flete' => $c->valor_flete,
                    'costo_destino' => $c->costo_destino,
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

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor",
     *     tags={"Carga Consolidada"},
     *     summary="Crear o actualizar contenedor",
     *     description="Crea un nuevo contenedor o actualiza uno existente",
     *     operationId="storeContenedor",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", nullable=true),
     *             @OA\Property(property="carga", type="string"),
     *             @OA\Property(property="mes", type="integer"),
     *             @OA\Property(property="f_cierre", type="string", format="date"),
     *             @OA\Property(property="ID_Pais", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contenedor creado/actualizado exitosamente"),
     *     @OA\Response(response=500, description="Error al crear contenedor")
     * )
     */
    public function store(Request $request)

    {
        try {
            $data = $request->all();
                if ($data['id']) {
                    $contenedor = Contenedor::find($data['id']);
                    $contenedor->update($data);
                } else {
                    // Calcular f_inicio usando mes (campo mes) y año de f_cierre
                    if (!empty($data['f_cierre'])) {
                        $year = date('Y', strtotime($data['f_cierre']));
                        // Determinar mes: si es numérico válido usarlo, sino fallback al mes de f_cierre
                        $month = null;
                        if (isset($data['mes']) && is_numeric($data['mes']) && (int)$data['mes'] >= 1 && (int)$data['mes'] <= 12) {
                            $month = (int)$data['mes'];
                        } else {
                            $month = (int)date('m', strtotime($data['f_cierre']));
                        }
                        $data['f_inicio'] = sprintf('%04d-%02d-01', $year, $month);
                    }

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
        $administracionSteps = $this->getAdministracionSteps($idContenedor);
        $this->insertSteps($cotizadorSteps, $documentacionSteps, $administracionSteps);
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
    public function getAdministracionSteps($idContenedor)
    {
        $stepsAdministracion = [];
        $idContenedor = intval($idContenedor);
        $index = 1;
        foreach ($this->defaultAdministracion as $step) {
            $stepsAdministracion[] = [
                "id_pedido" => $idContenedor,
                'id_order' => $index,
                'tipo' => 'ADMINISTRACION',
                'name' => $step['name'],
                'iconURL' => $step['iconURL'],
                'status' => 'PENDING'
            ];
            $index++;
        }
        return $stepsAdministracion;
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
    
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/{id}",
     *     tags={"Carga Consolidada"},
     *     summary="Obtener contenedor",
     *     description="Obtiene los detalles de un contenedor específico",
     *     operationId="showContenedor",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Contenedor obtenido exitosamente")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/{id}",
     *     tags={"Carga Consolidada"},
     *     summary="Eliminar contenedor",
     *     description="Elimina un contenedor y todos sus datos asociados",
     *     operationId="destroyContenedor",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Contenedor eliminado exitosamente"),
     *     @OA\Response(response=500, description="Error al eliminar")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/filter-options",
     *     tags={"Contenedor"},
     *     summary="Obtener opciones de filtro",
     *     description="Obtiene las opciones de filtro disponibles para contenedores",
     *     operationId="filterOptionsContenedor",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Opciones obtenidas exitosamente")
     * )
     */
    public function filterOptions()
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor filter options']);
    }
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/{idContenedor}/pasos",
     *     tags={"Contenedor"},
     *     summary="Obtener pasos del contenedor",
     *     description="Obtiene los pasos de proceso del contenedor según el rol del usuario",
     *     operationId="getContenedorPasos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Pasos obtenidos exitosamente")
     * )
     */
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
                case Usuario::ROL_ADMINISTRACION:
                    $query->where('tipo', 'COTIZADOR')->where('id_order', '>', 1);
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

        $ruta = trim($ruta);

        // Caso 1: si ya es una URL válida y NO está rota, devolverla tal cual.
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            // Evitar duplicados tipo baseUrl/baseUrl/...
            $base = rtrim(config('app.url'), '/');
            if (substr_count($ruta, $base) > 1) {
                // Quitar repeticiones dejando la última aparición
                $parts = explode($base, $ruta);
                $ruta = $base . end($parts);
            }
            return $ruta;
        }

        // Caso 2: URL mal formada local, ej: http://localhost:8000assets/icons/xxx.png (falta / tras puerto)
        // Normalizar insertando la barra para que luego podamos recortar.
        $ruta = preg_replace('#^(https?://[^/]+)(assets/)#i', '$1/$2', $ruta);

        // Caso 3: Si contiene múltiples http/https (concatenaciones erróneas) conservar sólo la última
        if (preg_match_all('#https?://#i', $ruta, $m) && count($m[0]) > 1) {
            $last = strrpos($ruta, 'http');
            $ruta = substr($ruta, $last);
        }

        // Caso 4: Si contiene 'assets/icons' recortamos desde allí, eliminando cualquier dominio previo (aun si estaba roto)
        if (strpos($ruta, 'assets/icons') !== false) {
            $ruta = substr($ruta, strpos($ruta, 'assets/icons'));
        }

        // Caso 5: eliminar cualquier residuo de dominio de nuestro host si quedó pegado
        $ruta = preg_replace('#^https?://[^/]+/#i', '', $ruta); // dominio + /
        $ruta = preg_replace('#^https?://[^/]+#i', '', $ruta);  // dominio sin /

        // Asegurar ruta relativa limpia
        $ruta = ltrim($ruta, '/');

        // Si después de limpiar no arranca con assets/, no forzamos nada: devolvemos base + original limpio
        $baseUrl = rtrim(config('app.url'), '/');
        if (!preg_match('#^(assets/|storage/)#', $ruta)) {
            return $baseUrl . '/' . ltrim($ruta, '/');
        }

        return $baseUrl . '/' . ltrim($ruta, '/');
    }
    public function getValidContainers()
    {
        // Obtener los contenedores existentes
        $existingContainers = Contenedor::where('empresa', '!=', '1')->
        //where year f_inicio is current year
        whereYear('f_inicio', date('Y'))->
        pluck('carga')->toArray();
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
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/cargas-disponibles",
     *     tags={"Contenedor"},
     *     summary="Obtener cargas disponibles",
     *     description="Obtiene la lista de cargas disponibles",
     *     operationId="getCargasDisponibles",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Cargas obtenidas exitosamente")
     * )
     */
    public function getCargasDisponibles()
    {
        $hoy = date('Y-m-d');
        $query = Contenedor::where('empresa', '!=', 1)
            ->orderByRaw('CAST(carga AS UNSIGNED) DESC');
        return $query->get();
    }
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/cargas-disponibles-dropdown",
     *     tags={"Contenedor"},
     *     summary="Obtener cargas disponibles dropdown",
     *     @OA\Parameter(name="year", in="query", required=false, @OA\Schema(type="integer")),
     *     description="Obtiene la lista de cargas disponibles para dropdown",
     *     operationId="getCargasDisponiblesDropdown",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Cargas obtenidas exitosamente")
     * )
     */
    public function getCargasDisponiblesDropdown(Request $request){
        $year = $request->year?$request->year:date('Y');
        $cargas = Contenedor::where('empresa', '!=', 1)
            ->whereYear('f_inicio', $year)
            //get row with f_inicio null where year is 2025
            ->where(function($query) use ($year){
                $query->whereYear('f_inicio', $year)
                    ->orWhereNull('f_inicio');
            })
            ->where('estado_china', '!=', Contenedor::CONTEDOR_CERRADO)
            ->orderByRaw('CAST(carga AS UNSIGNED) DESC')
            ->get();
        //return value label 
        return $cargas->map(function($carga){
            return [
                'value' => $carga->id,
                'label' => 'Contenedor #'.$carga->carga.' - '.Carbon::parse($carga->f_inicio??'2025-01-01')->format('Y'),
            ];
        });
        return response()->json(['data' => $cargas, 'success' => true]);
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/cotizaciones/mover-consolidado",
     *     tags={"Contenedor"},
     *     summary="Mover cotización a consolidado",
     *     description="Mueve una cotización de un contenedor a otro (consolidado)",
     *     operationId="moveCotizacionToConsolidado",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="idCotizacion", type="integer"),
     *             @OA\Property(property="idContenedorDestino", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cotización movida exitosamente"),
     *     @OA\Response(response=404, description="Cotización no encontrada")
     * )
     */
    public function moveCotizacionToConsolidado(Request $request)
    {
        try {
            $data = $request->all();
            $idCotizacion = $data['idCotizacion'];
            $idContenedorDestino = $data['idContenedorDestino'];
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }
            $idContenedorOrigen=Cotizacion::find($idCotizacion)->id_contenedor;

            $cotizacion->id_contenedor = $idContenedorDestino;
            $cotizacion->estado_cotizador = 'CONFIRMADO';
            $cotizacion->updated_at = date('Y-m-d H:i:s');
            $cotizacion->save();
            $contenedorDestino=Contenedor::find($idContenedorDestino);
            $contenedorOrigen=Contenedor::find($idContenedorOrigen);
            $cargaOrigen=$contenedorOrigen->carga;
            // Actualiza los proveedores asociados
            //find all pagos with this idCotizacion and update id_contenedor to idContenedorDestino
            $pagos = Pago::where('id_cotizacion', $idCotizacion)->get();
            foreach ($pagos as $pago) {
                $pago->id_contenedor = $idContenedorDestino;
                $pago->save();
            }
            $proveedores = CotizacionProveedor::where('id_cotizacion', $idCotizacion)->get();
            if (!$proveedores || $proveedores->isEmpty()) {
                return response()->json(['message' => 'Proveedores no encontrados', 'success' => false], 404);
            }
            foreach ($proveedores as $proveedor) {
                Log::info('proveedor' . json_encode($proveedor));
                $proveedor->id_contenedor = $idContenedorDestino;
                $proveedor->save();
            }

            // Crear notificaciones para Coordinación y Jefe de Ventas
            $this->crearNotificacionesMovimientoConsolidado($cotizacion, $idContenedorOrigen, $idContenedorDestino);
          
            $message = "Hola @nombrecliente, tu carga que estaba proyectado subir en el consolidado @cargaOrigen estamos pasándolo al @contenedorDestino,ya que al parecer tu pedido no llego a la fecha de cierre. 
Le estaré informando cualquier avance 🫡.";
/**if cargaDestino to int < carga origen use this message instead Hola (nombre), tu carga que estaba proyectado subir en el consolidado # , estará lista antes de lo previsto. Para agilizar tu importación, la hemos pasado al consolidado #
Le estaré informando cualquier avance 🫡 */
            if ($contenedorDestino->carga < $cargaOrigen) {
                $message = "Hola @nombrecliente, tu carga que estaba proyectado subir en el consolidado @cargaOrigen estará lista antes de lo previsto. Para agilizar tu importación, la hemos pasado al consolidado @contenedorDestino
Le estaré informando cualquier avance 🫡.";
            }
            $message = str_replace('@nombrecliente', $cotizacion->nombre, $message);
            $message = str_replace('@contenedorDestino', '#'.$contenedorDestino->carga, $message);
            $message = str_replace('@cargaOrigen', '#'.$cargaOrigen, $message);
            $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
            $telefono = $telefono ? $telefono . '@c.us' : '';
            // TEMPORALMENTE DESHABILITADO: Número de ventas bloqueado
            // $this->sendMessageVentas($message, $telefono, 3);
            return response()->json(['message' => 'Cotización movida a consolidado correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al mover cotización a consolidado: ' . $e->getMessage(), 'success' => false], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/cotizaciones/mover-calculadora",
     *     tags={"Contenedor"},
     *     summary="Mover cotización a calculadora",
     *     description="Mueve una cotización a la calculadora de importación",
     *     operationId="moveCotizacionToCalculadora",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="idCotizacion", type="integer"),
     *             @OA\Property(property="idContenedorDestino", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cotización movida exitosamente"),
     *     @OA\Response(response=404, description="Calculadora no encontrada")
     * )
     */
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
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/vendedores-dropdown",
     *     tags={"Contenedor"},
     *     @OA\Parameter(name="fecha_inicio", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="fecha_fin", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="id_contenedor", in="query", required=false, @OA\Schema(type="integer")),
     *     summary="Obtener vendedores para dropdown",
     *     description="Obtiene la lista de vendedores para usar como dropdown",
     *     operationId="getVendedoresDropdown",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Vendedores obtenidos exitosamente")
     * )
     */
    public function getVendedoresDropdown(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            $idContenedor = $request->input('id_contenedor');

            $query = DB::table('usuario as u')
                ->select([
                    'u.ID_Usuario as id',
                    'u.No_Nombres_Apellidos as nombre',
                    DB::raw('COUNT(DISTINCT cc.id) as total_cotizaciones'),
                    DB::raw('COALESCE(SUM(cccp.cbm_total), 0) as volumen_total')
                ])
                ->join('contenedor_consolidado_cotizacion as cc', 'u.ID_Usuario', '=', 'cc.id_usuario')
                ->join('contenedor_consolidado_cotizacion_proveedores as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                ->join('carga_consolidada_contenedor as cont', 'cc.id_contenedor', '=', 'cont.id')
                ->groupBy('u.ID_Usuario', 'u.No_Nombres_Apellidos');

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('cont.fecha_zarpe', [$fechaInicio, $fechaFin]);
            }

            if ($idContenedor) {
                $query->where('cc.id_contenedor', $idContenedor);
            }
            //not returns row with nombre  contains Danitza Leonardo y frank
            $query->whereNotIn('u.No_Nombres_Apellidos', ['Danitza', 'Leonardo', 'Frank Oviedo']);
           
            $vendedores = $query->get()->map(function($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->nombre,
                    'total_cotizaciones' => $item->total_cotizaciones,
                    'volumen_total' => round($item->volumen_total, 2)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $vendedores
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getVendedoresFiltro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vendedores para filtro',
                'error' => $e->getMessage()
            ], 500);
        }
    }
/**
    * @OA\Put(
    *     path="/carga-consolidada/contenedor/estado-documentacion",
    *     tags={"Contenedor"},
    *     summary="Actualizar estado de documentación",
    *     description="Actualiza el estado de documentación de un contenedor",
    *     operationId="updateEstadoDocumentacion",
    *     security={{"bearerAuth":{}}},
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(
    *             @OA\Property(property="id", type="integer"),
    *             @OA\Property(property="estado_documentacion", type="string")
    *         )
    *     ),
    *     @OA\Response(response=200, description="Estado actualizado exitosamente"),
    *     @OA\Response(response=404, description="Contenedor no encontrado")
    * )
    */
    
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
    /**
     * @OA\Put(
     *     path="/carga-consolidada/contenedor/{idcontenedor}/fecha-documentacion-max",
     *     tags={"Contenedor"},
     *     summary="Actualizar fecha máxima de documentación",
     *     description="Actualiza la fecha máxima de documentación de un contenedor",
     *     operationId="updateFechaDocumentacionMax",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idcontenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fecha_documentacion_max", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Fecha actualizada exitosamente"),
     *     @OA\Response(response=404, description="Contenedor no encontrado"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     *
     * Actualiza la columna fecha_documentacion_max de un contenedor.
     * Espera un body { "fecha_documentacion_max": "YYYY-MM-DD" } y el id del contenedor en la ruta.
     */
    public function updateFechaDocumentacionMax(Request $request, $idcontenedor)
    {
        try {
            $this->validate($request, [
                'fecha_documentacion_max' => 'required|date_format:Y-m-d'
            ]);

            $contenedor = Contenedor::find($idcontenedor);
            if (!$contenedor) {
                return response()->json(['success' => false, 'message' => 'Contenedor no encontrado'], 404);
            }

            $contenedor->fecha_documentacion_max = $request->input('fecha_documentacion_max');
            $contenedor->save();

            return response()->json([
                'success' => true,
                'message' => 'Fecha de documentación máxima actualizada correctamente',
                'data' => ['fecha_documentacion_max' => $contenedor->fecha_documentacion_max]
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['success' => false, 'message' => $ve->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar fecha_documentacion_max: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar fecha_documentacion_max: ' . $e->getMessage()], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/packing-list",
     *     tags={"Contenedor"},
     *     summary="Subir packing list",
     *     description="Sube un archivo de packing list para un contenedor",
     *     operationId="uploadPackingListContenedor",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idContenedor", type="integer"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Archivo subido exitosamente"),
     *     @OA\Response(response=400, description="Archivo no enviado o tipo no permitido")
     * )
     */
    public function uploadPackingList(Request $request)
    {
        try {
            $idContenedor = (int) $request->input('idContenedor');

            if (!$request->hasFile('file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se ha enviado ningún archivo',
                ], 400);
            }

            $file = $request->file('file');

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (!in_array($fileExtension, $allowedExtensions)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de archivo no permitido',
                ], 400);
            }

            $filename = time() . '_' . uniqid() . '.' . $fileExtension;
            $path = 'assets/images/agentecompra/';
            $fileUrl = $file->storeAs($path, $filename, 'public');

            $user = auth()->user();
            $userId = $user ? $user->ID_Usuario : null;
            $userGroup = $user ? $user->No_Grupo : null;

            ProcessPackingListUploadJob::dispatch(
                $idContenedor,
                $fileUrl,
                $file->getClientOriginalName(),
                $file->getSize(),
                $userId,
                $userGroup
            )->onQueue('importaciones');

            return response()->json([
                'success' => true,
                'message' => 'Lista de embarque recibida. Procesaremos la información en segundo plano.',
                'data' => [
                    'file_url' => $fileUrl,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en uploadListaEmbarque: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al subir la lista de embarque: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/{idcontenedor}/verify-completed",
     *     tags={"Contenedor"},
     *     summary="Verificar si contenedor está completado",
     *     description="Verifica y actualiza el estado del contenedor según su lista de embarque",
     *     operationId="verifyContainerIsCompleted",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idcontenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Verificación completada")
     * )
     */
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
    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/{idContenedor}/packing-list",
     *     tags={"Contenedor"},
     *     summary="Eliminar packing list",
     *     description="Elimina el packing list de un contenedor",
     *     operationId="deletePackingListContenedor",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Packing list eliminado exitosamente"),
     *     @OA\Response(response=500, description="Error al eliminar")
     * )
     */
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
    private function crearNotificacionesMovimientoConsolidado($cotizacion, $idContenedorOrigen, $idContenedorDestino)
    {
        try {
            // Obtener los contenedores
            $contenedorOrigen = Contenedor::find($idContenedorOrigen);
            $contenedorDestino = Contenedor::find($idContenedorDestino);
            
            if (!$contenedorOrigen) {
                Log::warning('Contenedor origen no encontrado: ' . $idContenedorOrigen);
                return;
            }
            
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
            
            // Disparar evento de cambio de contenedor
            try {
                $message = "El usuario {$usuarioActual->No_Nombres_Apellidos} movió la cotización de {$cotizacion->nombre} del contenedor {$contenedorOrigen->carga} al contenedor {$contenedorDestino->carga}";
                CotizacionChangeContainer::dispatch($cotizacion, $contenedorOrigen, $contenedorDestino, $usuarioActual, $message);
            } catch (\Exception $e) {
                Log::error('Error al disparar evento CotizacionChangeContainer: ' . $e->getMessage());
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
            //creat tambien para un cotizador 
            $notificacionCotizador = Notificacion::create([
                'titulo' => 'Cotización Movida a Consolidado',
                'mensaje' => "El usuario {$usuarioActual->No_Nombres_Apellidos} movió la cotización de {$cotizacion->nombre} al contenedor {$contenedorDestino->carga}",
                'descripcion' => "Cotización #{$cotizacion->id} | Cliente: {$cotizacion->nombre} | Documento: {$cotizacion->documento} | Volumen: {$cotizacion->volumen} CBM | Contenedor destino: {$contenedorDestino->carga}",
                'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
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
           

            return [$notificacionCoordinacion, $notificacionJefeVentas, $notificacionCotizador];
        } catch (\Exception $e) {
            Log::error('Error al crear notificaciones de movimiento a consolidado para Coordinación y Jefe de Ventas: ' . $e->getMessage());
            // No lanzar excepción para no afectar el flujo principal de movimiento
            return null;
        }
    }

    

    


}
