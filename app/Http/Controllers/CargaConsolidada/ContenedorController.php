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
use App\Models\CalculadoraImportacion;


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
            ["name" => "DOCUMENTACIÓN", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defautlAgenteChinaSteps = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "PAGOS Y COORDINACION", "iconURL" => env('APP_URL') . "assets/icons/coordinacion.png"],
            ["name" => "RECEPCION DE CARGA", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "BOOKING", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "DOCUMENTACIÓN", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defaultJefeChina = array(
            ["name" => "ORDEN DE COMPRA", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "PAGOS Y COORDINACION", "iconURL" => env('APP_URL') . "assets/icons/coordinacion.png"],
            ["name" => "RECEPCION E INSPECCION", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "BOOKING", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "DOCUMENTACIÓN", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defaultCotizador = array(
            ["name" => "COTIZACION", "iconURL" => env('APP_URL') . "assets/icons/cotizacion.png"],
            ["name" => "CLIENTES", "iconURL" => env('APP_URL') . "assets/icons/clientes.png"],
            ["name" => "DOCUMENTACION", "iconURL" => env('APP_URL') . "assets/icons/cdocumentacion.png"],
            ["name" => "COTIZACION FINAL", "iconURL" => env('APP_URL') . "assets/icons/cotizacion_final.png"],
            ["name" => "FACTURA Y GUIA", "iconURL" => env('APP_URL') . "assets/icons/factura.png"]
        );
        $this->defaultDocumentacion = array(
            ["name" => "CLIENTES", "iconURL" => env('APP_URL') . "assets/icons/cotizacion.png"],
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
            //order by int(carga) desc
            $query->orderBy(DB::raw('CAST(carga AS UNSIGNED)'), 'desc');
            $data = $query->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $data->items(),
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
                    if ($user->ID_Usuario == 28791) {
                        Log::info('jefe');
                        $query->limit(2);
                        break;
                    }
                    $query->limit(1);
                    break;
                case Usuario::ROL_DOCUMENTACION:
                    $query->where('tipo', 'DOCUMENTACION');
                    break;
                default:
                    $query->where('tipo', Usuario::ROL_COTIZADOR);
                    break;
            }
            $data = $query->select('id', 'name', 'status', 'iconURL')->get();
            return response()->json(['data' => $data, 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'success' => false, 'message' => 'Error al obtener pasos del contenedor: ' . $e->getMessage()]);
        }
    }
    public function getValidContainers()
    {
        // Obtener los contenedores existentes
        $existingContainers = Contenedor::pluck('carga')->toArray();

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
        $query = Contenedor::where(DB::raw('DATE(f_cierre)'), '>=', $hoy)->orderBy('carga', 'desc');
        return $query->get();
    }
    public function moveCotizacionToConsolidado(Request $request)
    {
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
        $proveedores = CotizacionProveedor::where('id_cotizacion', $idCotizacion);
        if (!$proveedores) {
            return response()->json(['message' => 'Proveedores no encontrados', 'success' => false], 404);
        }
        foreach ($proveedores as $proveedor) {
            $proveedor->id_contenedor = $idContenedorDestino;
            $proveedor->save();
        }

        return response()->json(['message' => 'Cotización movida a consolidado correctamente', 'success' => true]);
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
}
