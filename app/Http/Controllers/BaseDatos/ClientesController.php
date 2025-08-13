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
use App\Services\BaseDatos\Clientes\ClienteService;
use App\Services\BaseDatos\Clientes\ClienteExportService;
use App\Services\BaseDatos\Clientes\ClienteImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientesController extends Controller
{
    protected $clienteService;
    protected $clienteExportService;
    protected $clienteImportService;

    public function __construct(
        ClienteService $clienteService,
        ClienteExportService $clienteExportService,
        ClienteImportService $clienteImportService
    ) {
        $this->clienteService = $clienteService;
        $this->clienteExportService = $clienteExportService;
        $this->clienteImportService = $clienteImportService;
    }

    /**
     * Ruta base para archivos Excel importados (URL pública)
     */

    /**
     * Ruta física para archivos Excel importados (sistema de archivos)
     */
    /**
     * Obtener lista de clientes con paginación
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            // Usar el servicio para obtener datos
            $result = $this->clienteService->obtenerClientes($request, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
                'headers' => $result['headers']
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
            $result = $this->clienteService->obtenerClientePorId($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $result['status'] ?? 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
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

            $result = $this->clienteService->buscarClientes($request->termino);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
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
            $result = $this->clienteService->obtenerEstadisticas();

            return response()->json([
                'success' => true,
                'data' => $result['data'],
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

            $result = $this->clienteService->obtenerClientesPorServicio($request->servicio);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
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
                'excel_file' => 'required|file|mimes:xlsx,xls,xlsm|max:102400', // 100MB max (en KB)
            ]);
            Log::info('Importando clientes desde Excel');
            $result = $this->clienteImportService->importarClientes($request);

            return response()->json([
                'success' => true,
                'message' => 'Importación completada exitosamente',
                'data' => $result
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
            $result = $this->clienteImportService->obtenerListaImportaciones();
            return response()->json([
                'success' => true,
                'data' => $result
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
                'ruta_archivo' => $fileStoragePath,
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
        
        return $baseUrl . '/' . $storagePath . '/' . $ruta;
    }
    public function deleteExcel($id)
    {
        try {
            $result = $this->clienteImportService->eliminarImportacion($id);
            
            return response()->json([
                'success' => true, 
                'message' => 'Excel eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Error al eliminar el Excel: ' . $e->getMessage()
            ], 500);
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
            Cliente::create([
                'nombre' => $data['cliente'],
                'documento' => $data['dni'],
                'telefono' => $data['whatsapp'],
                'correo' => $data['correo'],
                'fecha' => $this->parsearFecha($data['fecha']),
                'empresa' => $data['razon_social'],
                'ruc' => $data['ruc'],
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
                    'nombre' => $data['cliente'],
                    'documento' => $data['dni'],
                    'telefono' => $data['whatsapp'],
                    'correo' => $data['correo'],
                    'fecha' => $this->parsearFecha($data['fecha']),
                    'estado' => 'PENDIENTE',
                    'estado_cotizador' => 'CONFIRMADO',
                    'estado_cliente' => 'NO RESERVADO',
                    'monto' => 0, // No hay monto en el Excel original
                    'logistica_final' => 0,
                    'impuestos_final' => 0,
                    'observaciones' => "Servicio: {$data['servicio']}",
                    'id_cliente_importacion' => $importId,
                    'id_tipo_cliente' => 1, // Tipo cliente por defecto
                ]);
                $cliente = Cliente::create([
                    'nombre' => $data['cliente'],
                    'documento' => $data['dni'],
                    'telefono' => $data['whatsapp'],
                    'correo' => $data['correo'],
                    'fecha' => $this->parsearFecha($data['fecha']),
                    'empresa' => $data['razon_social'],
                    'ruc' => $data['ruc'],
                    'id_cliente_importacion' => $importId,  

                ]);
                $cotizacion->id_cliente = $cliente->id;
                $cotizacion->save();
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
        // Extraer número de carga del texto (ejemplo: "CONSOLIDADO 1" -> "1")
        $carga = preg_replace('/.*?(\d+)/', '$1', $nombreContenedor);
        $contenedor = Contenedor::where('carga', $carga)
            ->first();

        if (!$contenedor) {
            $contenedor = Contenedor::create([
                'carga' => $carga,
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

            $result = $this->clienteImportService->descargarPlantilla($request);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla creada exitosamente',
                'download_url' => $result['download_url']
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

    /**
     * Obtener servicios para múltiples clientes en una sola consulta
     */
    private function obtenerServiciosEnLote($clienteIds)
    {
        if (empty($clienteIds)) {
            return [];
        }

        $serviciosPorCliente = [];

        // Inicializar array para todos los clientes
        foreach ($clienteIds as $clienteId) {
            $serviciosPorCliente[$clienteId] = [];
        }

        // Buscar en pedido_curso
        $pedidosCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->leftJoin('campana_curso as cc', 'pc.ID_Campana', '=', 'cc.ID_Campana')
            ->where('pc.Nu_Estado', 2) // Estado confirmado
            ->whereIn('pc.id_cliente', $clienteIds)
            ->orderBy('e.Fe_Registro', 'asc')
            ->get();

        //convert to nombre año mes  febrero 2025


        foreach ($pedidosCurso as $pedido) {

            // Parsear mes en inglés a español y obtener año
            $fechaCarbon = Carbon::parse($pedido->Fe_Inicio)->locale('es');
            $mesEspanol = $fechaCarbon->translatedFormat('F');
            $anio = $fechaCarbon->year;
            $nombreCampana = $mesEspanol . ' ' . $anio;
            $serviciosPorCliente[$pedido->id_cliente][] = [
                'servicio' => 'Curso ' . $nombreCampana,
                'monto' => $pedido->Ss_Total,
                'nombre' => $nombreCampana,
                'fecha' => $pedido->Fe_Registro
            ];
        }

        // Buscar en contenedor_consolidado_cotizacion
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->leftJoin('carga_consolidada_contenedor as cc', 'contenedor_consolidado_cotizacion.id_contenedor', '=', 'cc.id')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereIn('id_cliente', $clienteIds)
            ->orderBy('fecha', 'asc')
            ->get();

        foreach ($cotizaciones as $cotizacion) {
            $serviciosPorCliente[$cotizacion->id_cliente][] = [
                'servicio' => 'Consolidado #' . $cotizacion->carga,
                'monto' => $cotizacion->monto,
                'fecha' => $cotizacion->fecha
            ];
        }

        // Ordenar servicios por fecha para cada cliente
        foreach ($serviciosPorCliente as $clienteId => &$servicios) {
            if (!empty($servicios)) {
                // Ordenar por fecha
                usort($servicios, function ($a, $b) {
                    return strtotime($a['fecha']) - strtotime($b['fecha']);
                });

                // NO agregar categoría individual a servicios - se maneja a nivel de cliente
            }
        }

        return $serviciosPorCliente;
    }

    /**
     * Determinar categoría del cliente basada en sus servicios
     */
    private function determinarCategoriaCliente($servicios)
    {
        $totalServicios = count($servicios);

        // Sin servicios = Inactivo
        if ($totalServicios === 0) {
            return 'Inactivo';
        }

        // Obtener la fecha del último servicio
        $ultimoServicio = end($servicios);
        $fechaUltimoServicio = \Carbon\Carbon::parse($ultimoServicio['fecha']);
        $hoy = \Carbon\Carbon::now();

        // Solo corregir si la fecha es realmente futura (más de 1 año adelante)
        if ($fechaUltimoServicio->diffInMonths($hoy, false) < -12) {
            $fechaUltimoServicio->year = $hoy->year;
        }

        // Si aún es muy futura (más de 1 mes), usar fecha actual
        if ($fechaUltimoServicio->diffInMonths($hoy, false) < -1) {
            $fechaUltimoServicio = $hoy;
        }

        // Calcular meses desde la última compra (valor absoluto)
        $mesesDesdeUltimaCompra = abs($hoy->diffInMonths($fechaUltimoServicio, false));

        // Inactivo: Hace 6 meses o más no realiza pedidos
        if ($mesesDesdeUltimaCompra >= 6) {
            return 'Inactivo';
        }

        // Cliente: Participó de algún servicio (1 servicio)
        if ($totalServicios === 1) {
            return 'Cliente';
        }

        // Para clientes con múltiples servicios (2 o más)
        if ($totalServicios >= 2) {
            // Calcular frecuencia promedio de compras para determinar si es Premium
            $primerServicio = reset($servicios);
            $fechaPrimerServicio = \Carbon\Carbon::parse($primerServicio['fecha']);

            // Solo corregir si la fecha es realmente futura (más de 1 año adelante)
            if ($fechaPrimerServicio->diffInMonths($hoy, false) < -12) {
                $fechaPrimerServicio->year = $hoy->year;
            }

            // Si aún es muy futura (más de 1 mes), usar fecha actual
            if ($fechaPrimerServicio->diffInMonths($hoy, false) < -1) {
                $fechaPrimerServicio = $hoy;
            }

            $mesesEntrePrimeraYUltima = abs($fechaPrimerServicio->diffInMonths($fechaUltimoServicio, false));

            // Premium: Verificar que TODAS las compras tengan lapso ≤ 2 meses Y última compra ≤ 2 meses
            if ($mesesDesdeUltimaCompra <= 2) {
                $esPremium = true;

                // Recorrer todas las compras para verificar lapsos entre ellas
                for ($i = 1; $i < count($servicios); $i++) {
                    $fechaAnterior = \Carbon\Carbon::parse($servicios[$i - 1]['fecha']);
                    $fechaActual = \Carbon\Carbon::parse($servicios[$i]['fecha']);

                    // Corregir años futuros si es necesario
                    if ($fechaAnterior->diffInMonths($hoy, false) < -12) {
                        $fechaAnterior->year = $hoy->year;
                    }
                    if ($fechaActual->diffInMonths($hoy, false) < -12) {
                        $fechaActual->year = $hoy->year;
                    }

                    $lapsoEntreFechas = abs($fechaAnterior->diffInMonths($fechaActual, false));

                    // Si algún lapso es mayor a 2 meses, no es Premium
                    if ($lapsoEntreFechas > 2) {
                        $esPremium = false;
                        break;
                    }
                }

                if ($esPremium) {
                    return 'Premium';
                }
            }

            // Recurrente: Compró 2+ veces Y última compra ≤ 6 meses (ya validamos que < 6 meses arriba)
            return 'Recurrente';
        }

        return 'Inactivo';
    }

    /**
     * Determinar categoría del cliente de manera optimizada
     */
    private function determinarCategoriaOptimizada($cliente, $fechaServicio, $totalServicios)
    {
        if ($totalServicios === 0) {
            return 'Inactivo';
        }

        if ($totalServicios === 1) {
            return 'Cliente';
        }

        // Para esta función optimizada, usar lógica simplificada
        // Si necesitas mayor precisión, podrías hacer una consulta adicional
        $fechaServicio = \Carbon\Carbon::parse($fechaServicio);
        $hoy = \Carbon\Carbon::now();
        $mesesDesdeServicio = $fechaServicio->diffInMonths($hoy);

        // Lógica simplificada para la categorización
        if ($mesesDesdeServicio <= 2 && $totalServicios >= 2) {
            return 'Premium';
        } else if ($mesesDesdeServicio <= 6 && $totalServicios >= 2) {
            return 'Recurrente';
        }

        return 'Inactivo';
    }
    /**
     * Exportar clientes a Excel con filtros opcionales
     */
    public function export(Request $request)
    {
        try {
            return $this->clienteExportService->exportarClientes($request);
        } catch (\Exception $e) {
            Log::error('Error al exportar clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la letra de columna de Excel a partir de un índice numérico.
     *
     * @param int $column Índice de columna (empezando en 1)
     * @param int $i Incremento del índice
     * @return string Letra de la columna
     */
    public function getColumnLetter($column = 'A', $i = 1)
    {
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) + $i;
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
    }

    /**
     * Obtiene los datos filtrados para la exportación
     */
    private function obtenerDatosParaExportar(Request $request)
    {
        $query = Cliente::query();

        // Aplicar filtros
        if ($request->has('search') && !empty($request->search)) {
            $query->buscar($request->search);
        }

        if ($request->has('servicio') && !empty($request->servicio) && $request->servicio != 'todos') {
            $query->porServicio($request->servicio);
        }

        if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
            $fechaInicio = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
            $query->where('fecha', '>=', $fechaInicio);
        }

        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $fechaFin = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_fin)->endOfDay();
            $query->where('fecha', '<=', $fechaFin);
        }

        $filtroCategoria = $request->has('categoria') && !empty($request->categoria) && $request->categoria != 'todos'
            ? $request->categoria : null;

        $query->orderBy('created_at', 'desc');

        // Obtener datos según filtro de categoría
        if ($filtroCategoria) {
            $todosLosClientes = $query->get();
            $todosLosIds = $todosLosClientes->pluck('id')->toArray();
            $serviciosPorCliente = $this->obtenerServiciosEnLote($todosLosIds);

            $clientesFiltrados = [];
            foreach ($todosLosClientes as $cliente) {
                $servicios = $serviciosPorCliente[$cliente->id] ?? [];
                $categoria = $this->determinarCategoriaCliente($servicios);
                Log::info("categoria: $categoria");
                if ($categoria === $filtroCategoria) {
                    $clientesFiltrados[] = [
                        'cliente' => $cliente,
                        'servicios' => $servicios,
                        'categoria' => $categoria
                    ];
                }
            }
            return $clientesFiltrados;
        } else {
            $clientes = $query->get();
            $clienteIds = $clientes->pluck('id')->toArray();
            $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

            $datosExport = [];
            foreach ($clientes as $cliente) {
                $servicios = $serviciosPorCliente[$cliente->id] ?? [];
                $categoria = $this->determinarCategoriaCliente($servicios);
                Log::info("categoria: $categoria");
                $datosExport[] = [
                    'cliente' => $cliente,
                    'servicios' => $servicios,
                    'categoria' => $categoria
                ];
            }
            return $datosExport;
        }
    }

    /**
     * Configura los encabezados principales del Excel
     */
    private function configurarEncabezadosPrincipales($sheet)
    {
        $headers = [
            'B2' => 'INFORMACION PRINCIPAL',
            'B3' => 'N',
            'C3' => 'NOMBRE',
            'D3' => 'DNI',
            'E3' => 'CORREO',
            'F3' => 'WHATSAPP',
            'G3' => 'FECHA REGISTRO',
            'H3' => 'SERVICIO',
            'I3' => 'CATEGORIA',
            'J2' => 'HISTORIAL DE COMPRA',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
    }

    /**
     * Llena los datos en el Excel y retorna información de dimensiones
     */
    private function llenarDatosExcel($sheet, $datosExport)
    {
        $row = 4;
        $maxServiceCount = 0;

        foreach ($datosExport as $item) {
            $cliente = $item['cliente'];
            $servicios = $item['servicios'];
            $categoria = $item['categoria'];
            $primerServicio = $servicios[0];
            // Llenar información principal del cliente
            $sheet->setCellValue('B' . $row, $cliente->id);
            $sheet->setCellValue('C' . $row, $cliente->nombre);
            $sheet->setCellValue('D' . $row, $cliente->documento);
            $sheet->setCellValue('E' . $row, $cliente->correo);
            $sheet->setCellValue('F' . $row, $cliente->telefono);
            $sheet->setCellValue('G' . $row, $cliente->fecha ? $cliente->fecha->format('d/m/Y') : '');
            $sheet->setCellValue('H' . $row, $primerServicio['servicio']);
            $sheet->setCellValue('I' . $row, $categoria);

            // Llenar servicios
            $currentColumn = 'J';
            if (count($servicios) > $maxServiceCount) {
                $maxServiceCount = count($servicios);
            }

            foreach ($servicios as $servicio) {
                $sheet->setCellValue($currentColumn . $row, $servicio['servicio']);
                $sheet->setCellValue($this->getColumnLetter($currentColumn, 1) . $row, $servicio['monto'] ?? 0);
                $sheet->setCellValue($this->getColumnLetter($currentColumn, 2) . $row, $servicio['fecha']);
                $currentColumn = $this->getColumnLetter($currentColumn, 3);
            }

            $row++;
        }

        return [
            'maxServiceCount' => $maxServiceCount,
            'lastRow' => $row - 1,
            'maxColumn' => $this->getColumnLetter('J', ($maxServiceCount * 3) - 1)
        ];
    }

    /**
     * Configura los encabezados de servicios dinámicamente
     */
    private function configurarEncabezadosServicios($sheet, $maxServiceCount)
    {
        $startColumn = 'J';
        for ($i = 0; $i < $maxServiceCount; $i++) {
            $sheet->setCellValue($startColumn . 3, 'SERVICIO ' . ($i + 1));
            $startColumn = $this->getColumnLetter($startColumn, 1);
            $sheet->setCellValue($startColumn . 3, 'MONTO ' . ($i + 1));
            $startColumn = $this->getColumnLetter($startColumn, 1);
            $sheet->setCellValue($startColumn . 3, 'FECHA ' . ($i + 1));
            $startColumn = $this->getColumnLetter($startColumn, 1);
        }
    }

    /**
     * Aplica formato y estilos al Excel
     */
    private function aplicarFormatoExcel($sheet, $infoDimensiones)
    {
        $maxColumn = $infoDimensiones['maxColumn'];
        $lastRow = $infoDimensiones['lastRow'];

        // Unir celdas de encabezados
        $sheet->mergeCells('B2:I2');
        $sheet->mergeCells('J2:' . $maxColumn . '2');

        // Configurar ancho de columnas específico para mejor legibilidad
        $columnWidths = [
            'B' => 8,   // N (ID)
            'C' => 25,  // NOMBRE
            'D' => 15,  // DNI
            'E' => 30,  // CORREO
            'F' => 20,  // WHATSAPP
            'G' => 15,  // FECHA REGISTRO
            'H' => 15,  // SERVICIO
            'I' => 15,  // CATEGORIA
        ];

        // Aplicar anchos específicos a las columnas principales
        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Para las columnas de servicios, usar autoSize pero con un mínimo
        $startServiceColumn = 'J';
        for ($i = 0; $i < $infoDimensiones['maxServiceCount']; $i++) {
            $serviceCol = $this->getColumnLetter($startServiceColumn, $i * 3);
            $montoCol = $this->getColumnLetter($startServiceColumn, $i * 3 + 1);
            $fechaCol = $this->getColumnLetter($startServiceColumn, $i * 3 + 2);

            $sheet->getColumnDimension($serviceCol)->setWidth(20);  // SERVICIO
            $sheet->getColumnDimension($montoCol)->setWidth(15);    // MONTO
            $sheet->getColumnDimension($fechaCol)->setWidth(15);    // FECHA
        }

        // Configurar formato de texto para la columna F (WhatsApp)
        $sheet->getStyle('F4:F' . $lastRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Configurar formato de texto para columnas de servicios (para evitar que se interpreten como números)
        $serviceRange = 'J4:' . $maxColumn . $lastRow;
        $sheet->getStyle($serviceRange)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Aplicar bordes a toda la tabla
        $range = 'B3:' . $maxColumn . $lastRow;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Aplicar bordes a los encabezados principales
        $sheet->getStyle('B2:' . $maxColumn . '3')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Centrar encabezados
        $sheet->getStyle('B2:' . $maxColumn . '3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B2:' . $maxColumn . '3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Aplicar color de fondo a encabezados
        $sheet->getStyle('B2:' . $maxColumn . '3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E6E6E6');

        // Configurar wrap text para columnas con texto largo
        $sheet->getStyle('C4:C' . $lastRow)->getAlignment()->setWrapText(true); // NOMBRE
        $sheet->getStyle('E4:E' . $lastRow)->getAlignment()->setWrapText(true); // CORREO
        $sheet->getStyle('F4:F' . $lastRow)->getAlignment()->setWrapText(true); // WHATSAPP
    }

    /**
     * Genera y retorna la descarga del archivo Excel
     */
    private function generarDescargaExcel($spreadsheet)
    {
        $filename = 'Reporte_Clientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
    /**
     * Aplicar filtros a la query base
     */
    private function aplicarFiltros(Request $request)
    {
        $query = Cliente::query();

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
            $fechaInicio = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
            $query->where('fecha', '>=', $fechaInicio);
        }

        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $fechaFin = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_fin)->endOfDay();
            $query->where('fecha', '<=', $fechaFin);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Obtener clientes con filtro de categoría (paginación manual)
     */
    private function obtenerClientesConFiltroCategoria($query, $filtroCategoria, $page, $perPage)
    {
        // Obtener todos los clientes que coinciden con otros filtros
        $todosLosClientes = $query->get();

        // Obtener servicios para todos los clientes
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

        // Paginar manualmente
        $total = count($clientesFiltrados);
        $offset = ($page - 1) * $perPage;
        $clientesPagina = array_slice($clientesFiltrados, $offset, $perPage);

        // Crear objeto de paginación manual
        $paginationData = [
            'current_page' => (int) $page,
            'last_page' => (int) ($total > 0 ? ceil($total / $perPage) : 1),
            'per_page' => (int) $perPage,
            'total' => (int) $total,
            'from' => count($clientesPagina) > 0 ? $offset + 1 : null,
            'to' => count($clientesPagina) > 0 ? min($offset + count($clientesPagina), $total) : null,
        ];

        // Obtener servicios solo para la página actual
        $clientesIds = collect($clientesPagina)->pluck('id')->toArray();
        $serviciosPaginaActual = $this->obtenerServiciosEnLote($clientesIds);

        // Transformar datos
        $clientesData = $this->transformarDatosClientes($clientesPagina, $serviciosPaginaActual);

        return [$clientesData, $paginationData];
    }

    /**
     * Obtener clientes sin filtro de categoría (paginación normal)
     */
    private function obtenerClientesSinFiltroCategoria($query, $page, $perPage)
    {
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        // Obtener IDs de clientes para consultas en lote
        $clienteIds = collect($data->items())->pluck('id')->toArray();

        // Obtener todos los servicios de una vez para los clientes de esta página
        $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

        // Transformar los datos de clientes
        $clientesData = $this->transformarDatosClientes($data->items(), $serviciosPorCliente);

        $paginationData = [
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
        ];

        return [$clientesData, $paginationData];
    }

    /**
     * Transformar datos de clientes para la respuesta
     */
    private function transformarDatosClientes($clientes, $serviciosPorCliente)
    {
        $clientesData = [];
        foreach ($clientes as $cliente) {
            $servicios = $serviciosPorCliente[$cliente->id] ?? [];
            $primerServicio = !empty($servicios) ? $servicios[0] : null;

            // Calcular categoría del cliente basada en sus servicios
            $categoria = $this->determinarCategoriaCliente($servicios);

            $clientesData[] = [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                'categoria' => $categoria,
                'primer_servicio' => $primerServicio ? [
                    'servicio' => $primerServicio['servicio'],
                    'fecha' => \Carbon\Carbon::parse($primerServicio['fecha'])->format('d/m/Y'),
                    'categoria' => $categoria
                ] : null,
                'total_servicios' => count($servicios),
                'servicios' => collect($servicios)->map(function ($servicio) use ($categoria) {
                    return [
                        'servicio' => $servicio['servicio'],
                        'fecha' => \Carbon\Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                        'categoria' => $categoria
                    ];
                })
            ];
        }

        return $clientesData;
    }

    /**
     * Obtener estadísticas para el header considerando TODOS los filtros
     */
    private function obtenerEstadisticasHeader($request)
    {
        // Aplicar TODOS los filtros incluyendo categoría
        $queryBase = $this->aplicarFiltros($request);

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

    /**
     * Aplicar filtros sin incluir categoría
     */
    private function aplicarFiltrosSinCategoria(Request $request)
    {
        $query = Cliente::query();

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
            $fechaInicio = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
            $query->where('fecha', '>=', $fechaInicio);
        }

        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $fechaFin = \Carbon\Carbon::createFromFormat('d/m/Y', $request->fecha_fin)->endOfDay();
            $query->where('fecha', '<=', $fechaFin);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
