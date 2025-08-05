<?php

namespace App\Http\Controllers\BaseDatos;

use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\PedidoCurso;
use App\Models\Entidad;
use App\Models\Pais;
use App\Models\Moneda;
use App\Models\Campana;
use App\Models\ImportCliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientesController extends Controller
{
    /**
     * Ruta base para archivos Excel importados (URL pública)
     */
    private const EXCEL_IMPORTS_PATH = 'storage/excel-imports/';
    
    /**
     * Ruta física para archivos Excel importados (sistema de archivos)
     */
    private const EXCEL_IMPORTS_STORAGE_PATH = 'public/excel-imports/';
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
            if ($request->has('servicio') && !empty($request->servicio) && $request->servicio != 'todos') {
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

    /**
     * Importar datos desde Excel a cursos o cotizaciones
     */
    public function importExcel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,xlsm|max:10240', // 10MB max
            ]);

            $file = $request->file('excel_file');
            $empresaId = 1;
            $usuarioId = auth()->user()->ID_Usuario ?? 1;

            // Guardar archivo temporalmente
            $fileName = 'import_' . time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::EXCEL_IMPORTS_STORAGE_PATH, $fileName);
            $fullPath = storage_path('app/' . self::EXCEL_IMPORTS_STORAGE_PATH . $fileName);

            // Procesar el Excel y detectar tipo automáticamente
            $resultado = $this->procesarExcel($fullPath, $empresaId, $usuarioId, $fileName, $path);

            // Limpiar archivo temporal
            Storage::delete($path);

            return response()->json([
                'success' => true,
                'message' => 'Importación completada exitosamente',
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Log::error('Error en importación Excel desde ClientesController: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error durante la importación: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * obtener lista de excels 
     */

    public function obtenerListExcel()
    {
        try {
            $excels = ImportCliente::select('id', 'nombre_archivo','cantidad_rows', 'created_at','ruta_archivo')->get();
            
            // Agregar URL completa para cada archivo
            $excels->each(function ($excel) {
                $excel->url_descarga = url(self::EXCEL_IMPORTS_PATH . $excel->nombre_archivo);
            });
            
            return response()->json([
                'success' => true,
                'data' => $excels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lista de excels: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Procesar archivo Excel
     */
    private function procesarExcel($filePath, $empresaId, $usuarioId, $fileName, $fileStoragePath)
    {
        try {
            // Leer el Excel usando PhpSpreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Obtener datos desde la fila 3
            $highestRow = $worksheet->getHighestRow();

            // Detectar tipo de importación leyendo la columna F (SERVICIO) de la fila 3
            $tipoServicio = strtoupper(trim($this->obtenerValorCelda($worksheet, 3, 6))); // Columna F
            $tipoImportacion = '';

            if (strpos($tipoServicio, 'CONSOLIDADO') !== false) {
                $tipoImportacion = 'cotizaciones';
            } elseif (strpos($tipoServicio, 'CURSO') !== false) {
                $tipoImportacion = 'cursos';
            } else {
                throw new \Exception("No se pudo detectar el tipo de importación. La columna SERVICIO debe contener 'CONSOLIDADO' o 'CURSO'");
            }

            // Crear registro de importación
            $importCliente = ImportCliente::create([
                'nombre_archivo' => $fileName,
                'ruta_archivo' => self::EXCEL_IMPORTS_PATH . $fileName, // Ruta pública
                'tipo_importacion' => $tipoImportacion,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'cantidad_rows' => 0,
                'estadisticas' => null
            ]);

            $stats = [
                'creados' => 0,
                'actualizados' => 0,
                'errores' => 0,
                'detalles' => [],
                'tipo_detectado' => $tipoImportacion,
                'import_id' => $importCliente->id
            ];

            DB::beginTransaction();
            $cantidad_rows = 0;
            for ($row = 3; $row <= $highestRow; $row++) {
                try {
                    $data = $this->leerFilaExcel($worksheet, $row);

                    if (empty($data['cliente'])) {
                        continue; // Fila vacía, saltar
                    }
                    $cantidad_rows++;
                    if ($tipoImportacion === 'cursos') {
                        $this->procesarFilaCurso($data, $empresaId, $stats, $row, $importCliente->id);
                    } else {
                        $this->procesarFilaCotizacion($data, $empresaId, $stats, $row, $importCliente->id);
                    }
                } catch (\Exception $e) {
                    $stats['errores']++;
                    $stats['detalles'][] = "Fila {$row}: " . $e->getMessage();
                }
            }

            // Actualizar estadísticas en el registro de importación
            $importCliente->update([
                'estadisticas' => $stats,
                'cantidad_rows' => $cantidad_rows, // Excluyendo filas 1 y 2
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    public function deleteExcel($id)
    {
        try {
            Cliente::where('id_cliente_importacion', $id)->delete();
            Cotizacion::where('id_cliente_importacion', $id)->delete();
            PedidoCurso::where('id_cliente_importacion', $id)->delete();
            ImportCliente::where('id', $id)->delete();
            return response()->json(['success' => true, 'message' => 'Excel eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar el Excel: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Leer datos de una fila del Excel
     */
    private function leerFilaExcel($worksheet, $row)
    {
        return [
            'numero' => $this->obtenerValorCelda($worksheet, $row, 1), // Columna A - N
            'cliente' => $this->obtenerValorCelda($worksheet, $row, 2), // Columna B - CLIENTE
            'dni' => $this->obtenerValorCelda($worksheet, $row, 3), // Columna C - DNI
            'correo' => $this->obtenerValorCelda($worksheet, $row, 4), // Columna D - CORREO
            'whatsapp' => $this->obtenerValorCelda($worksheet, $row, 5), // Columna E - WHATSAPP
            'servicio' => $this->obtenerValorCelda($worksheet, $row, 6), // Columna F - SERVICIO
            'fecha' => $this->obtenerValorCelda($worksheet, $row, 7), // Columna G - FECHA
            'servicio_detalle' => $this->obtenerValorCelda($worksheet, $row, 8), // Columna H - SERVICIO (segundo)
            'ruc' => $this->obtenerValorCelda($worksheet, $row, 9), // Columna I - RUC
            'razon_social' => $this->obtenerValorCelda($worksheet, $row, 10), // Columna J - RAZON SOCIAL O NOMBRE
        ];
    }

    /**
     * Obtener valor de una celda
     */
    private function obtenerValorCelda($worksheet, $row, $col)
    {
        $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
        return trim($value ?? '');
    }


    /**
     * Procesar fila para cursos
     */
    private function procesarFilaCurso($data, $empresaId, &$stats, $row, $importId)
    {
        // Crear o encontrar entidad
        $entidad = $this->crearOEncontrarEntidad($data, $empresaId);

        // Obtener valores por defecto
        $pais = Pais::first() ?? Pais::create(['No_Pais' => 'Perú']);
        $moneda = Moneda::first() ?? Moneda::create(['No_Moneda' => 'PEN']);
        $campana = Campana::activas()->first() ?? Campana::create([
            'Fe_Creacion' => now(),
            'Fe_Inicio' => now(),
            'Fe_Fin' => now()->addYear(),
        ]);

        // Verificar si ya existe el pedido de curso
        $pedidoCurso = PedidoCurso::where('ID_Entidad', $entidad->ID_Entidad)
            ->where('ID_Empresa', $empresaId)
            ->first();

        if (!$pedidoCurso) {
            $pedidoCurso = PedidoCurso::create([
                'ID_Empresa' => $empresaId,
                'ID_Entidad' => $entidad->ID_Entidad,
                'ID_Pais' => $pais->ID_Pais,
                'ID_Moneda' => $moneda->ID_Moneda,
                'ID_Campana' => $campana->ID_Campana,
                'Fe_Emision' => $this->parsearFecha($data['fecha']),
                'Fe_Registro' => now(),
                'Ss_Total' => 0, // No hay monto en el Excel original
                'logistica_final' => 0,
                'impuestos_final' => 0,
                'Nu_Estado' => 1,
                'id_cliente_importacion' => $importId,
            ]);

            $stats['creados']++;
            $stats['detalles'][] = "Fila {$row}: Curso creado para {$data['cliente']}";
        } else {
            $stats['actualizados']++;
            $stats['detalles'][] = "Fila {$row}: Curso ya existe para {$data['cliente']}";
        }
    }

    /**
     * Procesar fila para cotizaciones
     */
    private function procesarFilaCotizacion($data, $empresaId, &$stats, $row, $importId)
    {
        try {
            $contenedor = $this->crearOEncontrarContenedor($data['servicio_detalle'], $empresaId);

        // Usar RUC como documento si está disponible, sino DNI
        $documento = !empty($data['ruc']) ? $data['ruc'] : $data['dni'];

        // Verificar si ya existe la cotización
        $cotizacion = Cotizacion::where('documento', $documento)
            ->where('id_contenedor', $contenedor->id)
            ->first();

        if (!$cotizacion) {
            $cotizacion = Cotizacion::create([
                'id_contenedor' => $contenedor->id,
                'nombre' => $data['razon_social'] ?: $data['cliente'],
                'documento' => $documento,
                'telefono' => $data['whatsapp'],
                'correo' => $data['correo'],
                'fecha' => $this->parsearFecha($data['fecha']),
                'estado' => 'PENDIENTE',
                'estado_cotizador' => 'PENDIENTE',
                'monto' => 0, // No hay monto en el Excel original
                'logistica_final' => 0,
                'impuestos_final' => 0,
                'observaciones' => "Servicio: {$data['servicio']}",
                'id_cliente_importacion' => $importId,
                'id_tipo_cliente' => 1, // Tipo cliente por defecto
            ]);
            //crea cliente
            $cliente = Cliente::create([
                'nombre' => $data['razon_social'] ?: $data['cliente'],
                'documento' => $documento,
                'telefono' => $data['whatsapp'],
                'correo' => $data['correo'],
                'fecha' => $this->parsearFecha($data['fecha']),

                'id_cliente_importacion' => $importId,
              
            ]);
            $stats['creados']++;
            $stats['detalles'][] = "Fila {$row}: Cotización creada para {$data['cliente']}";
        } else {
            $stats['actualizados']++;
            $stats['detalles'][] = "Fila {$row}: Cotización ya existe para {$data['cliente']}";
        }
        } catch (\Exception $e) {
            Log::error('Error al crear cotización: ' . $e->getMessage());
            $stats['errores']++;
            $stats['detalles'][] = "Fila {$row}: Error al crear cotización: " . $e->getMessage();
        }
    }

    /**
     * Crear o encontrar entidad
     */
    private function crearOEncontrarEntidad($data, $empresaId)
    {
        // Usar RUC como documento si está disponible, sino DNI
        $documento = !empty($data['ruc']) ? $data['ruc'] : $data['dni'];

        if (empty($documento)) {
            throw new \Exception("No se puede crear entidad sin documento (DNI o RUC)");
        }

        $entidad = Entidad::where('Nu_Documento_Identidad', $documento)
            ->where('ID_Empresa', $empresaId)
            ->first();

        if (!$entidad) {
            $pais = Pais::first() ?? Pais::create(['No_Pais' => 'Perú']);

            // Determinar tipo de documento
            $tipoDocumento = !empty($data['ruc']) ? 2 : 1; // 2 = RUC, 1 = DNI

            $entidad = Entidad::create([
                'ID_Empresa' => $empresaId,
                'Nu_Tipo_Entidad' => 1, // Cliente
                'ID_Tipo_Documento_Identidad' => $tipoDocumento,
                'Nu_Documento_Identidad' => $documento,
                'No_Entidad' => $data['razon_social'] ?: $data['cliente'],
                'Nu_Celular_Entidad' => $data['whatsapp'],
                'Txt_Email_Entidad' => $data['correo'],
                'ID_Pais' => $pais->ID_Pais,
                'Nu_Estado' => 1,
                'Fe_Registro' => now(),
            ]);
        }

        return $entidad;
    }

    /**
     * Crear o encontrar contenedor
     */
    private function crearOEncontrarContenedor($nombreContenedor, $empresaId)
    {
        if (empty($nombreContenedor)) {
            $nombreContenedor = '0';
        }
        //get all text later # example in Consolidado #10 carga is 10
        $carga = preg_replace('/#(\d+)/', '$1', $nombreContenedor);
        $contenedor = Contenedor::where('carga', $carga)
            ->where('empresa', $empresaId)
            ->first();

        if (!$contenedor) {
            $contenedor = Contenedor::create([
                'carga' => $nombreContenedor,
                'empresa' => $empresaId,
                'estado' => 'PENDIENTE',
                'mes' => $this->obtenerMesEspanol(date('n')), // Convertir a español
                'id_pais' => 1, // Perú por defecto
                'tipo_carga' => 'CARGA CONSOLIDADA',
                'estado_china' => 'PENDIENTE',
                'estado_documentacion' => 'PENDIENTE',
            ]);
        }

        return $contenedor;
    }

    /**
     * Parsear fecha desde string
     */
    private function parsearFecha($dateString)
    {
        if (empty($dateString)) {
            return now();
        }

        // Intentar diferentes formatos de fecha
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                return $date;
            } catch (\Exception $e) {
                continue;
            }
        }

        // Si no se puede parsear, usar fecha actual
        return now();
    }

    /**
     * Obtener nombre del mes en español
     */
    private function obtenerMesEspanol($numeroMes)
    {
        $meses = [
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
            9 => 'SEPTIEMBRE',
            10 => 'OCTUBRE',
            11 => 'NOVIEMBRE',
            12 => 'DICIEMBRE'
        ];

        return $meses[$numeroMes] ?? 'ENERO';
    }

    /**
     * Descargar plantilla de Excel
     */
    public function descargarPlantilla(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tipo' => 'required|in:cursos,cotizaciones'
            ]);

            $tipo = $request->tipo;
            $templatePath = storage_path("app/templates/plantilla_{$tipo}.xlsx");

            if (!file_exists($templatePath)) {
                $this->crearPlantilla($templatePath, $tipo);
            }

            return response()->json([
                'success' => true,
                'message' => 'Plantilla creada exitosamente',
                'download_url' => url("api/base-datos/clientes/descargar-plantilla/{$tipo}")
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear plantilla de Excel
     */
    private function crearPlantilla($path, $tipo)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($tipo === 'cursos') {
            // Encabezados para cursos
            $headers = [
                'A1' => 'N',
                'B1' => 'CLIENTE',
                'C1' => 'DNI',
                'D1' => 'CORREO',
                'E1' => 'WHATSAPP',
                'F1' => 'SERVICIO',
                'G1' => 'FECHA',
                'H1' => 'SERVICIO',
                'I1' => 'RUC',
                'J1' => 'RAZON SOCIAL O NOMBRE'
            ];

            // Ejemplos para cursos
            $examples = [
                ['1', 'JESUS QUESQUEN CONDORI', '', '', '981 466 498', 'CURSO', '1/01/2024', 'CURSO #1', '', 'JESUS ANTONIO QUESQUEN CONDORI'],
                ['2', 'MARIA GONZALEZ', '12345678', 'maria@email.com', '999 888 777', 'CURSO', '15/01/2024', 'CURSO #2', '', 'MARIA ELENA GONZALEZ LOPEZ'],
            ];
        } else {
            // Encabezados para cotizaciones
            $headers = [
                'A1' => 'N',
                'B1' => 'CLIENTE',
                'C1' => 'DNI',
                'D1' => 'CORREO',
                'E1' => 'WHATSAPP',
                'F1' => 'SERVICIO',
                'G1' => 'FECHA',
                'H1' => 'SERVICIO',
                'I1' => 'RUC',
                'J1' => 'RAZON SOCIAL O NOMBRE'
            ];

            // Ejemplos para cotizaciones
            $examples = [
                ['1', 'JESUS QUESQUEN CONDORI', '', '', '981 466 498', 'CONSOLIDADO', '1/01/2024', 'CONSOLIDADO #1', '10452681418', 'JESUS ANTONIO QUESQUEN CONDORI'],
                ['2', 'MARIA GONZALEZ', '12345678', 'maria@email.com', '999 888 777', 'CONSOLIDADO', '15/01/2024', 'CONSOLIDADO #2', '20123456789', 'MARIA ELENA GONZALEZ LOPEZ'],
            ];
        }

        // Aplicar encabezados
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Estilo para encabezados
        $sheet->getStyle('A1:' . array_key_last($headers))->getFont()->setBold(true);
        $sheet->getStyle('A1:' . array_key_last($headers))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:' . array_key_last($headers))->getFill()->getStartColor()->setRGB('CCCCCC');

        // Aplicar ejemplos
        $row = 3;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // Autoajustar columnas
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Guardar plantilla
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }
}
