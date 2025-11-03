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
use App\Traits\WhatsappTrait;
use App\Traits\GoogleSheetsHelper;

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
            ["name" => "COTIZACION", "iconURL" => $host . '/assets/icons/cotizacion.png'],
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
        $query = Contenedor::where('empresa', '!=', 1)
            ->orderByRaw('CAST(carga AS UNSIGNED) DESC');
        return $query->get();
    }
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
            $cotizacion->id_contenedor = $idContenedorDestino;
            $cotizacion->estado_cotizador = 'CONFIRMADO';
            $cotizacion->updated_at = date('Y-m-d H:i:s');
            $cotizacion->save();
            $contenedorDestino=Contenedor::find($idContenedorDestino);
            // Actualiza los proveedores asociados
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
            $this->crearNotificacionesMovimientoConsolidado($cotizacion, $idContenedorDestino);
          
            $message = "Hola @nombrecliente, segun lo conversado estamos pasando su carga para el consolidado @contenedorDestino.
Le estaré informando cualquier avance 🫡.";
            $message = str_replace('@nombrecliente', $cotizacion->nombre, $message);
            $message = str_replace('@contenedorDestino', '#'.$contenedorDestino->carga, $message);
            $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
            $telefono = $telefono ? $telefono . '@c.us' : '';
            $this->sendMessage($message, $telefono, 3);
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
    /**
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

            // Validar tamaño del archivo 400 MB
            $maxFileSize = 400 * 1024 * 1024; // 400 MB en bytes
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

            $contenedor->update([
                'lista_embarque_url' => $fileUrl,
                'lista_embarque_uploaded_at' => date('Y-m-d H:i:s')
            ]);

            // Verificar si el contenedor está completado
            $this->verifyContainerIsCompleted($idContenedor);
            
            // Validar usuarios en cotizaciones con proveedores cargados
            $this->validateUsersInCotizacionesWithLoadedProveedores($idContenedor);
            $carga = Contenedor::find($idContenedor);
            $sheetName = 'CONSOLIDADO ' . $carga->carga;
            
            // Crear hoja "CONSOLIDADO CARGA" en el spreadsheet de estado
            try {
                $spreadsheetId = config('google.post_sheet_status_doc_id');
                if ($spreadsheetId) {
                    $sheetId = $this->createSheet($sheetName, $spreadsheetId);
                    if ($sheetId) {
                        Log::info("Hoja {$sheetName} creada exitosamente para contenedor ID: {$idContenedor}");
                        
                        // Obtener cotizaciones con proveedores para poblar la hoja
                        $cotizaciones = $this->getCotizacionesForSheet($idContenedor);
                        
                        if (!empty($cotizaciones)) {
                            // Poblar la hoja con los datos
                            $numeroCarga = $carga->carga ?? '';
                            $this->populateConsolidadoSheet($sheetName, $spreadsheetId, $cotizaciones, $numeroCarga);
                            Log::info("Hoja {$sheetName} poblada exitosamente con " . count($cotizaciones) . " cotizaciones");
                        } else {
                            Log::warning("No se encontraron cotizaciones para poblar la hoja {$sheetName}");
                        }
                    } else {
                        Log::warning("No se pudo crear la hoja {$sheetName} para contenedor ID: {$idContenedor}");
                    }
                } else {
                    Log::warning("POST_SHEET_STATUS_DOC_ID no está configurado en el archivo .env");
                }
            } catch (\Exception $e) {
                // No fallar la respuesta si hay error al crear/poblar la hoja
                Log::error("Error al crear/poblar hoja {$sheetName}: " . $e->getMessage());
            }
            
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

    /**
     * Validar usuarios en cotizaciones con proveedores cargados
     * Usa la misma validación del comando PopulateClientesData
     */
    private function validateUsersInCotizacionesWithLoadedProveedores($idContenedor)
    {
        try {
            Log::info('🔍 Iniciando validación de usuarios en cotizaciones con proveedores cargados', [
                'contenedor_id' => $idContenedor
            ]);

            // Obtener cotizaciones del contenedor que tienen proveedores con estado_china = 'LOADED'
            $cotizaciones = DB::table('contenedor_consolidado_cotizacion as ccc')
                ->join('contenedor_consolidado_cotizacion_proveedores as cccp', 'ccc.id', '=', 'cccp.id_cotizacion')
                ->where('ccc.id_contenedor', $idContenedor)
                ->where('cccp.estados_proveedor', 'LOADED')
                ->whereNotNull('ccc.nombre')
                ->where('ccc.nombre', '!=', '')
                ->whereRaw('LENGTH(TRIM(ccc.nombre)) >= 2')
                ->where(function ($query) {
                    $query->whereNotNull('ccc.telefono')
                        ->where('ccc.telefono', '!=', '')
                        ->whereRaw('LENGTH(TRIM(ccc.telefono)) >= 7')
                        ->orWhereNotNull('ccc.documento')
                        ->where('ccc.documento', '!=', '')
                        ->whereRaw('LENGTH(TRIM(ccc.documento)) >= 5')
                        ->orWhereNotNull('ccc.correo')
                        ->where('ccc.correo', '!=', '')
                        ->whereRaw('ccc.correo REGEXP "^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$"');
                })
                ->select('ccc.id', 'ccc.telefono', 'ccc.nombre', 'ccc.documento', 'ccc.correo')
                ->distinct()
                ->get();

            Log::info('Cotizaciones encontradas con proveedores cargados: ' . $cotizaciones->count());

            $validados = 0;
            $clientesCreados = 0;
            $clientesEncontrados = 0;

            foreach ($cotizaciones as $cotizacion) {
                // Convertir a objeto para usar las mismas validaciones
                $clienteData = [
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'correo' => $cotizacion->correo,
                    'telefono' => $cotizacion->telefono
                ];

                $clienteObj = (object)$clienteData;

                // Usar la misma validación del comando
                if ($this->validateClienteDataFromCommand($clienteObj)) {
                    $validados++;
                    $clienteId = $this->insertOrGetClienteFromCommand($clienteObj, 'cotizacion_proveedor_loaded');
                    
                    if ($clienteId) {
                        // Verificar si el cliente ya existía o fue creado
                        $clienteExistia = DB::table('clientes')->where('id', $clienteId)->exists();
                        
                        if ($clienteExistia) {
                            $clientesEncontrados++;
                        } else {
                            $clientesCreados++;
                        }

                        Log::info("✅ Cliente validado para cotización con proveedor cargado", [
                            'cotizacion_id' => $cotizacion->id,
                            'cliente_id' => $clienteId,
                            'nombre' => $clienteObj->nombre,
                            'fue_creado' => !$clienteExistia
                        ]);
                    }
                } else {
                    Log::warning("❌ Cliente no válido en cotización con proveedor cargado", [
                        'cotizacion_id' => $cotizacion->id,
                        'nombre' => $clienteObj->nombre,
                        'telefono' => $clienteObj->telefono,
                        'documento' => $clienteObj->documento,
                        'correo' => $clienteObj->correo
                    ]);
                }
            }

            Log::info('🎉 Validación completada', [
                'contenedor_id' => $idContenedor,
                'total_procesados' => $cotizaciones->count(),
                'validados' => $validados,
                'clientes_encontrados' => $clientesEncontrados,
                'clientes_creados' => $clientesCreados
            ]);

        } catch (\Exception $e) {
            Log::error('Error en validación de usuarios con proveedores cargados: ' . $e->getMessage(), [
                'contenedor_id' => $idContenedor,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validar que el cliente tenga al menos un campo de contacto válido
     * (Copiado del comando PopulateClientesData)
     */
    private function validateClienteDataFromCommand($data)
    {
        $telefono = trim($data->telefono ?? '');
        $documento = trim($data->documento ?? '');
        $correo = trim($data->correo ?? '');
        $nombre = trim($data->nombre ?? '');

        // Validar que tenga nombre válido (no vacío y no solo espacios)
        if (empty($nombre) || strlen($nombre) < 2) {
            return false;
        }

        // Validar que tenga al menos uno de los tres campos de contacto válidos
        $hasValidPhone = !empty($telefono) && strlen($telefono) >= 7;
        $hasValidDocument = !empty($documento) && strlen($documento) >= 5;
        $hasValidEmail = !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL);

        if (!$hasValidPhone && !$hasValidDocument && !$hasValidEmail) {
            return false;
        }

        return true;
    }

    /**
     * Normalizar número de teléfono eliminando espacios, caracteres especiales y +
     * (Copiado del comando PopulateClientesData)
     */
    private function normalizePhoneFromCommand($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Eliminar espacios, guiones, paréntesis, puntos y símbolo +
        $normalized = preg_replace('/[\s\-\(\)\.\+]/', '', $phone);

        // Solo mantener números
        $normalized = preg_replace('/[^0-9]/', '', $normalized);

        return $normalized ?: null;
    }

    /**
     * Insertar cliente si no existe, o retornar ID si ya existe
     * (Copiado del comando PopulateClientesData)
     */
    private function insertOrGetClienteFromCommand($data, $fuente = 'desconocida')
    {
        // Normalizar teléfono
        $telefonoNormalizado = $this->normalizePhoneFromCommand($data->telefono ?? null);

        // Buscar por teléfono normalizado primero
        $cliente = null;
        
        // Validar que el teléfono no sea nulo o vacío antes de procesar
        if (!empty($telefonoNormalizado) && $telefonoNormalizado !== null) {
            $cliente = DB::table('clientes')
                ->where('telefono', 'like', $telefonoNormalizado)
                ->first();
            
            if ($cliente) {
                return $cliente->id;
            }
        }

        // Si no se encuentra por teléfono, buscar por documento
        // Validar que el documento no sea nulo o vacío antes de procesar
        if (!$cliente && !empty(trim($data->documento ?? '')) && trim($data->documento ?? '') !== null) {
            $cliente = DB::table('clientes')
                ->where('documento', $data->documento)
                ->first();
                
            if ($cliente) {
                return $cliente->id;
            }
        }

        // Si no se encuentra por documento, buscar por correo
        // Validar que el correo no sea nulo o vacío antes de procesar
        if (!$cliente && !empty(trim($data->correo ?? '')) && trim($data->correo ?? '') !== null) {
            $cliente = DB::table('clientes')
                ->where('correo', $data->correo)
                ->first();
                
            if ($cliente) {
                return $cliente->id;
            }
        }

        // Validación final antes de insertar
        $nombre = trim($data->nombre ?? '');
        $documento = !empty($data->documento) ? trim($data->documento) : null;
        $correo = !empty($data->correo) ? trim($data->correo) : null;

        // Verificar que el nombre sea válido
        if (empty($nombre) || strlen($nombre) < 2) {
            return null;
        }

        // Verificar que tenga al menos un método de contacto válido
        $hasValidContact = false;
        if (!empty($telefonoNormalizado) && strlen($telefonoNormalizado) >= 7) {
            $hasValidContact = true;
        }
        if (!$hasValidContact && !empty($documento) && strlen($documento) >= 5) {
            $hasValidContact = true;
        }
        if (!$hasValidContact && !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $hasValidContact = true;
        }

        if (!$hasValidContact) {
            return null;
        }

        try {
            // Insertar nuevo cliente
            $clienteId = DB::table('clientes')->insertGetId([
                'nombre' => $nombre,
                'documento' => $documento,
                'correo' => $correo,
                'telefono' => $telefonoNormalizado,
                'fecha' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return $clienteId;
        } catch (\Exception $e) {
            Log::error("Error al insertar cliente desde {$fuente}: " . $e->getMessage(), [
                'nombre' => $nombre,
                'telefono' => $telefonoNormalizado,
                'documento' => $documento,
                'correo' => $correo
            ]);
            return null;
        }
    }

    /**
     * Obtener cotizaciones con proveedores para poblar la hoja de consolidado
     * 
     * @param int $idContenedor ID del contenedor
     * @return array Array de cotizaciones con proveedores
     */
    private function getCotizacionesForSheet($idContenedor)
    {
        try {
            // Obtener solo cotizaciones que tengan al menos un proveedor con estados_proveedor = 'LOADED'
            $query = DB::table('contenedor_consolidado_cotizacion AS main')
                ->select([
                    'main.id',
                    'main.nombre',
                    'main.documento',
                    'main.id_contenedor'
                ])
                ->where('main.id_contenedor', $idContenedor)
                ->whereNull('main.id_cliente_importacion')
                ->where('main.estado_cotizador', 'CONFIRMADO')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                        ->whereRaw('proveedores.id_cotizacion = main.id')
                        ->where('proveedores.estados_proveedor', 'LOADED');
                })
                ->orderBy('main.id', 'asc');

            $cotizaciones = $query->get();

            // Para cada cotización, obtener TODOS los proveedores (no solo LOADED)
            // porque la cotización ya fue filtrada por tener al menos uno LOADED
            $cotizacionesConProveedores = [];
            foreach ($cotizaciones as $cotizacion) {
                // Obtener TODOS los proveedores de esta cotización (no solo LOADED)
                // La cotización ya fue filtrada por tener al menos uno LOADED
                $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id_cotizacion', $cotizacion->id)
                    ->select([
                        'id',
                        'id_cotizacion', // Incluir para validación
                        'factura_comercial',
                        'packing_list',
                        'excel_confirmacion',
                        'supplier',
                        'code_supplier',
                        'estados_proveedor'
                    ])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->toArray();

                // Validar que todos los proveedores pertenezcan a esta cotización
                $proveedoresValidados = [];
                foreach ($proveedores as $proveedor) {
                    // Validación adicional: asegurar que el id_cotizacion coincida
                    if ($proveedor->id_cotizacion == $cotizacion->id) {
                        $proveedoresValidados[] = [
                            'id' => $proveedor->id,
                            'id_cotizacion' => $proveedor->id_cotizacion,
                            'factura_comercial' => $proveedor->factura_comercial ?? '',
                            'packing_list' => $proveedor->packing_list ?? '',
                            'excel_confirmacion' => $proveedor->excel_confirmacion ?? '',
                            'supplier' => $proveedor->supplier ?? '',
                            'code_supplier' => $proveedor->code_supplier ?? ''
                        ];
                    } else {
                        Log::warning("Proveedor con ID {$proveedor->id} no pertenece a cotización {$cotizacion->id}", [
                            'proveedor_id_cotizacion' => $proveedor->id_cotizacion,
                            'cotizacion_id' => $cotizacion->id
                        ]);
                    }
                }

                // Solo agregar si tiene proveedores válidos
                if (!empty($proveedoresValidados)) {
                    $cotizacionesConProveedores[] = [
                        'id' => $cotizacion->id,
                        'nombre' => $cotizacion->nombre,
                        'documento' => $cotizacion->documento,
                        'proveedores' => $proveedoresValidados
                    ];
                    
                    // Log para debugging
                    $codes = array_column($proveedoresValidados, 'code_supplier');
                    Log::info("Cotización {$cotizacion->id} ({$cotizacion->nombre}) tiene " . count($proveedoresValidados) . " proveedores", [
                        'codes' => $codes
                    ]);
                }
            }

            return $cotizacionesConProveedores;
        } catch (\Exception $e) {
            Log::error("Error obteniendo cotizaciones para hoja: " . $e->getMessage());
            return [];
        }
    }
}
