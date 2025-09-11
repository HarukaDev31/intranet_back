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
            ["name" => "Orden de Compra", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "Pagos", "iconURL" => env('APP_URL') . "assets/icons/pagos.png"],
            ["name" => "Recepcion de Carga", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "Booking", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "Documentacion", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defautlAgenteChinaSteps = array(
            ["name" => "Orden de Compra", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "Pagos y Coordinacion", "iconURL" => env('APP_URL') . "assets/icons/coordinacion.png"],
            ["name" => "Recepcion de Carga", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "Booking", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "Documentacion", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defaultJefeChina = array(
            ["name" => "Orden de Compra", "iconURL" => env('APP_URL') . "assets/icons/orden.png"],
            ["name" => "Pagos y Coordinacion", "iconURL" => env('APP_URL') . "assets/icons/coordinacion.png"],
            ["name" => "Recepcion e Inspeccion", "iconURL" => env('APP_URL') . "assets/icons/recepcion.png"],
            ["name" => "Booking", "iconURL" => env('APP_URL') . "assets/icons/inspeccion.png"],
            ["name" => "Documentacion", "iconURL" => env('APP_URL') . "assets/icons/documentacion.png"]
        );
        $this->defaultCotizador = array(
            ["name" => "Cotizacion", "iconURL" => env('APP_URL') . "assets/icons/cotizacion.png"],
            ["name" => "Clientes", "iconURL" => env('APP_URL') . "assets/icons/clientes.png"],
            ["name" => "Documentacion", "iconURL" => env('APP_URL') . "assets/icons/cdocumentacion.png"],
            ["name" => "Cotizacion Final", "iconURL" => env('APP_URL') . "assets/icons/cotizacion_final.png"],
            ["name" => "Factura y Guia", "iconURL" => env('APP_URL') . "assets/icons/factura.png"]
        );
        $this->defaultDocumentacion = array(
            ["name" => "Cotizacion", "iconURL" => env('APP_URL') . "assets/icons/cotizacion.png"],
            ["name" => "Documentacion", "iconURL" => env('APP_URL') . "assets/icons/cdocumentacion.png"],
            ["name" => "Aduana", "iconURL" => env('APP_URL') . "assets/icons/aduana.png"],
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
        $query = Contenedor::where(DB::raw('DATE(f_cierre)'), '>=', $hoy)->where('empresa', '!=', 1)->orderBy('carga', 'desc');
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
}
