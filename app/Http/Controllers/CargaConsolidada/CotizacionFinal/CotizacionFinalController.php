<?php

namespace App\Http\Controllers\CargaConsolidada\CotizacionFinal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\Usuario;
use App\Models\Notificacion;
use Illuminate\Support\Facades\DB;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Dompdf\Dompdf;
use Dompdf\Options;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\FileTrait;
class CotizacionFinalController extends Controller
{
    use WhatsappTrait, FileTrait;
    private $table_cliente = 'entidad';
    private $table_usuario = 'usuario';
    private $table = "carga_consolidada_contenedor";
    private $STATUS_CONTACTED = "C";
    private $STATUS_RECIVED = "R";
    private $STATUS_NOT_SELECTED = "NS";
    private $STATUS_INSPECTION = "INSPECTION";
    private $STATUS_LOADED = "LOADED";
    private $STATUS_NO_LOADED = "NO LOADED";
    private $STATUS_ROTULADO = "ROTULADO";
    private $STATUS_DATOS_PROVEEDOR = "DATOS PROVEEDOR";
    private $STATUS_COBRANDO = "COBRANDO";
    private $STATUS_INSPECCIONADO = "INSPECCIONADO";
    private $STATUS_RESERVADO = "RESERVADO";
    private $STATUS_NO_RESERVADO = "NO RESERVADO";
    private $STATUS_EMBARCADO = "EMBARCADO";
    private $STATUS_NO_EMBARCADO = "NO EMBARCADO";
    private $table_pais = "pais";
    private $table_contenedor_steps = "contenedor_consolidado_order_steps";
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_crons = "contenedor_consolidado_cotizacion_crons";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor_documentacion_files = "contenedor_consolidado_documentacion_files";
    private $table_contenedor_documentacion_folders = "contenedor_consolidado_documentacion_folders";
    private $table_contenedor_tipo_cliente = "contenedor_consolidado_tipo_cliente";
    private $table_contenedor_cotizacion_documentacion = "contenedor_consolidado_cotizacion_documentacion";
    private $table_contenedor_almacen_documentacion = "contenedor_consolidado_almacen_documentacion";
    private $table_contenedor_almacen_inspection = "contenedor_consolidado_almacen_inspection";
    private $table_conteneodr_proveedor_estados_tracking = "contenedor_proveedor_estados_tracking";
    private $roleCotizador = "Cotizador";
    private $roleCoordinacion = "Coordinación";
    private $roleContenedorAlmacen = "ContenedorAlmacen";
    private $roleCatalogoChina = "CatalogoChina";
    private $rolesChina = ["CatalogoChina", "ContenedorAlmacen"];
    private $roleDocumentacion = "Documentacion";
    private $aNewContainer = "new-container";
    private $aNewConfirmado = "new-confirmado";
    private $aNewCotizacion = "new-cotizacion";
    private $cambioEstadoProveedor = "cambio-estado-proveedor";
    private $table_contenedor_cotizacion_final = "contenedor_consolidado_cotizacion_final";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $CONCEPT_PAGO_IMPUESTOS = 2;
    private $CONCEPT_PAGO_LOGISTICA = 1;
    /**
     * Obtiene las cotizaciones finales de un contenedor específico
     */
    public function getContenedorCotizacionesFinales(Request $request, $idContenedor)
    {
        try {
            // Construir la consulta usando Eloquent con campos formateados directamente
            $query = Cotizacion::with('tipoCliente')
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    'contenedor_consolidado_cotizacion.id as id_cotizacion',
                    'contenedor_consolidado_tipo_cliente.name',
                    DB::raw('UPPER(contenedor_consolidado_cotizacion.nombre) as nombre_upper'),
                    DB::raw('UPPER(LEFT(TRIM(contenedor_consolidado_tipo_cliente.name), 1)) || LOWER(SUBSTRING(TRIM(contenedor_consolidado_tipo_cliente.name), 2)) as tipo_cliente_formateado'),
                    DB::raw('contenedor_consolidado_cotizacion.volumen_final as volumen_final_formateado'),
                    DB::raw('contenedor_consolidado_cotizacion.fob_final as fob_final_formateado'),
                    DB::raw('contenedor_consolidado_cotizacion.logistica_final as logistica_final_formateado'),
                    DB::raw('contenedor_consolidado_cotizacion.impuestos_final as impuestos_final_formateado'),
                    DB::raw('contenedor_consolidado_cotizacion.tarifa_final as tarifa_final_formateado')
                ])
                ->join('contenedor_consolidado_tipo_cliente', 'contenedor_consolidado_cotizacion.id_tipo_cliente', '=', 'contenedor_consolidado_tipo_cliente.id')
                ->where('id_contenedor', $idContenedor)
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'contenedor_consolidado_cotizacion.id');
                })
                ->where('estado_cotizador', 'CONFIRMADO');

            // Aplicar filtros adicionales si se proporcionan
            if ($request->has('search')) {
                // Normalize and trim search input
                $search = trim((string)$request->search);
                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('contenedor_consolidado_cotizacion.nombre', 'LIKE', "%{$search}%")
                          ->orWhere('contenedor_consolidado_cotizacion.documento', 'LIKE', "%{$search}%")
                          ->orWhere('contenedor_consolidado_cotizacion.correo', 'LIKE', "%{$search}%")
                          ->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
                    });
                }
            }

            if ($request->has('estado_cotizacion_final') && !empty($request->estado_cotizacion_final)) {
                $query->where('estado_cotizacion_final', $request->estado_cotizacion_final);
            }

            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            $transformedData = [];
            $index = 1;

            foreach ($data->items() as $row) {
                $pagos = DB::table($this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . ' as P')
                    ->leftJoin($this->table_pagos_concept . ' as C', 'P.id_concept', '=', 'C.id')
                    ->where('P.id_cotizacion', $row->id_cotizacion)
                    ->select('P.id', 'P.monto', 'P.voucher_url', 'P.payment_date', 'P.banco', 'P.created_at', 'P.status', 'C.name as concept_name')
                    ->orderBy('P.id', 'asc')
                    ->get();

                $pagos->transform(function ($p) {
                    if (isset($p->voucher_url) && $p->voucher_url) {
                        $p->voucher_url = $this->generateImageUrl($p->voucher_url);
                    }
                    return $p;
                });

                // Determinar si los pagos asociados están todos confirmados
                $pagosNotConfirmed = $pagos->filter(function($p){
                    $status = isset($p->status) ? strtoupper(trim($p->status)) : '';
                    return $status !== 'CONFIRMADO';
                })->count();

                $pagado_verificado = false;
                // Considerar pagado verificado solo cuando:
                //  - el estado en BD sea exactamente 'PAGADO' (no 'SOBREPAGO'), y
                //  - NO existen pagos sin confirmar para los conceptos LOGISTICA/IMPUESTOS
                // Esto garantiza que un registro en 'SOBREPAGO' nunca aparezca como "verificado"
                // hasta que el monto pagado se iguale al monto a pagar (es decir, pase a 'PAGADO').
                $estadoFinal = isset($row->estado_cotizacion_final) ? strtoupper(trim($row->estado_cotizacion_final)) : '';
                if ($estadoFinal === 'PAGADO' && $pagosNotConfirmed === 0) {
                    $pagado_verificado = true;
                }

                $subdata = [
                    'index' => $index,
                    'nombre' => $this->cleanUtf8String($row->nombre_upper ?? $row->nombre),
                    'documento' => $this->cleanUtf8String($row->documento),
                    'correo' => $this->cleanUtf8String($row->correo),
                    'telefono' => $this->cleanUtf8String($row->telefono),
                    'tipo_cliente' => $this->cleanUtf8String($row->name),
                    'volumen_final' => $row->volumen_final_formateado ?? $row->volumen_final,
                    'fob_final' => $row->fob_final_formateado ?? $row->fob_final,
                    'logistica_final' => $row->logistica_final_formateado ?? $row->logistica_final,
                    'impuestos_final' => $row->impuestos_final_formateado ?? $row->impuestos_final,
                    'tarifa_final' => $row->tarifa_final_formateado ?? $row->tarifa_final,
                    'estado_cotizacion_final' => $this->cleanUtf8String($row->estado_cotizacion_final),
                    'pagado_verificado' => $pagado_verificado,
                    'id_cotizacion' => $row->id_cotizacion,
                    'cotizacion_final_url' =>$this->generateImageUrl($row->cotizacion_final_url),
                    'pagos' => $pagos,
                    'cotizacion_contrato_firmado_url' => $row->cotizacion_contrato_firmado_url ? $this->generateImageUrl($row->cotizacion_contrato_firmado_url) : null,
                    'cod_contract' => $row->cod_contract,
                ];

                $transformedData[] = $subdata;
                $index++;
            }

            //ordenar de manera ascendente por id_cotizacion
            $transformedData = collect($transformedData)->sortBy('id_cotizacion')->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem()
                ],

            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener cotizaciones finales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones finales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las cotizaciones finales con documentación y pagos para un contenedor
     */
    public function getCotizacionFinalDocumentacionPagos(Request $request, $idContenedor)
    {
        try {
            // Construir la consulta usando Eloquent con campos formateados directamente
            $query = Cotizacion::with('tipoCliente')
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    'contenedor_consolidado_cotizacion.id as id_cotizacion',
                    'TC.name',
                   
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id_pago', cccp.id,
                                'concepto', ccp.name,
                                'status', cccp.status,
                                'payment_date', cccp.payment_date,
                                'banco', cccp.banco,
                                'monto', cccp.monto,
                                'voucher_url', cccp.voucher_url
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                        order by cccp.id asc
                    ) AS pagos"),
                    DB::raw("(
                        SELECT IFNULL(SUM(cccp.monto), 0) 
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS total_pagos"),
                    DB::raw("(
                        SELECT COUNT(*) 
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS pagos_count"),
                    DB::raw('(contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final) as total_logistica_impuestos')
                ])
                ->leftJoin('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'contenedor_consolidado_cotizacion.id_tipo_cliente')
                ->where('contenedor_consolidado_cotizacion.id_contenedor', $idContenedor)
                ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente')
                ->where('contenedor_consolidado_cotizacion.estado_cotizador', "CONFIRMADO");

            // Aplicar filtros adicionales si se proporcionan
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('contenedor_consolidado_cotizacion.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('contenedor_consolidado_cotizacion.documento', 'LIKE', "%{$search}%")
                        ->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', 10);
            $query->whereNull('id_cliente_importacion');
            // Ordenamiento
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            $query->orderBy($sortField, $sortOrder);
     
            $data = $query->paginate($perPage);

            // Transformar los datos para incluir las columnas específicas
            $transformedData = [];
            $index = 1;

            foreach ($data->items() as $row) {
                $pagos = json_decode($row->pagos??'[]', true);
                $pagos = array_map(function($pago) {
                    $pago['voucher_url'] = $this->generateImageUrl($pago['voucher_url']);
                    return $pago;
                }, $pagos);
                $subdata = [
                    'index' => $index,
                    'id_contenedor' => $row->id_contenedor,
                    'nombre' => $this->cleanUtf8String($row->nombre),
                    'documento' => $this->cleanUtf8String($row->documento),
                    'telefono' => $this->cleanUtf8String($row->telefono),
                    'tipo_cliente' => $this->cleanUtf8String($row->name),
                    'total_logistica_impuestos' => $row->total_logistica_impuestos,
                    'total_pagos' => $row->total_pagos == 0 ? "0.00" : $row->total_pagos,
                    'pagos_count' => $row->pagos_count,
                    'id_cotizacion' => $row->id_cotizacion,
                    'pagos' => json_encode($pagos)
                ];

                $transformedData[] = $subdata;
                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem()
                ],

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones con documentación y pagos: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Obtiene y procesa la boleta para envío
     */
    private function getBoletaForSend($idCotizacion)
    {
        try {
            // Obtener URL de cotización final
            $cotizacion = DB::table('contenedor_consolidado_cotizacion')
                ->select('cotizacion_final_url')
                ->where('id', $idCotizacion)
                ->first();

            if (!$cotizacion || !$cotizacion->cotizacion_final_url) {
                Log::error('Cotización final no encontrada o sin URL', ['id_cotizacion' => $idCotizacion]);
                return false;
            }

            // Procesar URL y obtener contenido
            $originalUrl = $cotizacion->cotizacion_final_url;
            
            // Intentar diferentes ubicaciones
            $possiblePaths = [];
            
            // Nueva ubicación: storage/app/public/CargaConsolidada/cotizacionfinal/{idContenedor}
            $possiblePaths[] = storage_path('app/public/' . $originalUrl);
            
            // Procesar ruta de la DB con generateImageUrl
            $generatedUrl = $this->generateImageUrl($originalUrl);
            if ($generatedUrl) {
                $possiblePaths[] = $generatedUrl;
            }
            
            // Verificar si es una URL completa
            $isValidUrl = filter_var($originalUrl, FILTER_VALIDATE_URL) || preg_match('/^https?:\/\//', $originalUrl);
            
            if ($isValidUrl) {
                $fileUrl = str_replace(' ', '%20', $originalUrl);
                $possiblePaths[] = $fileUrl; // Agregar URL completa
            } else {
                // Ubicaciones legacy
                $possiblePaths[] = public_path('assets/downloads/' . basename($originalUrl));
                $possiblePaths[] = public_path($originalUrl);
                $possiblePaths[] = storage_path($originalUrl);
                $possiblePaths[] = $originalUrl;
            }
            
            // Buscar el archivo en las ubicaciones posibles
            $fileContent = false;
            $foundPath = null;
            
            foreach ($possiblePaths as $path) {
                if (strpos($path, 'http') === 0) {
                    // Es una URL, usar downloadFileFromUrl
                    //fix malformed url
                    $path = str_replace(' ', '%20', $path);
                    $fileContent = $this->downloadFileFromUrl($path);
                } else if (file_exists($path)) {
                    // Es un archivo local
                    $fileContent = file_get_contents($path);
                    $foundPath = $path;
                }
                
                if ($fileContent !== false) {
                    break;
                }
            }

            
            if ($fileContent === false) {
                Log::error('No se pudo leer el archivo de cotización final');
                throw new \Exception("No se pudo leer el archivo Excel desde ninguna ubicación.");
            }

            // Crear archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'cotizacion_') . '.xlsx';
            if (file_put_contents($tempFile, $fileContent) === false) {
                throw new \Exception("No se pudo crear el archivo temporal.");
            }

            // Cargar Excel usando PhpSpreadsheet
            $spreadsheet = IOFactory::load($tempFile);

            // Generar boleta
            return $this->generateBoletaForSend($spreadsheet);
        } catch (\Exception $e) {
            Log::error('Error en getBoletaForSend: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } finally {
            // Limpiar archivo temporal si existe
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Descarga un archivo desde una URL externa usando cURL
     * 
     * @param string $url URL del archivo a descargar
     * @return string|false Contenido del archivo o false si falla
     */
    private function downloadFileFromUrl($url)
    {
        Log::info("=== INICIO downloadFileFromUrl ===", ['url' => $url]);
        
        try {
            // Verificar si cURL está disponible
            if (!function_exists('curl_init')) {
                Log::error("cURL no está disponible en el servidor");
                return false;
            }
            
            Log::info("Inicializando cURL...");
            
            // Inicializar cURL
            $ch = curl_init();
            
            if (!$ch) {
                Log::error("No se pudo inicializar cURL");
                return false;
            }
            
            Log::info("Configurando opciones de cURL...");
            
            // Configurar opciones de cURL
            $success = curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,*/*',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache'
                ],
                CURLOPT_VERBOSE => false,
                CURLOPT_HEADER => false
            ]);
            
            if (!$success) {
                Log::error("Error al configurar opciones de cURL");
                curl_close($ch);
                return false;
            }
            
            Log::info("Ejecutando petición cURL...");
            
            // Ejecutar la petición
            $fileContent = curl_exec($ch);
            
            // Obtener información de la petición
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
            
            Log::info("Información de cURL", [
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'total_time' => $totalTime,
                'download_size' => $downloadSize,
                'curl_error' => $error
            ]);
            
            curl_close($ch);
            
            // Verificar si hubo error en la ejecución
            if ($fileContent === false) {
                Log::error("curl_exec retornó false", ['curl_error' => $error]);
                return false;
            }
            
            // Verificar errores de cURL
            if (!empty($error)) {
                Log::error("Error cURL al descargar archivo: " . $error, ['url' => $url]);
                return false;
            }
            
            // Verificar código HTTP
            if ($httpCode !== 200) {
                Log::error("Error HTTP al descargar archivo. Código: " . $httpCode, [
                    'url' => $url,
                    'content_type' => $contentType
                ]);
                return false;
            }
            
            // Verificar que el contenido no esté vacío
            if (empty($fileContent)) {
                Log::error("Archivo descargado está vacío", ['url' => $url]);
                return false;
            }
            
            $fileSize = strlen($fileContent);
            Log::info("Archivo descargado exitosamente", [
                'url' => $url,
                'size' => $fileSize,
                'content_type' => $contentType,
                'first_bytes' => bin2hex(substr($fileContent, 0, 16)) // Primeros 16 bytes en hex
            ]);
            
            // Verificar que sea un archivo Excel válido mirando los primeros bytes
            $signature = substr($fileContent, 0, 4);
            if ($signature !== "PK\x03\x04") {
                Log::warning("El archivo descargado no parece ser un archivo ZIP/Excel válido", [
                    'signature' => bin2hex($signature),
                    'expected' => bin2hex("PK\x03\x04")
                ]);
                // No retornar false, intentar procesar de todos modos
            }
            
            Log::info("=== FIN downloadFileFromUrl EXITOSO ===");
            return $fileContent;
            
        } catch (\Exception $e) {
            Log::error("Excepción al descargar archivo desde URL: " . $e->getMessage(), [
                'url' => $url,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Descarga la plantilla general con datos procesados
     */
    public function downloadPlantillaGeneral($idContenedor)
    {
        try {
            // Obtener URL de factura general
            $contenedor = DB::table($this->table)
                ->select('factura_general_url')
                ->where('id', $idContenedor)
                ->first();

            if (!$contenedor || !$contenedor->factura_general_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la factura general'
                ], 404);
            }

            // Obtener datos del sistema
            $dataSystem = DB::table($this->table_contenedor_cotizacion . ' as CC')
                ->join($this->table_contenedor_tipo_cliente . ' as TC', 'CC.id_tipo_cliente', '=', 'TC.id')
                ->select(
                    'CC.nombre',
                    'CC.volumen',
                    'CC.documento',
                    'CC.volumen_doc',
                    'CC.valor_doc',
                    'CC.valor_cot',
                    'CC.volumen_china',
                    'TC.name',
                    'CC.vol_selected',
                    'CC.peso',
                    'CC.telefono'
                )
                ->where('CC.id_contenedor', $idContenedor)
                ->where('CC.estado_cotizador', 'CONFIRMADO')
                ->whereNull('CC.id_cliente_importacion')
                ->whereNotNull('CC.estado_cliente')
                ->get();
            Log::info('Data system: ' . json_encode($dataSystem));
            // Obtener y validar ruta del archivo
            $facturaGeneralUrl = $this->getLocalPath($contenedor->factura_general_url);
            $plantillaPath = public_path('assets/templates/PLANTILLA_GENERAL.xlsx');

            if (!file_exists($plantillaPath)) {
                throw new \Exception('No se encontró la plantilla general');
            }

            // Cargar archivos Excel
            $objPHPExcel = IOFactory::load($facturaGeneralUrl);
            $sheet = $objPHPExcel->getActiveSheet();

            $newExcel = IOFactory::load($plantillaPath);
            $newSheet = $newExcel->getActiveSheet();

            // Configurar encabezados
            $headers = [
                'A' => 'CLIENTE',
                'B' => 'TIPO',
                'C' => 'DNI',
                'D' => 'TELEFONO',
                'E' => 'ITEM',
                'F' => 'PRODUCTO',
                'N' => 'CANTIDAD',
                'O' => 'PRECIO UNITARIO',
                'P' => 'ANTIDUMPING',
                'Q' => 'VALORACION',
                'R' => 'AD VALOREM',
                'S' => 'PERCEPCION',
                'T' => 'PESO',
                'U' => 'VOLUMEN SISTEMA'
            ];

            foreach ($headers as $column => $value) {
                $newSheet->setCellValue($column . '1', $value);
            }

            // Procesar datos
            $startRow = 26;
            $highestRow = $sheet->getHighestRow();
            $newRow = 2;
            $continue = true;

            while ($continue && $startRow <= $highestRow) {
                $itemNo = $sheet->getCell('B' . $startRow)->getValue();

                // Verificar si llegamos al final
                if (trim($itemNo) == "TOTAL FOB PRICE") {
                    break;
                }

                // Obtener rangos combinados para el cliente actual
                $mergeRanges = $sheet->getMergeCells();
                $clientMergeRange = null;

                foreach ($mergeRanges as $range) {
                    if (strpos($range, 'D') !== false) {
                        list($startCell, $endCell) = explode(':', $range);
                        $rangeStart = (int)preg_replace('/[^0-9]/', '', $startCell);
                        $rangeEnd = (int)preg_replace('/[^0-9]/', '', $endCell);

                        if ($startRow >= $rangeStart && $startRow <= $rangeEnd) {
                            $clientMergeRange = [
                                'start' => $rangeStart,
                                'end' => $rangeEnd
                            ];
                            break;
                        }
                    }
                }

                // Obtener información del cliente - manejar tanto celdas mergeadas como individuales
                $clientName = '';
                $clientType = '';
                $currentRow = $startRow;

                if ($clientMergeRange) {
                    // Si hay merge, obtener datos del inicio del rango
                $clientName = $sheet->getCell('D' . $clientMergeRange['start'])->getValue();
                $clientType = $sheet->getCell('C' . $clientMergeRange['start'])->getValue();
                $mergeStartRow = $newRow;

                    // Procesar cada fila del rango mergeado
                for ($currentRow = $clientMergeRange['start']; $currentRow <= $clientMergeRange['end']; $currentRow++) {
                        $this->processSingleRow($sheet, $newSheet, $newRow, $currentRow, $clientName, $clientType, $dataSystem);
                        $newRow++;
                    }
                } else {
                    // Si no hay merge, obtener datos de la fila actual
                    $clientName = $sheet->getCell('D' . $startRow)->getValue();
                    $clientType = $sheet->getCell('C' . $startRow)->getValue();
                    $mergeStartRow = $newRow;
                    
                    // Procesar solo la fila actual
                    $this->processSingleRow($sheet, $newSheet, $newRow, $startRow, $clientName, $clientType, $dataSystem);
                    $newRow++;
                }

                // Combinar celdas para el cliente solo si hay múltiples filas
                if ($clientMergeRange && $mergeStartRow < ($newRow - 1)) {
                    $this->applyClientMerges($newSheet, $mergeStartRow, $newRow - 1);
                }

                // Avanzar a la siguiente fila o grupo
                if ($clientMergeRange) {
                $startRow = $clientMergeRange['end'] + 1;
                } else {
                    $startRow++;
                }
            }

            // Aplicar estilos finales
            $this->applyFinalStyles($newSheet, $newRow);

            // Generar archivo temporal para descarga
            $writer = new Xlsx($newExcel);
            $tempFile = storage_path('app/temp/plantilla_general_' . $idContenedor . '.xlsx');

            if (!file_exists(dirname($tempFile))) {
                mkdir(dirname($tempFile), 0755, true);
            }

            $writer->save($tempFile);

            return response()->download($tempFile, 'plantilla_general_' . $idContenedor . '.xlsx')
                ->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Error en downloadPlantillaGeneral: ' . $e->getMessage(), [
                'id_contenedor' => $idContenedor,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla general: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aplica estilos a una fila específica
     */
    private function applyRowStyles($sheet, $row)
    {
        // Estilo base para la fila
        $rowStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ];

        // Aplicar estilo base a toda la fila
        $sheet->getStyle('A' . $row . ':U' . $row)->applyFromArray($rowStyle);

        // Formato de moneda
        $currencyStyle = [
            'numberFormat' => [
                'formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE
            ]
        ];

        $currencyColumns = ['O', 'P', 'Q', 'R'];
        foreach ($currencyColumns as $column) {
            $sheet->getStyle($column . $row)->applyFromArray($currencyStyle);
        }

        // Formato de porcentaje
        $percentageStyle = [
            'numberFormat' => [
                'formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00
            ]
        ];
        $sheet->getStyle('S' . $row)->applyFromArray($percentageStyle);

        // Ajustar texto y ajuste automático
        $sheet->getStyle('A' . $row . ':U' . $row)
            ->getAlignment()
            ->setWrapText(true)
            ->setShrinkToFit(true);
    }

    /**
     * Aplica combinaciones de celdas para un cliente
     */
    private function applyClientMerges($sheet, $startRow, $endRow)
    {
        $columnsToMerge = ['A', 'B', 'C', 'D', 'T', 'U'];

        foreach ($columnsToMerge as $column) {
            $sheet->mergeCells($column . $startRow . ':' . $column . $endRow);
            $sheet->getStyle($column . $startRow)
                ->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Aplica estilos finales a la hoja
     */
    private function applyFinalStyles($sheet, $lastRow)
    {
        // Estilos de encabezado
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9D9D9']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
                'shrinkToFit' => true
            ]
        ];

        // Aplicar estilos al encabezado
        $sheet->getStyle('A1:U1')->applyFromArray($headerStyle);
        $sheet->mergeCells('F1:M1');

        // Establecer ancho de columnas
        $columnWidths = [
            'A' => 30, // CLIENTE
            'B' => 15, // TIPO
            'C' => 15, // DNI
            'D' => 15, // TELEFONO
            'E' => 10, // ITEM
            'F' => 40, // PRODUCTO (merged)
            'N' => 12, // CANTIDAD
            'O' => 15, // PRECIO UNITARIO
            'P' => 15, // ANTIDUMPING
            'Q' => 15, // VALORACION
            'R' => 15, // AD VALOREM
            'S' => 15, // PERCEPCION
            'T' => 12, // PESO
            'U' => 15  // VOLUMEN SISTEMA
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Aplicar bordes a toda la tabla
        $tableStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ],
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
        $sheet->getStyle('A1:U' . ($lastRow))->applyFromArray($tableStyle);

        // Formato de porcentaje para columna R
        $sheet->getStyle('R2:R' . ($lastRow - 1))
            ->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

        // Fórmula de suma para columna P con estilo
        $sheet->setCellValue('P' . $lastRow, '=SUM(P2:P' . ($lastRow - 1) . ')');
        $totalRowStyle = [
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2']
            ]
        ];
        $sheet->getStyle('P' . $lastRow)->applyFromArray($totalRowStyle);

        // Ajustar altura de filas
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getRowDimension(1)->setRowHeight(25); // Encabezado más alto
    }

    /**
     * Verifica si dos nombres coinciden de manera exacta
     */
    private function isNameMatch($fullName, $partialName)
    {
        // Verificación inicial
        if (empty($fullName) || empty($partialName)) {
            return false;
        }

        $fullName = $this->normalizeString($fullName);
        $partialName = $this->normalizeString($partialName);

        // Verificar que normalizeString no devolvió cadenas vacías
        if (empty($fullName) || empty($partialName)) {
            return false;
        }

        // Comparación exacta - la única forma válida de match
        if ($fullName === $partialName) {
            return true;
        }

        // Comparar palabra por palabra - TODAS las palabras deben coincidir exactamente
        $fullWords = array_filter(explode(' ', $fullName));
        $partialWords = array_filter(explode(' ', $partialName));

        // Deben tener el mismo número de palabras para ser exactos
        if (count($fullWords) !== count($partialWords)) {
            return false;
        }

        // Verificar que tenemos palabras para comparar
        if (empty($fullWords) || empty($partialWords)) {
            return false;
        }

        // Ordenar las palabras para comparar independientemente del orden
        sort($fullWords);
        sort($partialWords);

        // Comparar palabra por palabra - deben ser exactamente iguales
        for ($i = 0; $i < count($fullWords); $i++) {
            if ($fullWords[$i] !== $partialWords[$i]) {
                return false;
            }
        }

        return true;
    }
    private function normalizeString($string)
    {
        $string = strtolower(trim($string));
        $accents = [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ā' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'ē' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'ī' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'ō' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'ū' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'Á' => 'a',
            'À' => 'a',
            'Ä' => 'a',
            'Â' => 'a',
            'Ā' => 'a',
            'Ã' => 'a',
            'É' => 'e',
            'È' => 'e',
            'Ë' => 'e',
            'Ê' => 'e',
            'Ē' => 'e',
            'Í' => 'i',
            'Ì' => 'i',
            'Ï' => 'i',
            'Î' => 'i',
            'Ī' => 'i',
            'Ó' => 'o',
            'Ò' => 'o',
            'Ö' => 'o',
            'Ô' => 'o',
            'Ō' => 'o',
            'Õ' => 'o',
            'Ú' => 'u',
            'Ù' => 'u',
            'Ü' => 'u',
            'Û' => 'u',
            'Ū' => 'u',
            'Ñ' => 'n',
            'Ç' => 'c'
        ];

        return strtr($string, $accents);
    }
    public function updateEstadoCotizacionFinal(Request $request)
    {
        try {
            $cotizacion = Cotizacion::find($request->idCotizacion);
            $cotizacion->estado_cotizacion_final = $request->estado;
            $cotizacion->save();
            if ($request->estado == 'COBRANDO') {
                $cotizacion = DB::table($this->table_contenedor_cotizacion . ' as CC')
                    ->select([
                        'CC.telefono',
                        'CC.id_contenedor',
                        'CC.impuestos_final',
                        'CC.volumen_final',
                        'CC.monto_final',
                        'CC.tarifa_final',
                        'CC.nombre',
                        'CC.logistica_final',
                        DB::raw('(
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                            JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_cotizacion = CC.id
                            AND (ccp.name = "LOGISTICA" OR ccp.name = "IMPUESTOS")
                        ) as total_pagos')
                    ])
                    ->where('CC.id', $request->idCotizacion)
                    ->first();

                if (!$cotizacion) {
                    throw new \Exception('Cotización no encontrada');
                }
                $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
                $phoneNumberId = $telefono ? $telefono . '@c.us' : '';
                $totalPagos = $cotizacion->total_pagos;
                $volumen = $cotizacion->volumen_final;
                $nombre = $cotizacion->nombre;
                $logisticaFinal = $cotizacion->logistica_final;
                $impuestosFinal = $cotizacion->impuestos_final;
                $total = $logisticaFinal + $impuestosFinal;
                $totalAPagar = $total - $totalPagos;
                $idContenedor = $cotizacion->id_contenedor;
                $contenedor = Contenedor::select('fecha_arribo', 'carga')
                    ->select('fecha_arribo', 'carga')
                    ->where('id', $idContenedor)
                    ->first();

                if (!$contenedor) {
                    throw new \Exception('Contenedor no encontrado');
                }
                $carga = $contenedor->carga;
                $fechaArribo = $contenedor->fecha_arribo;
                $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
                $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';
                $message = "📦 *Consolidado #" . $carga . "*\n" .
                    "Hola " . $nombre . " 😁 un gusto saludarte! \n" .
                    "A continuación te envio la cotización final de tu importación📋📦.\n" .
                    "🙋‍♂️PAGO PENDIENTE: \n" .
                    "☑️Costo CBM: $" . number_format($logisticaFinal, 2) . "\n" .
                    "☑️Impuestos: $" . number_format($impuestosFinal, 2) . "\n" .
                    "☑️Total: $" . number_format($total, 2) . "\n" .
                    "Pronto le aviso nuevos avances, que tengan buen dia \n" .
                    "Último día de pago: " . date('d/m/Y', strtotime($fechaArribo)) . "\n";
                $this->sendMessage($message);
                $pathCotizacionFinalPDF = $this->getBoletaForSend($request->idCotizacion);
                Log::info('pathCotizacionFinalPDF: ' . $pathCotizacionFinalPDF);
                $this->sendMedia($pathCotizacionFinalPDF, null, null, null, 3);
                $message = "Resumen de Pago\n" .
                    "✅Cotización final: $" . number_format($total, 2) . "\n" .
                    "✅Adelanto: $" . number_format($totalPagos, 2) . "\n" .
                    "✅ *Pendiente de pago: $" . number_format($totalAPagar, 2) . "*\n";
                $this->sendMessage($message, null, 5);
                $pagosUrl = public_path('assets/images/pagos-full.jpg');    
                $this->sendMedia($pagosUrl, 'image/jpg', null, null, 10);
            }
            return response()->json([
                'success' => true,
                'message' => 'Estado de cotización final actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado de cotización final: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar recordatorio de pago por WhatsApp al cliente para una cotización final.
     * Body (opcional): { "sleep": <segundos de espera entre llamadas> }
     */
    public function sendReminderPago(Request $request, $idCotizacion)
    {
        try {
            // Recuperar datos principales de la cotización (incluye subconsulta para totales pagados)
            $cotizacion = DB::table($this->table_contenedor_cotizacion . ' as CC')
                ->select([
                    'CC.telefono',
                    'CC.id_contenedor',
                    'CC.impuestos_final',
                    'CC.volumen_final',
                    'CC.monto_final',
                    'CC.tarifa_final',
                    'CC.nombre',
                    'CC.logistica_final',
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND (ccp.name = "LOGISTICA" OR ccp.name = "IMPUESTOS")
                    ) as total_pagos')
                ])
                ->where('CC.id', $idCotizacion)
                ->first();

            if (! $cotizacion) {
                return response()->json(['message' => 'Cotización no encontrada', 'success' => false], 404);
            }

            // Obtener contenedor para número de consolidado
            $contenedor = Contenedor::select('carga')->where('id', $cotizacion->id_contenedor)->first();
            $carga = $contenedor ? $contenedor->carga : 'N/A';

            // Calculos de montos
            $logisticaFinal = $cotizacion->logistica_final ?? 0;
            $impuestosFinal = $cotizacion->impuestos_final ?? 0;
            $totalCotizacion = $logisticaFinal + $impuestosFinal;
            $totalPagos = $cotizacion->total_pagos ?? 0;
            $pendiente = $totalCotizacion - $totalPagos;

            // Preparar mensaje según plantilla solicitada
            $message = "🙋🏽‍♀ RECORDATORÍO DE PAGO\n\n" .
                "📦 Consolidado #" . $carga . "\n" .
                "Usted cuenta con un pago pendiente, es necesario realizar el pago para continuar con el proceso de nacionalización.\n\n" .
                "Resumen de Pago\n" .
                "✅ Cotización final: $" . number_format($totalCotizacion, 2, '.', '') . "\n" .
                "✅ Adelanto: $" . number_format($totalPagos, 2, '.', '') . "\n" .
                "✅ *Pendiente de pago: $" . number_format($pendiente, 2, '.', '') . "*\n\n" .
                "Por favor debe enviar el comprobante de pago a la brevedad.";
            // Preparar número y enviar (normalizar como en otros lugares del proyecto)
            $rawTelefono = $cotizacion->telefono ?? '';
            // Remover todo lo que no sea dígito
            $telefonoDigits = preg_replace('/\D/', '', $rawTelefono);

            // Si no tiene código de país, asumir Perú (+51) para números locales de 9 dígitos
            if (strlen($telefonoDigits) === 9) {
                $telefonoDigits = '51' . $telefonoDigits;
            } elseif (strlen($telefonoDigits) === 10 && substr($telefonoDigits, 0, 1) === '0') {
                $telefonoDigits = '51' . substr($telefonoDigits, 1);
            }

            if (empty($telefonoDigits)) {
                Log::warning('sendReminderPago: teléfono inválido o vacío', ['cotizacion_id' => $idCotizacion, 'telefono_raw' => $rawTelefono]);
                return response()->json(['message' => 'Teléfono del cliente inválido o vacío', 'success' => false], 400);
            }

            $this->phoneNumberId = $telefonoDigits . '@c.us';

            // Log previo al envío para diagnóstico
            Log::info('sendReminderPago enviando', [
                'cotizacion_id' => $idCotizacion,
                'telefono_raw' => $rawTelefono,
                'telefono_normalized' => $telefonoDigits,
                'phoneNumberId' => $this->phoneNumberId
            ]);

            $sleep = $request->input('sleep', 0);
            $result = $this->sendMessage($message, $this->phoneNumberId, $sleep);

            Log::info('sendReminderPago resultado', ['cotizacion_id' => $idCotizacion, 'result' => $result]);

            return response()->json(['message' => 'Recordatorio enviado', 'success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            Log::error('Error en sendReminderPago: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Error al enviar recordatorio: ' . $e->getMessage(), 'success' => false], 500);
        }
    }

    /**
     * Obtiene la ruta local de un archivo, ya sea desde URL o ruta local
     * Si es una URL, descarga el archivo temporalmente
     */
    private function getLocalPath($fileUrl)
    {
        try {
            // Si es una URL externa
            if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                // Crear directorio temporal si no existe
                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                // Generar nombre de archivo temporal
                $tempFile = $tempDir . '/' . time() . '_' . basename($fileUrl);
                
                // Descargar archivo
                $fileContent = file_get_contents($fileUrl);
                if ($fileContent === false) {
                    throw new \Exception("No se pudo descargar el archivo: " . $fileUrl);
                }
                
                // Guardar archivo temporal
                if (file_put_contents($tempFile, $fileContent) === false) {
                    throw new \Exception("No se pudo guardar el archivo temporal");
                }
                
                return $tempFile;
            }

            // Si es una ruta local, probar diferentes ubicaciones
            $possiblePaths = [
                storage_path('app/public/' . $fileUrl),
                storage_path($fileUrl),
                public_path($fileUrl),
                storage_path('app/' . $fileUrl),
                base_path($fileUrl),
                $fileUrl // ruta tal cual
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            throw new \Exception("No se encontró el archivo en ninguna ubicación: " . $fileUrl);
        } catch (\Exception $e) {
            Log::error('Error en getLocalPath: ' . $e->getMessage(), [
                'fileUrl' => $fileUrl,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function store(Request $request)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'voucher' => 'required|file',
                'idCotizacion' => 'required|integer',
                'idContenedor' => 'required|integer',
                'monto' => 'required|numeric|min:0',
                'fecha' => 'required|date',
                'banco' => 'required|string|max:255'
            ]);

            // Autenticar usuario
            $user = JWTAuth::parseToken()->authenticate();

            // Subir el archivo voucher
            $voucherUrl = null;
            if ($request->hasFile('voucher')) {
                $file = $request->file('voucher');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $voucherUrl = $file->storeAs('cargaconsolidada/pagos', $fileName, 'public');
            }

            // Verificar si la cotización tiene cotizacion_final_url
            $cotizacion = DB::table($this->table_contenedor_cotizacion)
                ->select('cotizacion_final_url')
                ->where('id', $request->idCotizacion)
                ->first();

            $cotizacionFinalUrl = $cotizacion ? $cotizacion->cotizacion_final_url : null;

            if ($cotizacion) {
                Log::info('Cotizacion Final URL: ' . $cotizacion->cotizacion_final_url);
            }

            // Determinar el concepto de pago
            $conceptId =  $this->CONCEPT_PAGO_IMPUESTOS;

            // Preparar datos para insertar
            $data = [
                'voucher_url' => $voucherUrl,
                'id_cotizacion' => $request->idCotizacion,
                'id_contenedor' => $request->idContenedor,
                'id_concept' => $conceptId,
                'monto' => $request->monto,
                'payment_date' => date('Y-m-d', strtotime($request->fecha)),
                'banco' => $request->banco,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Insertar en la base de datos
            $inserted = DB::table($this->table_contenedor_consolidado_cotizacion_coordinacion_pagos)->insert($data);

            if ($inserted) {
                $cotizacionInfo = DB::table($this->table_contenedor_cotizacion . ' as CC')
                    ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
                    ->select('CC.nombre as cliente_nombre', 'CC.documento as cliente_documento', 'C.carga as contenedor_nombre')
                    ->where('CC.id', $request->idCotizacion)
                    ->first();
                if ($cotizacionInfo) {
                    Notificacion::create([
                        'titulo' => 'Nuevo Pago de Impuestos Registrado',
                        'mensaje' => "Se ha registrado un pago de impuestos de S/ {$request->monto} para el cliente {$cotizacionInfo->cliente_nombre} del contenedor {$cotizacionInfo->contenedor_nombre}",
                        'descripcion' => "Cliente: {$cotizacionInfo->cliente_nombre} | Documento: {$cotizacionInfo->cliente_documento} | Monto: S/ {$request->monto} | Banco: {$request->banco} | Fecha: {$request->fecha}",
                        'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                        'rol_destinatario' => Usuario::ROL_ADMINISTRACION,
                        'navigate_to' => 'verificacion',
                        'navigate_params' => [
                            'idCotizacion' => $request->idCotizacion,
                            'tab' => 'consolidado'
                        ],
                        'tipo' => Notificacion::TIPO_SUCCESS,
                        'icono' => 'mdi:cash-check',
                        'prioridad' => Notificacion::PRIORIDAD_ALTA,
                        'referencia_tipo' => 'pago_impuestos',
                        'referencia_id' => $request->idCotizacion,
                        'activa' => true,
                        'creado_por' => $user->ID_Usuario,
                        'configuracion_roles' => [
                            Usuario::ROL_ADMINISTRACION => [
                                'titulo' => 'Pago Impuestos - Verificar',
                                'mensaje' => "Nuevo pago de S/ {$request->monto} para verificar",
                                'descripcion' => "Cliente: {$cotizacionInfo->cliente_nombre} | Contenedor: {$cotizacionInfo->contenedor_nombre}"
                            ]
                        ]
                    ]);
                }
                // Sincronizar estado de la cotización a partir de los pagos (LOGISTICA / IMPUESTOS)
                try {
                    app()->make(\App\Http\Controllers\CargaConsolidada\PagosController::class)
                        ->syncEstadoCotizacionFromPayments($request->idCotizacion, false);
                } catch (\Exception $e) {
                    Log::error('Error sincronizando estado de cotizacion tras store pago: ' . $e->getMessage());
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Pago guardado exitosamente',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el pago en la base de datos'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el pago: ' . $e->getMessage()
            ], 500);
        }
    }
    public function uploadFacturaComercial(Request $request)
    {
        try {
            $idContenedor = $request->idContenedor;
            $file = $request->file;
            $path = $file->storeAs('cargaconsolidada/cotizacionfinal/' . $idContenedor, 'factura_general'.time().'.xlsx');
            $contenedor = Contenedor::find($idContenedor);
            $contenedor->factura_general_url = $path;
            $contenedor->save();
            return response()->json([
                'success' => true,
                'message' => 'Factura general actualizada correctamente',
                'path' => $path
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar factura general: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar factura general: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir una cotización final a partir de un archivo (Excel) para una cotización específica.
     * Campos esperados: file (xlsx/xls), idCotizacion (int)
     */
    public function uploadCotizacionFinalFile(Request $request, $idCotizacion)
    {
        try {
            // Requerir idCotizacion: el flujo debe ser por id para evitar ambigüedades
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls',
                
            ]);

            $file = $request->file('file');
            $fileExt = $file->getClientOriginalExtension();

            // Buscar cotizacion por id (obligatorio)
            $cotizacion = Cotizacion::find($idCotizacion);
            if (! $cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            $idContenedor = $cotizacion->id_contenedor ?? 'unknown';

            // Guardar archivo en storage/public
            $fileName = time() . '_cotizacion_final_' . $idCotizacion . '.' . $fileExt;
            $relativeDirectory = 'cotizacion_final/' . $idContenedor;
            $storedPath = $file->storeAs($relativeDirectory, $fileName, 'public');
            $dbPath = $storedPath;

            // Leer desde el archivo guardado
            $fullStoredPath = storage_path('app/public/' . $storedPath);
            Log::info('Intentando leer Excel desde: ' . $fullStoredPath);
            $spreadsheet = IOFactory::load($fullStoredPath);

            // --- Parsear valores finales desde el Excel
            $sheet = $spreadsheet->getSheet(0);

            // Helper inline para obtener y normalizar valores numéricos desde celdas
            $getNumeric = function($cellAddress) use ($sheet) {
                try {
                    $raw = $sheet->getCell($cellAddress)->getCalculatedValue();
                } catch (\Exception $e) {
                    try { $raw = $sheet->getCell($cellAddress)->getValue(); } catch (\Exception $_) { $raw = null; }
                }
                if ($raw === null) return null;
                $str = (string) $raw;
                // Remover símbolos comunes de moneda y espacios
                $clean = preg_replace('/[^0-9\.,\-]/', '', $str);
                // Normalizar coma decimal -> punto si hay más comas que puntos
                if (substr_count($clean, ',') > 0 && substr_count($clean, '.') === 0) {
                    $clean = str_replace(',', '.', $clean);
                } else {
                    // eliminar comas de miles
                    $clean = str_replace(',', '', $clean);
                }
                if ($clean === '' || $clean === null) return null;
                return floatval($clean);
            };

            $hasAntidumping = strtoupper(trim((string) $sheet->getCell('B23')->getValue())) === 'ANTIDUMPING';

            if ($hasAntidumping) {
                $fob_final = $getNumeric('K30');
                $logistica_sheet = $getNumeric('K31');
                $impuestos_final = $getNumeric('K32');
                $monto_final = $getNumeric('K31');
            } else {
                $fob_final = $getNumeric('K29');
                $logistica_sheet = $getNumeric('K30');
                $impuestos_final = $getNumeric('K31');
                $monto_final = $getNumeric('K30');
            }

            $tarifa_final = $getNumeric('K27');
            $volumen_final = $getNumeric('J11');

            $pesoCellRaw = $sheet->getCell('J9')->getValue();
            $peso_final = null;
            if ($pesoCellRaw !== null) {
                $pesoClean = preg_replace('/[^0-9\.,\-]/', '', (string) $pesoCellRaw);
                if ($pesoClean !== '' && $pesoClean !== null) {
                    if (substr_count($pesoClean, ',') > 0 && substr_count($pesoClean, '.') === 0) {
                        $pesoClean = str_replace(',', '.', $pesoClean);
                    } else {
                        $pesoClean = str_replace(',', '', $pesoClean);
                    }
                    $peso_final = is_numeric($pesoClean) ? (float) $pesoClean : null;
                    if ($peso_final !== null && stripos((string) $pesoCellRaw, 'tn') !== false) {
                        $peso_final *= 1000;
                    }
                }
            }

            $logistica_final = $logistica_sheet;

            if ($tarifa_final === null && $volumen_final !== null && $volumen_final > 0) {
                $tarifa_final = $volumen_final < 1 ? $logistica_final : ($logistica_final / $volumen_final);
            } elseif ($tarifa_final !== null && $volumen_final !== null && $volumen_final > 0) {
                $logistica_final = $volumen_final < 1 ? $tarifa_final : $tarifa_final * $volumen_final;
            }

            // Validación estricta: monto, impuestos y logística deben extraerse correctamente
            if ($monto_final === null || $impuestos_final === null || $logistica_final === null) {
                // borrar archivo almacenado
                try { Storage::disk('public')->delete($dbPath); } catch (\Exception $_) {}
                Log::warning('Extraccion obligatoria falló (monto/impuestos/logistica) al subir cotizacion final. Archivo eliminado: ' . $dbPath);
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron extraer los campos obligatorios (monto, impuestos, logística) del Excel. La cotización final no fue guardada.'
                ], 422);
            }

            // Preparar datos para actualizar: SOLO campos derivados de la cotizacion final
            $updateData = [
                'cotizacion_final_url' => $dbPath,
                'estado_cotizacion_final' => 'COTIZADO'
            ];

            $updateData['monto_final'] = $monto_final;
            $updateData['impuestos_final'] = $impuestos_final;
            $updateData['logistica_final'] = $logistica_final;

            $updateData['fob_final'] = $fob_final ?? 0;
            $updateData['peso_final'] = $peso_final ?? 0;
            $updateData['tarifa_final'] = $tarifa_final ?? 0;
            $updateData['volumen_final'] = $volumen_final ?? 0;

            // Actualizar la cotizacion (por id obligatorio)
            try {
                DB::table($this->table_contenedor_cotizacion)
                    ->where('id', $idCotizacion)
                    ->update($updateData);
                Log::info('Cotización final actualizada', ['id' => $idCotizacion, 'update' => $updateData]);
            } catch (\Exception $dbError) {
                Log::error('Error al actualizar cotizacion final: ' . $dbError->getMessage(), ['id' => $idCotizacion, 'update' => $updateData]);
                if (strpos($dbError->getMessage(), 'Out of range value') !== false) {
                    // aplicar límites y reintentar
                    $limited = $updateData;
                    if (isset($limited['monto_final'])) $limited['monto_final'] = min($limited['monto_final'], 999999.99);
                    if (isset($limited['logistica_final'])) $limited['logistica_final'] = min($limited['logistica_final'], 999999.99);
                    if (isset($limited['impuestos_final'])) $limited['impuestos_final'] = min($limited['impuestos_final'], 999999.99);
                    try {
                        DB::table($this->table_contenedor_cotizacion)
                            ->where('id', $idCotizacion)
                            ->update($limited);
                        Log::info('Cotización final actualizada con valores limitados', ['id' => $idCotizacion]);
                    } catch (\Exception $retryErr) {
                        Log::error('Fallo persistente al actualizar cotizacion final: ' . $retryErr->getMessage(), ['id' => $idCotizacion]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Error al actualizar la cotización final en la base de datos.'
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al actualizar la cotización final en la base de datos.'
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Cotización final subida y registrada correctamente',
                'data' => $updateData
            ]);

        } catch (\Exception $e) {
            Log::error('Error en uploadCotizacionFinalFile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir cotización final: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Limpia y valida caracteres UTF-8
     */
    private function cleanUtf8String($string)
    {
        if (empty($string)) {
            return '';
        }

        // Convertir a string si no lo es
        $string = (string) $string;

        // Verificar si la cadena es UTF-8 válida
        if (!mb_check_encoding($string, 'UTF-8')) {
            // Intentar convertir desde diferentes encodings
            $encodings = ['ISO-8859-1', 'ISO-8859-15', 'Windows-1252', 'CP1252'];
            
            foreach ($encodings as $encoding) {
                if (mb_check_encoding($string, $encoding)) {
                    $string = mb_convert_encoding($string, 'UTF-8', $encoding);
                    break;
                }
            }
        }

        // Limpiar caracteres inválidos
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Remover caracteres de control excepto tab, newline, carriage return
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        
        // Normalizar espacios
        $string = preg_replace('/\s+/', ' ', $string);
        
        return trim($string);
    }

    /**
     * Maneja peticiones OPTIONS para CORS
     */
    public function handleOptions()
    {
        return response()->json([], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    /**
     * Genera Excel masivo de cotizaciones para múltiples clientes
     */
    public function generateMassiveExcelPayrolls(Request $request)
    {
        try {
            // Validar datos de entrada
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls',
                'idContenedor' => 'required|integer'
            ]);

            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '2048M');
            $idContainer = $request->idContenedor;
            
            $data = $this->getMassiveExcelData($request->file('file'));
            
            Log::info('Datos procesados del Excel: ' . json_encode($data));
            
            // Obtener datos de cotizaciones confirmadas
            $result = DB::table($this->table_contenedor_cotizacion . ' as cc')
                ->join($this->table_contenedor_tipo_cliente . ' as tc', 'cc.id_tipo_cliente', '=', 'tc.id')
                ->select([
                    'cc.id',
                    'cc.tarifa',
                    'cc.nombre',
                    'tc.id as id_tipo_cliente',
                    'tc.name as tipoCliente',
                    'cc.correo',
                    'cc.vol_selected',
                    'cc.volumen',
                    'cc.volumen_china',
                    'cc.volumen_doc'
                ])
                ->where('id_contenedor', $idContainer)
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from($this->table_contenedor_cotizacion_proveedores)
                        ->whereRaw($this->table_contenedor_cotizacion_proveedores . '.id_cotizacion = cc.id');
                })
                ->get();

            Log::info('Datos de cotizaciones encontrados: ' . json_encode($result));
            Log::info('Datos de excel: ' . json_encode($data));
            // Procesar datos y hacer matching
            foreach ($data as &$cliente) {
                $nombreCliente = $cliente['cliente']['nombre'];
                $matchFound = false;

                foreach ($result as $item) {
                    Log::info('Comparando: ' . $nombreCliente . ' con ' . $item->nombre);
                    if ($this->isNameMatch($nombreCliente, $item->nombre)) {
                        Log::info('Coincidencia encontrada: ' . $nombreCliente . ' con ' . json_encode($item));
                        $cliente['cliente']['tarifa'] = $item->tarifa ?? 0;
                        Log::info('Tarifa: ' . $cliente['cliente']['tarifa']);
                        $cliente['cliente']['correo'] = $item->correo ?? '';
                        $cliente['cliente']['tipo_cliente'] = $item->tipoCliente ?? '';
                        $cliente['cliente']['id_tipo_cliente'] = $item->id_tipo_cliente ?? 0;
                        $cliente['id'] = $item->id;
                        
                        // Asignar volumen basado en vol_selected, con fallback a volumen disponible
                        $volumenAsignado = 0;
                        if ($item->vol_selected == 'volumen' && is_numeric($item->volumen)) {
                            $volumenAsignado = (float)$item->volumen;
                        } else if ($item->vol_selected == 'volumen_china' && is_numeric($item->volumen_china)) {
                            $volumenAsignado = (float)$item->volumen_china;
                        } else if ($item->vol_selected == 'volumen_doc' && is_numeric($item->volumen_doc)) {
                            $volumenAsignado = (float)$item->volumen_doc;
                        } else {
                            // Si vol_selected no está definido o es inválido, usar el primer volumen disponible
                            if (is_numeric($item->volumen) && $item->volumen > 0) {
                                $volumenAsignado = (float)$item->volumen;
                            } else if (is_numeric($item->volumen_china) && $item->volumen_china > 0) {
                                $volumenAsignado = (float)$item->volumen_china;
                            } else if (is_numeric($item->volumen_doc) && $item->volumen_doc > 0) {
                                $volumenAsignado = (float)$item->volumen_doc;
                            }
                        }
                        $cliente['cliente']['volumen'] = $volumenAsignado;
                        
                        Log::info('Volumen asignado para ' . $nombreCliente . ': ' . $volumenAsignado . ' (vol_selected: ' . ($item->vol_selected ?? 'null') . ')');
                        $matchFound = true;
                        break;
                    }
                }

                // Si no se encontró match, asignar valores por defecto
                if (!$matchFound) {
                    Log::warning('No se encontró match para cliente: ' . $nombreCliente);
                    $cliente['cliente']['tarifa'] = 0;
                    $cliente['cliente']['correo'] = '';
                    $cliente['cliente']['tipo_cliente'] = '';
                    $cliente['cliente']['id_tipo_cliente'] = 0;
                    $cliente['cliente']['volumen'] = 0;
                    $cliente['id'] = 0;
                }
            }
            unset($cliente);

            // Generar nombre único para el archivo ZIP temporal
            $zipFileName = 'Boletas_' . $idContainer . '_' . time() . '.zip';
            $zipFilePath = storage_path('app' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $zipFileName);
            
            // Crear directorio temporal si no existe
            $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Eliminar archivo ZIP anterior si existe
            if (file_exists($zipFilePath)) {
                unlink($zipFilePath);
                Log::info('Archivo ZIP anterior eliminado: ' . $zipFilePath);
            }

            // Crear nuevo archivo ZIP
            $zip = new ZipArchive();
       
            $zipResult = $zip->open($zipFilePath, ZipArchive::CREATE);
            if ($zipResult !== TRUE) {
                $errorMessages = [
                    ZipArchive::ER_OK => 'Sin errores',
                    ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
                    ZipArchive::ER_RENAME => 'Renaming temporary file failed',
                    ZipArchive::ER_CLOSE => 'Closing zip archive failed',
                    ZipArchive::ER_SEEK => 'Seek error',
                    ZipArchive::ER_READ => 'Read error',
                    ZipArchive::ER_WRITE => 'Write error',
                    ZipArchive::ER_CRC => 'CRC error',
                    ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
                    ZipArchive::ER_NOENT => 'No such file',
                    ZipArchive::ER_EXISTS => 'File already exists',
                    ZipArchive::ER_OPEN => 'Can\'t open file',
                    ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
                    ZipArchive::ER_ZLIB => 'Zlib error',
                    ZipArchive::ER_MEMORY => 'Memory allocation failure',
                    ZipArchive::ER_CHANGED => 'Entry has been changed',
                    ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
                    ZipArchive::ER_EOF => 'Premature EOF',
                    ZipArchive::ER_INVAL => 'Invalid argument',
                    ZipArchive::ER_NOZIP => 'Not a zip archive',
                    ZipArchive::ER_INTERNAL => 'Internal error',
                    ZipArchive::ER_INCONS => 'Zip archive inconsistent',
                    ZipArchive::ER_REMOVE => 'Can\'t remove file',
                    ZipArchive::ER_DELETED => 'Entry has been deleted'
                ];
                
                $errorMessage = $errorMessages[$zipResult] ?? 'Error desconocido';
                Log::error('Error al crear archivo ZIP. Código: ' . $zipResult . ' - ' . $errorMessage);
                throw new \Exception('No se pudo crear el archivo ZIP. Error: ' . $errorMessage . ' (Código: ' . $zipResult . ')');
            }
            
            Log::info('Archivo ZIP creado exitosamente: ' . $zipFilePath);

            // Generar Excel para cada cliente
            Log::info('Total de clientes a procesar: ' . count($data));
            $processedCount = 0;
            
            foreach ($data as $key => $value) {
                // Validar que el cliente tiene los datos necesarios
                if (!isset($value['cliente']['tarifa']) || $value['cliente']['tarifa'] == 0) {
                    Log::warning('Cliente sin tarifa válida, saltando: ' . $value['cliente']['nombre']);
                    continue;
                }

                if (!isset($value['id']) || $value['id'] == 0) {
                    Log::warning('Cliente sin ID válido, saltando: ' . $value['cliente']['nombre']);
                    continue;
                }

                if (!isset($value['cliente']['volumen']) || $value['cliente']['volumen'] == 0) {
                    Log::warning('Cliente sin volumen válido, saltando: ' . $value['cliente']['nombre'] . ' (volumen: ' . ($value['cliente']['volumen'] ?? 'null') . ')');
                    continue;
                }

                if (!isset($value['cliente']['productos']) || empty($value['cliente']['productos'])) {
                    Log::warning('Cliente sin productos, saltando: ' . $value['cliente']['nombre']);
                    continue;
                }

                $processedCount++;

                try {
                    Log::info('Iniciando generación de Excel para: ' . $value['cliente']['nombre']);
                    
                    // Cargar plantilla de Excel como en el original de CodeIgniter
                    $templatePath = public_path('assets/templates/Boleta_Template.xlsx');
                    if (!file_exists($templatePath)) {
                        Log::error('Plantilla no encontrada: ' . $templatePath);
                        throw new \Exception('Plantilla de cotización no encontrada');
                    }
                    $objPHPExcel = IOFactory::load($templatePath);
                    
                    $result = $this->getFinalCotizacionExcelv2($objPHPExcel, $value, $idContainer);
                    
                    if (!$result || !isset($result['excel_file_name']) || !isset($result['excel_file_path'])) {
                        Log::error('getFinalCotizacionExcelv2 no retornó datos válidos para: ' . $value['cliente']['nombre']);
                        continue;
                    }
                    
                    $excelFileName = $result['excel_file_name'];
                    $excelFilePath = $result['excel_file_path'];
                    $fullExcelPath = public_path('storage/' . $excelFilePath);

                    // Agregar archivo al ZIP
                    Log::info('Agregando archivo al ZIP: ' . $excelFileName);
                    Log::info('Archivo Excel existe: ' . (file_exists($fullExcelPath) ? 'Sí' : 'No'));
                    
                    if (file_exists($fullExcelPath)) {
                        $addResult = $zip->addFile($fullExcelPath, $excelFileName);
                        if ($addResult) {
                        } else {
                            Log::error('Error al agregar archivo al ZIP: ' . $excelFileName);
                        }
                    } else {
                        Log::error('El archivo Excel no existe: ' . $fullExcelPath);
                    }
                    $estadoCotizacionFinal = DB::table($this->table_contenedor_cotizacion)
                        ->where('id', $result['id'])
                        ->where('estado_cotizacion_final', '!=', 'PENDIENTE')
                        ->where('estado_cotizacion_final', '!=', null)
                        ->first();
                    
                    // Validar valores antes de actualizar la base de datos
                    $updateData = [
                        'cotizacion_final_url' => $result['cotizacion_final_url'],
                        'volumen_final' => $result['volumen_final'],
                        'monto_final' => $result['monto_final'],
                        'tarifa_final' => $result['tarifa_final'],
                        'impuestos_final' => $result['impuestos_final'],
                        'logistica_final' => $result['logistica_final'],
                        'fob_final' => $result['fob_final'],
                        'peso_final' => $result['peso_final'],
                    ];
                    if(!$estadoCotizacionFinal) {
                        $updateData['estado_cotizacion_final'] = 'COTIZADO';
                    }
                    
                    
                    // Actualizar tabla de cotizaciones con manejo de errores
                    try {
                        DB::table($this->table_contenedor_cotizacion)
                            ->where('id', $result['id'])
                            ->update($updateData);
                    } catch (\Exception $dbError) {
                        Log::error('Error al actualizar cotización en BD: ' . $dbError->getMessage(), [
                            'id' => $result['id'],
                            'cliente' => $value['cliente']['nombre'],
                            'update_data' => $updateData
                        ]);
                        
                        // Si es un error de rango numérico, intentar con valores limitados
                        if (strpos($dbError->getMessage(), 'Out of range value') !== false) {
                            Log::warning('Intentando actualizar con valores limitados...');
                            $limitedData = $updateData;
                            $limitedData['monto_final'] = min($limitedData['monto_final'], 999999.99);
                            $limitedData['logistica_final'] = min($limitedData['logistica_final'], 999999.99);
                            $limitedData['impuestos_final'] = min($limitedData['impuestos_final'], 999999.99);
                            $limitedData['fob_final'] = min($limitedData['fob_final'], 999999.99);
                            
                            try {
                                DB::table($this->table_contenedor_cotizacion)
                                    ->where('id', $result['id'])
                                    ->update($limitedData);
                                Log::info('Cotización actualizada con valores limitados');
                            } catch (\Exception $retryError) {
                                Log::error('Error persistente al actualizar cotización: ' . $retryError->getMessage());
                                continue; // Saltar este cliente y continuar con el siguiente
                            }
                        } else {
                            continue; // Saltar este cliente si no es un error de rango
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error procesando cliente ' . $value['cliente']['nombre'] . ': ' . $e->getMessage());
                    continue;
                }
            }            
            // Verificar si se agregaron archivos al ZIP
            $zipFileCount = $zip->numFiles;
            Log::info('Archivos en el ZIP: ' . $zipFileCount);
            
            if ($zipFileCount === 0) {
                Log::warning('No se agregaron archivos al ZIP. Creando archivo ZIP vacío con mensaje informativo.');
                
                // Crear contenido informativo directamente en el ZIP
                $infoContent = "No se encontraron clientes válidos para procesar.\n\nTotal de clientes en Excel: " . count($data) . "\nClientes procesados: " . $processedCount . "\n\nVerifique que los clientes tengan tarifa válida y datos completos.";
                
                // Agregar contenido directamente al ZIP sin crear archivo temporal
                $zip->addFromString('INFORMACION.txt', $infoContent);
                Log::info('Archivo informativo agregado al ZIP');
            }
            try {
                $zip->close();
            } catch (\Exception $zipCloseError) {
                Log::error('Error al cerrar ZIP: ' . $zipCloseError->getMessage());
                Log::error('Archivo ZIP existe al momento del error: ' . (file_exists($zipFilePath) ? 'Sí' : 'No'));
                throw new \Exception('Error al cerrar archivo ZIP: ' . $zipCloseError->getMessage());
            }

            // Restaurar límite de memoria
            ini_set('memory_limit', $originalMemoryLimit);
            gc_collect_cycles();
            if (!file_exists($zipFilePath)) {
                Log::error('El archivo ZIP no existe después de cerrarlo');
                throw new \Exception('El archivo ZIP no se creó correctamente');
            }
            
            $fileSize = filesize($zipFilePath);
            Log::info('Tamaño del archivo ZIP: ' . ($fileSize !== false ? $fileSize . ' bytes' : 'No se puede leer'));
            
            if ($fileSize === false || $fileSize === 0) {
                Log::error('El archivo ZIP está vacío o no se puede leer el tamaño');
                throw new \Exception('El archivo ZIP está vacío o no se puede leer');
            }
            
            Log::info('Descargando archivo ZIP: ' . $zipFilePath . ' (Tamaño: ' . $fileSize . ' bytes)');
            
            // Configurar headers para descarga directa con CORS
            $response = response()->download($zipFilePath, 'Boletas_' . $idContainer . '.zip')
                ->deleteFileAfterSend(true);
            
            // Agregar headers CORS
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            $response->headers->set('Expires', '0');
            
            Log::info('Archivo ZIP enviado para descarga: ' . $zipFilePath);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Error en generateMassiveExcelPayrolls: ' . $e->getMessage());
            ini_set('memory_limit', $originalMemoryLimit ?? '128M');
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar Excel masivo: ' . $e->getMessage()
            ], 500);
        }
    }

 

    /**
     * Procesa datos masivos desde archivo Excel
     */
    public function getMassiveExcelData($excelFile)
    {
        try {
            // Validar que el archivo exista
            if (!$excelFile) {
                Log::error('Archivo Excel no proporcionado');
                throw new \Exception('Archivo Excel no proporcionado');
            }

            // Obtener el path del archivo - intentar diferentes métodos
            $filePath = null;
            
            // Método 1: getRealPath()
            if (method_exists($excelFile, 'getRealPath') && $excelFile->getRealPath()) {
                $filePath = $excelFile->getRealPath();
            }
            
            // Método 2: getPathname()
            if (!$filePath && method_exists($excelFile, 'getPathname') && $excelFile->getPathname()) {
                $filePath = $excelFile->getPathname();
            }
            
            // Método 3: path()
            if (!$filePath && method_exists($excelFile, 'path') && $excelFile->path()) {
                $filePath = $excelFile->path();
            }
            
            if (!$filePath || !file_exists($filePath)) {
                Log::error('No se pudo obtener el path del archivo o el archivo no existe');
                throw new \Exception('No se pudo acceder al archivo Excel subido');
            }
            
            $excel = IOFactory::load($filePath);
            $worksheet = $excel->getActiveSheet();

            // Obtener el rango total de datos válidos
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Obtener todas las celdas combinadas
            $mergedCells = $worksheet->getMergeCells();

            // Función para obtener el valor real de una celda (considerando combinadas)
            $getCellValue = function ($col, $row) use ($worksheet, $mergedCells) {
                $cellAddress = $col . $row;
                $cellValue = trim($worksheet->getCell($cellAddress)->getValue());

                // Si la celda está vacía, buscar en celdas combinadas
                if (empty($cellValue)) {
                    foreach ($mergedCells as $mergedRange) {
                        // Verificar si es un rango (contiene :)
                        if (strpos($mergedRange, ':') !== false) {
                            // Dividir el rango manualmente
                            list($startCell, $endCell) = explode(':', $mergedRange);

                            // Extraer coordenadas de inicio y fin
                            preg_match('/([A-Z]+)(\d+)/', $startCell, $startMatches);
                            preg_match('/([A-Z]+)(\d+)/', $endCell, $endMatches);

                            if (count($startMatches) >= 3 && count($endMatches) >= 3) {
                                $startCol = $startMatches[1];
                                $startRow = (int)$startMatches[2];
                                $endCol = $endMatches[1];
                                $endRow = (int)$endMatches[2];

                                // Verificar si la celda actual está dentro del rango
                                if ($col >= $startCol && $col <= $endCol && $row >= $startRow && $row <= $endRow) {
                                    $cellValue = trim($worksheet->getCell($startCell)->getValue());
                                    break;
                                }
                            }
                        }
                    }
                }

                return $cellValue;
            };

            // Función para verificar si una fila pertenece a un cliente específico
            $getClientRowRange = function ($startRow) use ($worksheet, $getCellValue, $highestRow) {
                $endRow = $startRow;
                $clientName = $getCellValue('A', $startRow);

                // Buscar hasta dónde se extiende este cliente
                for ($row = $startRow + 1; $row <= $highestRow; $row++) {
                    $nextClientName = $getCellValue('A', $row);
                    if (!empty($nextClientName) && $nextClientName !== $clientName) {
                        break;
                    }
                    $endRow = $row;
                }

                return $endRow;
            };

            $clients = [];
            $processedRows = [];
            
            // Recorrer todas las filas buscando clientes (empezar desde fila 2 para saltar headers)
            for ($row = 2; $row <= $highestRow; $row++) {
                // Saltar filas ya procesadas
                if (in_array($row, $processedRows)) {
                    continue;
                }

                $clientName = $getCellValue('A', $row);

                // Verificar si hay un nombre de cliente válido o si es una fila de header
                if (empty($clientName) || $this->isHeaderRow($clientName)) {
                    continue;
                }

                // Determinar el rango de filas para este cliente
                $endRow = $getClientRowRange($row);

                // Marcar filas como procesadas
                for ($r = $row; $r <= $endRow; $r++) {
                    $processedRows[] = $r;
                }

                // Obtener datos básicos del cliente
                $client = [
                    'nombre' => $clientName,
                    'tipo' => $getCellValue('B', $row),
                    'dni' => $getCellValue('C', $row),
                    'telefono' => $getCellValue('D', $row),
                    'productos' => [],
                ];

                // Procesar productos dentro del rango del cliente
                for ($productRow = $row; $productRow <= $endRow; $productRow++) {
                    $producto = $getCellValue('F', $productRow);

                    if (empty($producto)) {
                        continue;
                    }

                    $cantidad = $getCellValue('N', $productRow);
                    $precioUnitario = $getCellValue('O', $productRow);

                    // Solo agregar productos con datos esenciales
                    if (!empty($cantidad) && !empty($precioUnitario)) {
                        $productoData = [
                            'nombre' => $producto,
                            'cantidad' => $cantidad,
                            'precio_unitario' => $precioUnitario,
                            'antidumping' => $getCellValue('P', $productRow) ?: 0,
                            'valoracion' => $getCellValue('Q', $productRow) ?: 0,
                            'ad_valorem' => $getCellValue('R', $productRow) ?: 0,
                            'percepcion' => $getCellValue('S', $productRow) ?: 0.035,
                            'peso' => $getCellValue('T', $productRow) ?: 0,
                            'cbm' => $getCellValue('U', $productRow) ?: '',
                        ];

                        $client['productos'][] = $productoData;
                    }
                }

                $clients[] = ['cliente' => $client];
            }
            
            return $clients;
        } catch (\Exception $e) {
            Log::error('Error en getMassiveExcelData: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Incrementa una columna Excel (A -> B -> C, etc.)
     */
    private function incrementColumn($column, $increment = 1)
    {
        $columnIndex = Coordinate::columnIndexFromString($column);
        $newIndex = $columnIndex + $increment;
        return Coordinate::stringFromColumnIndex($newIndex);
    }

    /**
     * Verifica si una fila es una fila de header
     */
    private function isHeaderRow($clientName)
    {
        $headerKeywords = [
            'CLIENTE', 'CLIENT', 'NOMBRE', 'NAME', 'TIPO', 'TYPE', 
            'DNI', 'DOCUMENTO', 'TELEFONO', 'PHONE', 'ITEM', 'PRODUCTO', 
            'PRODUCT', 'CANTIDAD', 'QUANTITY', 'PRECIO', 'PRICE'
        ];
        
        $clientNameUpper = strtoupper(trim($clientName));
        
        // Si el nombre del cliente contiene palabras típicas de header, es probablemente un header
        foreach ($headerKeywords as $keyword) {
            if (strpos($clientNameUpper, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calcula tarifa según tipo de cliente y volumen
     */
    private function calculateTarifaByTipoCliente($tipoCliente, $volumen, $tarifaBase)
    {
        $tipoCliente = trim(strtoupper($tipoCliente));
        $volumen = is_numeric($volumen) ? round((float)$volumen, 2) : 0;

        switch ($tipoCliente) {
            case "NUEVO":
                if ($volumen < 0.59 && $volumen > 0) {
                    return 280;
                } elseif ($volumen < 1.00 && $volumen > 0.59) {
                    return 375;
                } elseif ($volumen < 2.00 && $volumen > 1.00) {
                    return 375;
                } elseif ($volumen < 3.00 && $volumen > 2.00) {
                    return 350;
                } elseif ($volumen <= 4.10 && $volumen > 3.00) {
                    return 325;
                } elseif ($volumen > 4.10) {
                    return 300;
                }
                break;

            case "ANTIGUO":
                if ($volumen < 0.59 && $volumen > 0) {
                    return 260;
                } elseif ($volumen < 1.00 && $volumen > 0.59) {
                    return 350;
                } elseif ($volumen <= 2.09 && $volumen > 1.00) {
                    return 350;
                } elseif ($volumen <= 3.09 && $volumen > 2.09) {
                    return 325;
                } elseif ($volumen <= 4.10 && $volumen > 3.09) {
                    return 300;
                } elseif ($volumen > 4.10) {
                    return 280;
                }
                break;

            case "SOCIO":
                return 250; // Tarifa fija para socios
        }

        return $tarifaBase; // Retornar tarifa base si no coincide con ningún caso
    }

    /**
     * Configura la sección de tributos
     */
    private function configureTributosSection($objPHPExcel, $InitialColumn, $InitialColumnLetter, $borders, $grayColor)
    {
        $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B23:E23');
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B23', 'Tributos Aplicables');
        $style = $objPHPExcel->getActiveSheet()->getStyle('B23');
        $style->getFill()->setFillType(Fill::FILL_SOLID);
        $style->getFill()->getStartColor()->setARGB($grayColor);
        $objPHPExcel->getActiveSheet()->getStyle('B23:E23')->applyFromArray($borders);
        $objPHPExcel->getActiveSheet()->getStyle('B23')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $tributosHeaders = [
            'B26' => 'ANTIDUMPING',
            'B28' => 'AD VALOREM',
            'B29' => 'IGV',
            'B30' => 'IPM',
            'B31' => 'PERCEPCION',
            'B32' => 'TOTAL'
        ];

        foreach ($tributosHeaders as $cell => $value) {
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($cell, $value);
        }

        // Configurar totales
        $objPHPExcel->getActiveSheet()->getStyle('B26:' . $InitialColumn . '26')->applyFromArray($borders);
        $objPHPExcel->getActiveSheet()->getStyle('C27:' . $InitialColumn . '27')->applyFromArray($borders);
        
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '26', "=SUM(C26:" . $InitialColumnLetter . "26)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '27', "=SUM(C27:" . $InitialColumnLetter . "27)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '27')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '28', "=SUM(C28:" . $InitialColumnLetter . "28)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '28')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '29', "=SUM(C29:" . $InitialColumnLetter . "29)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '29')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '30', "=SUM(C30:" . $InitialColumnLetter . "30)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '30')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '31', "=SUM(C31:" . $InitialColumnLetter . "31)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '31')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '32', "=SUM($InitialColumn" . "28:" . $InitialColumn . "31)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '32')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
    }

    /**
     * Configura la sección de costos destino
     */
    private function configureCostosDestinoSection($objPHPExcel, $InitialColumn, $InitialColumnLetter, $borders, $grayColor)
    {
        $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B37:E37');
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B37', 'Costos Destinos');
        $style = $objPHPExcel->getActiveSheet()->getStyle('B37');
        $style->getFill()->setFillType(Fill::FILL_SOLID);
        $style->getFill()->getStartColor()->setARGB($grayColor);
        $objPHPExcel->getActiveSheet()->getStyle('B37:E37')->applyFromArray($borders);
        $objPHPExcel->getActiveSheet()->getStyle('B37')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $costosHeaders = [
            'B40' => 'ITEM',
            'B43' => 'ITEM',
            'B44' => 'COSTO TOTAL',
            'B45' => 'CANTIDAD',
            'B46' => 'COSTO UNITARIO',
            'B47' => 'COSTO SOLES'
        ];

        foreach ($costosHeaders as $cell => $value) {
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($cell, $value);
        }

        $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B41:E41');
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '43', "Total");
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '44', "=SUM(C44:" . $InitialColumnLetter . "44)");
        $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '44')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
    }

    /**
     * Configura la hoja principal
     */
    private function configureMainSheet($objPHPExcel, $data, $pesoTotal, $tipoCliente, $cbmTotalProductos, $tarifaValue, $antidumpingSum)
    {
        // Asegurarse de trabajar con la hoja principal (índice 0)
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet1 = $objPHPExcel->getActiveSheet();
        
        // Configurar información del cliente
        $sheet1->mergeCells('C8:C9');
        $sheet1->getStyle('C8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getStyle('C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->setCellValue('C8', $data['cliente']['nombre']);
        $sheet1->setCellValue('C10', $data['cliente']['dni']);
        $sheet1->setCellValue('C11', $data['cliente']['telefono']);
        
        // Configurar peso
        $sheet1->setCellValue('J9', $pesoTotal >= 1000 ? $pesoTotal / 1000 . " Tn" : $pesoTotal . " Kg");
        $sheet1->getStyle('J9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        
        // Configurar CBM
        $sheet1->setCellValue('J11', $cbmTotalProductos);
        $sheet1->getStyle('J11')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet1->setCellValue('I11', "CBM");
        
        // Configurar tipo de cliente
        $sheet1->setCellValue('F11', $tipoCliente);
        
        // Configurar columnas de referencia
        $productsCount = count($data['cliente']['productos']);
        $columnaIndex = Coordinate::stringFromColumnIndex($productsCount + 2);
        
        // Configurar referencias a la hoja de cálculos (que será la hoja "2" después de la reorganización)
        $sheet1->setCellValue('K14', "='2'!" . $columnaIndex . "11");
        $sheet1->setCellValue('K15', "='2'!" . $columnaIndex . "14 + '2'!" . $columnaIndex . "17");
        $sheet1->setCellValue('K20', "='2'!" . $columnaIndex . "28");
        $sheet1->setCellValue('K21', "='2'!" . $columnaIndex . "29");
        $sheet1->setCellValue('K22', "='2'!" . $columnaIndex . "30");
        $sheet1->setCellValue('K25', "='2'!" . $columnaIndex . "31");
        
        // Configurar fórmulas para casos sin antidumping
        Log::info('Configurando fórmulas principales con tarifa: ' . $tarifaValue . ' y CBM: ' . $cbmTotalProductos);
        $sheet1->setCellValue('K29', "=K14"); // FOB
        
        // K30 = Logística (como en CodeIgniter): si CBM<1 usar tarifa, sino tarifa*CBM
        $sheet1->setCellValue('K30', "=IF(J11<1, " . $tarifaValue . ", " . $tarifaValue . "*J11)");
        
        $sheet1->setCellValue('K31', "=K20+K21+K22+K25"); // Impuestos totales
        
        Log::info('Fórmula K30 configurada: ' . "=IF(J11<1, " . $tarifaValue . ", " . $tarifaValue . "*J11)");
        Log::info('CBM en J11: ' . $cbmTotalProductos . ', Tarifa: ' . $tarifaValue);
        
        // Configurar fórmulas para casos con antidumping (K32, K33)
        $sheet1->setCellValue('K32', "=K29+K30+K31"); // Total con antidumping

        // Configurar mensaje de WhatsApp
        $ClientName = $sheet1->getCell('C8')->getValue();
        $CobroCellValue = $sheet1->getCell('K30')->getCalculatedValue();
        $ImpuestosCellValue = $sheet1->getCell('K31')->getCalculatedValue();
        
        // Asegurar que los valores sean numéricos
        $CobroCellValue = is_numeric($CobroCellValue) ? (float)$CobroCellValue : 0;
        $ImpuestosCellValue = is_numeric($ImpuestosCellValue) ? round((float)$ImpuestosCellValue, 2) : 0;
        $TotalValue = $ImpuestosCellValue + $CobroCellValue;
        
        $N20CellValue = "Hola " . $ClientName . " 😁 un gusto saludarte!\n" .
            "A continuación te envío la cotización final de tu importación📋📦.\n" .
            "🙋‍♂️ PAGO PENDIENTE :\n" .
            "☑️Costo CBM: $" . $CobroCellValue . "\n" .
            "☑️Impuestos: $" . $ImpuestosCellValue . "\n" .
            "☑️ Total: $" . $TotalValue . "\n" .
            "Pronto le aviso nuevos avances, que tengan buen día🚢\n" .
            "Último día de pago:";
            
        $sheet1->setCellValue('N20', $N20CellValue);

        // Remover página 2 (índice 1) y renombrar hoja de cálculos (índice 2) como "2"
        $objPHPExcel->removeSheetByIndex(1);
        $objPHPExcel->setActiveSheetIndex(1);
        $objPHPExcel->getActiveSheet()->setTitle('2');
    }

    /**
     * Genera cotización individual
     */
    public function generateIndividualCotizacion(Request $request, $idContenedor)
    {
        try {
            $request->validate([
                'cliente_data' => 'required|array',
                'cliente_data.cliente' => 'required|array',
                'cliente_data.cliente.nombre' => 'required|string',
                'cliente_data.cliente.productos' => 'required|array'
            ]);

            $data = $request->cliente_data;
            
            // Cargar plantilla de Excel como en el original de CodeIgniter
            $templatePath = public_path('assets/templates/Boleta_Template.xlsx');
            if (!file_exists($templatePath)) {
                Log::error('Plantilla no encontrada: ' . $templatePath);
                throw new \Exception('Plantilla de cotización no encontrada');
            }
            $objPHPExcel = IOFactory::load($templatePath);
            
            $result = $this->getFinalCotizacionExcelv2($objPHPExcel, $data, $idContenedor);

            // Actualizar tabla de cotizaciones
            DB::table($this->table_contenedor_cotizacion)
                ->where('id', $result['id'])
                ->update([
                    'cotizacion_final_url' => $result['cotizacion_final_url'],
                    'volumen_final' => $result['volumen_final'],
                    'monto_final' => $result['monto_final'],
                    'tarifa_final' => $result['tarifa_final'],
                    'impuestos_final' => $result['impuestos_final'],
                    'logistica_final' => $result['logistica_final'],
                    'fob_final' => $result['fob_final'],
                    'peso_final' => $result['peso_final'],
                    'estado_cotizacion_final' => 'COTIZADO'
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Cotización individual generada correctamente',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error en generateIndividualCotizacion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar cotización individual: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa datos de Excel sin generar archivos
     */
    public function processExcelData(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls'
            ]);

            $data = $this->getMassiveExcelData($request->file('excel_file'));

            return response()->json([
                'success' => true,
                'message' => 'Datos de Excel procesados correctamente',
                'data' => $data,
                'total_clientes' => count($data)
            ]);
        } catch (\Exception $e) {
            Log::error('Error en processExcelData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar datos de Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica el directorio temporal y permisos
     */
    public function checkTempDirectory()
    {
        try {
            $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
            
            $info = [
                'temp_dir' => $tempDir,
                'exists' => file_exists($tempDir),
                'writable' => is_writable($tempDir),
                'readable' => is_readable($tempDir),
                'permissions' => file_exists($tempDir) ? substr(sprintf('%o', fileperms($tempDir)), -4) : 'N/A',
                'php_version' => PHP_VERSION,
                'zip_extension' => extension_loaded('zip') ? 'Sí' : 'No',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ];
            
            if (!file_exists($tempDir)) {
                $info['created'] = mkdir($tempDir, 0755, true);
                $info['created_permissions'] = file_exists($tempDir) ? substr(sprintf('%o', fileperms($tempDir)), -4) : 'N/A';
            }
            
            // Probar crear un archivo de prueba
            $testFile = $tempDir . DIRECTORY_SEPARATOR . 'test_' . time() . '.txt';
            $testResult = file_put_contents($testFile, 'test');
            if ($testResult !== false) {
                $info['test_file_created'] = true;
                $info['test_file_size'] = filesize($testFile);
                unlink($testFile);
            } else {
                $info['test_file_created'] = false;
            }
            
            return response()->json([
                'success' => true,
                'directory_info' => $info
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar directorio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add value_formatted to headers with currency values (CBMs and logística related)
     */
    private function addCurrencyFormatting(array $headers)
    {
        $keysToFormat = ['total_logistica', 'total_logistica_pagado', 'total_fob', 'total_impuestos','total_vendido_logistica_impuestos', 'total_pagado'];
        foreach ($headers as $k => $item) {
            if (is_array($item) && array_key_exists('value', $item) && in_array($k, $keysToFormat)) {
                $headers[$k]['value'] = $this->formatCurrency($item['value']);
            }
        }
        return $headers;
    }

    /**
     * Helper: format a number as currency (e.g., $1,234.56)
     */
    private function formatCurrency($value, $symbol = '$')
    {
        $num = is_numeric($value) ? (float)$value : 0.0;
        return $symbol . number_format($num, 2, '.', ',');
    }

    /**
     * Obtiene los headers de cotizaciones finales para un contenedor
     */
    public function getCotizacionFinalHeaders($idContenedor)
    {
        try {
            // Obtener ID del usuario autenticado
            $userId = auth()->user()->ID_Usuario ?? null;

            // Consulta principal con múltiples subconsultas
            $result = DB::table($this->table_contenedor_cotizacion_proveedores . ' as cccp')
                ->select([
                    // CBM Total China con condición de estado
                    DB::raw('COALESCE(SUM(IF(cc.estado_cotizador = "CONFIRMADO", cccp.cbm_total_china, 0)), 0) as cbm_total_china'),
                    
                    // CBM Total Perú (todos los CONFIRMADO)
                    DB::raw('(
                        SELECT COALESCE(SUM(volumen_final), 0)
                        FROM ' . $this->table_contenedor_cotizacion . '
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . '
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                    ) as cbm_total_peru'),
                    
                    // Subconsulta para total_logistica
                    DB::raw('(
                        SELECT COALESCE(SUM(logistica_final), 0) 
                        FROM ' . $this->table_contenedor_cotizacion . ' 
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion 
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . ' 
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                    ) as total_logistica'),
                    
                    // Subconsulta para total_impuestos
                    DB::raw('(
                        SELECT COALESCE(SUM(impuestos_final), 0)
                        FROM ' . $this->table_contenedor_cotizacion . '
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . '
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                    ) as total_impuestos'),
                    
                    // Suma de fob_final
                    DB::raw('(
                        SELECT COALESCE(SUM(fob_final), 0)
                        FROM ' . $this->table_contenedor_cotizacion . '
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . '
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                    ) as total_fob'),
                    
                    // Total vendido logistica + impuestos
                    DB::raw('(
                        SELECT COALESCE(SUM(logistica_final + impuestos_final), 0)
                        FROM ' . $this->table_contenedor_cotizacion . '
                        WHERE id IN (
                            SELECT DISTINCT id_cotizacion
                            FROM ' . $this->table_contenedor_cotizacion_proveedores . '
                            WHERE id_contenedor = ' . $idContenedor . '
                        )
                        AND estado_cotizador = "CONFIRMADO"
                    ) as total_vendido_logistica_impuestos'),
                    
                    // Total pagado logistica
                    DB::raw('(
                        SELECT COALESCE(SUM(monto), 0)
                        FROM ' . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . ' 
                        JOIN ' . $this->table_pagos_concept . ' ON ' . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . '.id_concept = ' . $this->table_pagos_concept . '.id
                        WHERE id_contenedor = ' . $idContenedor . '
                        AND ' . $this->table_pagos_concept . '.name = "LOGISTICA"
                    ) as total_logistica_pagado'),
                    
                    // Total pagado (logistica + impuestos)
                    DB::raw('(
                        SELECT COALESCE(SUM(monto), 0)
                        FROM ' . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . ' 
                        JOIN ' . $this->table_pagos_concept . ' ON ' . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . '.id_concept = ' . $this->table_pagos_concept . '.id
                        WHERE id_contenedor = ' . $idContenedor . '
                        AND (' . $this->table_pagos_concept . '.name = "LOGISTICA"
                        OR ' . $this->table_pagos_concept . '.name = "IMPUESTOS")
                    ) as total_pagado')
                ])
                ->join($this->table_contenedor_cotizacion . ' as cc', 'cccp.id_cotizacion', '=', 'cc.id')
                ->where('cccp.id_contenedor', $idContenedor)
                ->first();

            // Obtener bl_file_url y lista_empaque_file_url del contenedor
            $result2 = DB::table($this->table)
                ->select('bl_file_url', 'lista_embarque_url', 'carga')
                ->where('id', $idContenedor)
                ->first();

            // Si es el usuario 28791, obtener los CBM por usuario (vendido, pendiente, embarcado)
            if ($userId == 28791) {
                $dataHeaders = [
                    'cbm_total_peru' => [
                        "value" => $result->cbm_total_peru,
                        "label" => "",
                        "icon" => "https://upload.wikimedia.org/wikipedia/commons/c/cf/Flag_of_Peru.svg"
                    ],
                    'total_logistica' => [
                        "value" => $result->total_logistica,
                        "label" => "Logist.",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_logistica_pagado' => [
                        "value" => $result->total_logistica_pagado,
                        "label" => "Logist. Pagado",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_impuestos' => [
                        "value" => $result->total_impuestos,
                        "label" => "Impuestos",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_fob' => [
                        "value" => $result->total_fob,
                        "label" => "FOB",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                ];
                $dataHeaders = $this->addCurrencyFormatting($dataHeaders);
                return response()->json([
                    'success' => true,
                    'data' => $dataHeaders,
                    'carga' => $result2->carga ?? ''
                ]);
            }

            if ($result) {
                $dataHeaders = [
                    'cbm_total' => [
                        "value" => $result->cbm_total_peru,
                        "label" => "",
                        "icon" => "https://upload.wikimedia.org/wikipedia/commons/c/cf/Flag_of_Peru.svg"
                    ],
                    'total_logistica' => [
                        "value" => $result->total_logistica,
                        "label" => "Logist.",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_impuestos' => [
                        "value" => $result->total_impuestos,
                        "label" => "Impuestos",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_fob' => [
                        "value" => $result->total_fob,
                        "label" => "FOB",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_pagado' => [
                        "value" => $result->total_pagado,
                        "label" => "Pagado",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                    'total_vendido_logistica_impuestos' => [
                        "value" => $result->total_vendido_logistica_impuestos,
                        "label" => "Vendido",
                        "icon" => "cryptocurrency-color:soc"
                    ],
                ];
                $dataHeaders = $this->addCurrencyFormatting($dataHeaders);
                return response()->json([
                    'success' => true,
                    'data' => $dataHeaders,
                    'carga' => $result2->carga ?? ''
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'cbm_total' => ["value" => 0, "label" => "CBM Pendiente", "icon" => "fas fa-cube"],
                        'cbm_embarcado' => ["value" => 0, "label" => "CBM Embarcado", "icon" => "fas fa-ship"],
                        'total_logistica' => ["value" => 0, "label" => "Logistica", "icon" => "fas fa-dollar-sign"],
                        'qty_items' => ["value" => 0, "label" => "Items", "icon" => "bi:boxes"],
                        'cbm_total_peru' => ["value" => 0, "label" => "CBM Total Perú", "icon" => "https://upload.wikimedia.org/wikipedia/commons/c/cf/Flag_of_Peru.svg"],
                        'total_fob' => ["value" => 0, "label" => "FOB", "icon" => "fas fa-dollar-sign"]
                    ],
                    'carga' => ''
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en getCotizacionFinalHeaders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener headers de cotizaciones finales: ' . $e->getMessage()
            ], 500);
        }
    }
    public function deleteCotizacionFinalFile($idCotizacionFinal)
    {
        try {
            // Buscar la cotización por ID
            $cotizacion = Cotizacion::find($idCotizacionFinal);

            if (!$cotizacion) {
                Log::error('Error en deleteCotizacionFinalFile: Cotización no encontrada con ID: ' . $idCotizacionFinal);
                return false;
            }

            // Obtener la URL del archivo y eliminarlo si existe
            $cotizacionFinalUrl = $cotizacion->cotizacion_final_url;
            if ($cotizacionFinalUrl && file_exists($cotizacionFinalUrl)) {
                    unlink($cotizacionFinalUrl);
                }

            // Actualizar el campo a null en la base de datos
            $cotizacion->update(['cotizacion_final_url' => null]);
            $cotizacion->update(['estado_cotizacion_final' => 'PENDIENTE']);

            return response()->json([
                'success' => true,
                'message' => 'Cotización final eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en deleteCotizacionFinalFile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cotización final: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el archivo Excel de cotización final individual
     */
    public function downloadCotizacionFinalExcel($idCotizacion)
    {
        try {
            // Buscar la cotización por ID
            $cotizacion = Cotizacion::find($idCotizacion);

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            if (!$cotizacion->cotizacion_final_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró archivo de cotización final'
                ], 404);
            }

            // Obtener la ruta del archivo
            $fileUrl = $cotizacion->cotizacion_final_url;
            
            // Intentar diferentes ubicaciones
            $possiblePaths = [];
            
            // Nueva ubicación: storage/app/public/CargaConsolidada/cotizacionfinal/{idContenedor}
            $possiblePaths[] = storage_path('app/public/' . $fileUrl);
            
            // Ubicación legacy: public/assets/downloads
            if (strpos($fileUrl, 'http') === 0) {
                $pathParts = parse_url($fileUrl);
                $possiblePaths[] = storage_path('app/public' . $pathParts['path']);
                $possiblePaths[] = public_path($pathParts['path']);
            } else {
                $possiblePaths[] = public_path('assets/downloads/' . basename($fileUrl));
                $possiblePaths[] = public_path($fileUrl);
            }
            
            // Buscar el archivo en las ubicaciones posibles
            $filePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $filePath = $path;
                    break;
                }
            }

            // Verificar que el archivo existe
            if (!$filePath || !file_exists($filePath)) {
                Log::error('Archivo de cotización final no encontrado');
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado en el servidor'
                ], 404);
            }

            // Obtener el nombre del archivo
            $fileName = basename($filePath);
            
            // Retornar el archivo para descarga
            return response()->download($filePath, $fileName);

        } catch (\Exception $e) {
            Log::error('Error en downloadCotizacionFinalExcel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar cotización final: ' . $e->getMessage()
            ], 500);
        }
    }
   
    public function downloadCotizacionFinalPdf($idCotizacionFinal)
    {
        // Obtener la URL de cotización final y generar boleta
        try {
            $cotizacion = Cotizacion::find($idCotizacionFinal);
            
            if (!$cotizacion || !$cotizacion->cotizacion_final_url) {
                Log::error('Error en downloadBoleta: Cotización no encontrada o sin archivo final con ID: ' . $idCotizacionFinal);
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada o sin archivo final'
                ], 404);
            }

            $cotizacionFinalUrl = $cotizacion->cotizacion_final_url;
            
            // Intentar diferentes ubicaciones
            $possiblePaths = [];
            
            // Nueva ubicación: storage/app/public/CargaConsolidada/cotizacionfinal/{idContenedor}
            $possiblePaths[] = storage_path('app/public/' . $cotizacionFinalUrl);
            
            // Ubicación legacy
            if (strpos($cotizacionFinalUrl, 'http') === 0) {
                $fileUrl = str_replace(' ', '%20', $cotizacionFinalUrl);
                $possiblePaths[] = $fileUrl; // URL completa
            } else {
                $possiblePaths[] = public_path('assets/downloads/' . basename($cotizacionFinalUrl));
                $possiblePaths[] = public_path($cotizacionFinalUrl);
            }
            
            // Buscar el archivo en las ubicaciones posibles
            $fileContent = false;
            
            foreach ($possiblePaths as $path) {
                if (strpos($path, 'http') === 0) {
                    // Es una URL, usar file_get_contents
                    $fileContent = @file_get_contents($path);
                } else if (file_exists($path)) {
                    // Es un archivo local
                    $fileContent = file_get_contents($path);
                }
                
                if ($fileContent !== false) {
                    break;
                }
            }
            
            if ($fileContent === false) {
                Log::error('No se pudo leer el archivo de cotización final para PDF');
                throw new \Exception("No se pudo leer el archivo Excel desde ninguna ubicación.");
            }
            
            $tempFile = tempnam(sys_get_temp_dir(), 'cotizacion_') . '.xlsx';
            file_put_contents($tempFile, $fileContent);

            // Cargar Excel usando PhpSpreadsheet
            $spreadsheet = IOFactory::load($tempFile);
            
            // Generar y retornar el PDF
            $pdfResponse = $this->generateBoleta($spreadsheet);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            return $pdfResponse;
            
        } catch (\Exception $e) {
            Log::error('Error en downloadBoleta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar boleta: ' . $e->getMessage()
            ], 500);
        }
    }
    private function generateBoleta($spreadsheet)
    {
        try {
            $spreadsheet->setActiveSheetIndex(0);
            $activeSheet = $spreadsheet->getActiveSheet();
            $antidumping = $activeSheet->getCell('B23')->getValue();
            $data = [
                "name" => $activeSheet->getCell('C8')->getValue(),
                "lastname" => $activeSheet->getCell('C9')->getValue(),
                "ID" => $activeSheet->getCell('C10')->getValue(),
                "phone" => $activeSheet->getCell('C11')->getValue(),
                "date" => date('d/m/Y'),
                "tipocliente" => $activeSheet->getCell('F11')->getValue(),
                "peso" => $activeSheet->getCell('J9')->getCalculatedValue(),
                "qtysuppliers" => $activeSheet->getCell('J10')->getValue(),
                "cbm" => $activeSheet->getCell('J11')->getCalculatedValue(),
                "valorcarga" => round($activeSheet->getCell('K14')->getCalculatedValue(), 2),
                "fleteseguro" => round($activeSheet->getCell('K15')->getCalculatedValue(), 2),
                "valorcif" => round($activeSheet->getCell('K16')->getCalculatedValue(), 2),
                "advalorempercent" => intval($activeSheet->getCell('J20')->getCalculatedValue() * 100),
                "advalorem" => round($activeSheet->getCell('K20')->getCalculatedValue(), 2),
                "antidumping" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K23')->getCalculatedValue(), 2) : "",

                "igv" => round($activeSheet->getCell('K21')->getCalculatedValue(), 2),
                "ipm" => round($activeSheet->getCell('K22')->getCalculatedValue(), 2),
                "subtotal" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K24')->getCalculatedValue(), 2) : round($activeSheet->getCell('K23')->getCalculatedValue(), 2),
                "percepcion" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K26')->getCalculatedValue(), 2) : round($activeSheet->getCell('K25')->getCalculatedValue(), 2),
                "total" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K27')->getCalculatedValue(), 2) : round($activeSheet->getCell('K26')->getCalculatedValue(), 2),
                "valorcargaproveedor" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K30')->getCalculatedValue(), 2) : round($activeSheet->getCell('K29')->getCalculatedValue(), 2),
                "servicioimportacion" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K31')->getCalculatedValue(), 2) : round($activeSheet->getCell('K30')->getCalculatedValue(), 2),
                "impuestos" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K32')->getCalculatedValue(), 2) : round($activeSheet->getCell('K31')->getCalculatedValue(), 2),
                "montototal" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K33')->getCalculatedValue(), 2) : round($activeSheet->getCell('K32')->getCalculatedValue(), 2),
            ];
            $i = $antidumping == "ANTIDUMPING" ? 37 : 36;
            $items = [];
            while ($activeSheet->getCell('B' . $i)->getValue() != 'TOTAL') {
                //add item to items array
                $item = [
                    "index" => $activeSheet->getCell('B' . $i)->getCalculatedValue(),
                    "name" => $activeSheet->getCell('C' . $i)->getCalculatedValue(),
                    "qty" => $activeSheet->getCell('F' . $i)->getCalculatedValue(),
                    "costounit" => number_format(round((float)$activeSheet->getCell('G' . $i)->getCalculatedValue(), 2), 2, '.', ','),
                    "preciounit" => number_format(round((float)$activeSheet->getCell('I' . $i)->getCalculatedValue(), 2), 2, '.', ','),
                    "total" => round((float)$activeSheet->getCell('J' . $i)->getCalculatedValue(), 2),
                    "preciounitpen" => number_format(round((float)$activeSheet->getCell('K' . $i)->getCalculatedValue(), 2), 2, '.', ','),
                ];
                $items[] = $item;
                $i++;
            }
            $itemsCount = count($items);
            $data["br"] = $itemsCount - 18 < 0 ? str_repeat("<br>", 18 - $itemsCount) : "";
            $data['items'] = $items;
            $logoContent = file_get_contents(public_path('assets/images/probusiness.png'));
            $logoData = base64_encode($logoContent);
            $data["logo"] = 'data:image/png;base64,' . $logoData;
            $htmlFilePath = public_path('assets/templates/PLANTILLA_COTIZACION_FINAL.html');
            $htmlContent = file_get_contents($htmlFilePath);
            $pagosContent = file_get_contents(public_path('assets/images/pagos-full.jpg'));
            $pagosData = base64_encode($pagosContent);
            $data["pagos"] = 'data:image/png;base64,' . $pagosData;
            //replace {{name}} with data['name']
            foreach ($data as $key => $value) {
                //if value is a number parse to 2 decimals with comma as unit separator and dot as decimal separator
                if (is_numeric($value)) {
                    if ($value == 0) {
                        $value = '-';
                    }
                    if ($key != "ID" && $key != "phone" && $key != "qtysuppliers" && $key != "advalorempercent") {
                        $value = number_format((float)$value, 2, '.', ',');
                    }
                }
                if ($key == "antidumping" && $antidumping == "ANTIDUMPING") {
                    $antidumpingHtml = '<tr style="background:#FFFF33">
                    <td style="border-top:none!important;border-bottom:none!important" colspan="3">ANTIDUMPING</td>
                    <td style="border-top:none!important;border-bottom:none!important" ></td>
                    <td style="border-top:none!important;border-bottom:none!important" >$' . number_format((float)$data['antidumping'], 2, '.', ',') . '</td>
                    <td style="border-top:none!important;border-bottom:none!important" >USD</td>
                    </tr>';
                    $htmlContent = str_replace('{{antidumping}}', $antidumpingHtml, $htmlContent);
                    //search items with class ipm and set border none
                }
                if ($key == "items") {
                    $itemsHtml = "";
                    $total = 0;
                    $cantidad = 0;
                    foreach ($value as $item) {
                        $total += $item['total'];
                        $cantidad += $item['qty'];
                        $itemsHtml .= '<tr>
                        <td colspan="1">' . $item['index'] . '</td>
                        <td colspan="5">' . $item['name'] . '</td>
                        <td colspan="1">' . $item['qty'] . '</td>
                        <td colspan="2">$ ' . $item['costounit'] . '</td>
                        <td colspan="1">$ ' . $item['preciounit'] . '</td>
                        <td colspan="1">$ ' . number_format((float)$item['total'], 2, '.', ',') . '</td>
                        <td colspan="1">S/. ' . $item['preciounitpen'] . '</td>
                    </tr>';
                    }
                    $itemsHtml .= '<tr>
                    <td colspan="6" >TOTAL</td>
                    <td >' . $cantidad . '</td>
                    <td colspan="2" style="border:none!important"></td>
                    <td style="border:none!important"></td>
                    <td >$ ' . number_format((float)$total, 2, '.', ',') . '</td>
                    <td style="border:none!important"></td>

                </tr>';
                    $htmlContent = str_replace('{{' . $key . '}}', $itemsHtml, $htmlContent);
                } else {
                    $htmlContent = str_replace('{{' . $key . '}}', $value, $htmlContent);
                }
            }
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);

            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Obtener el contenido del PDF como string
            $pdfContent = $dompdf->output();
            
            // Retornar el PDF como respuesta para descarga
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="Cotizacion.pdf"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            Log::error("Error en la fórmula de la celda: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Excepción descargarBoleta: ' . $e->getMessage());
            throw $e;
        }
    }
    private function generateBoletaForSend($spreadsheet)
    {
        try {
            $spreadsheet->setActiveSheetIndex(0);
            $activeSheet = $spreadsheet->getActiveSheet();
            $antidumping = $activeSheet->getCell('B23')->getValue();
            $data = [
                "name" => $activeSheet->getCell('C8')->getValue(),
                "lastname" => $activeSheet->getCell('C9')->getValue(),
                "ID" => $activeSheet->getCell('C10')->getValue(),
                "phone" => $activeSheet->getCell('C11')->getValue(),
                "date" => date('d/m/Y'),
                "tipocliente" => $activeSheet->getCell('F11')->getValue(),
                "peso" => $activeSheet->getCell('J9')->getCalculatedValue(),
                "qtysuppliers" => $activeSheet->getCell('J10')->getValue(),
                "cbm" => $activeSheet->getCell('J11')->getCalculatedValue(),
                "valorcarga" => round($activeSheet->getCell('K14')->getCalculatedValue(), 2),
                "fleteseguro" => round($activeSheet->getCell('K15')->getCalculatedValue(), 2),
                "valorcif" => round($activeSheet->getCell('K16')->getCalculatedValue(), 2),
                "advalorempercent" => intval($activeSheet->getCell('J20')->getCalculatedValue() * 100),
                "advalorem" => round($activeSheet->getCell('K20')->getCalculatedValue(), 2),
                "antidumping" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K23')->getCalculatedValue(), 2) : "",

                "igv" => round($activeSheet->getCell('K21')->getCalculatedValue(), 2),
                "ipm" => round($activeSheet->getCell('K22')->getCalculatedValue(), 2),
                "subtotal" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K24')->getCalculatedValue(), 2) : round($activeSheet->getCell('K23')->getCalculatedValue(), 2),
                "percepcion" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K26')->getCalculatedValue(), 2) : round($activeSheet->getCell('K25')->getCalculatedValue(), 2),
                "total" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K27')->getCalculatedValue(), 2) : round($activeSheet->getCell('K26')->getCalculatedValue(), 2),
                "valorcargaproveedor" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K30')->getCalculatedValue(), 2) : round($activeSheet->getCell('K29')->getCalculatedValue(), 2),
                "servicioimportacion" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K31')->getCalculatedValue(), 2) : round($activeSheet->getCell('K30')->getCalculatedValue(), 2),
                "impuestos" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K32')->getCalculatedValue(), 2) : round($activeSheet->getCell('K31')->getCalculatedValue(), 2),
                "montototal" => $antidumping == "ANTIDUMPING" ? round($activeSheet->getCell('K33')->getCalculatedValue(), 2) : round($activeSheet->getCell('K32')->getCalculatedValue(), 2),
            ];
            $i = $antidumping == "ANTIDUMPING" ? 37 : 36;
            $items = [];
            while ($activeSheet->getCell('B' . $i)->getValue() != 'TOTAL') {
                //add item to items array
                $item = [
                    "index" => $activeSheet->getCell('B' . $i)->getCalculatedValue(),
                    "name" => $activeSheet->getCell('C' . $i)->getCalculatedValue(),
                    "qty" => $activeSheet->getCell('F' . $i)->getCalculatedValue(),
                    "costounit" => number_format(round((float)$activeSheet->getCell('G' . $i)->getCalculatedValue(), 2), 2, '.', ','),
                    "preciounit" => number_format(round((float)$activeSheet->getCell('I' . $i)->getCalculatedValue(), 2), 2, '.', ','),
                    "total" => round((float)$activeSheet->getCell('J' . $i)->getCalculatedValue(), 2),
                    "preciounitpen" => number_format(round((float)$activeSheet->getCell('K' . $i)->getCalculatedValue(), 2), 2, '.', ','),
                ];
                $items[] = $item;
                $i++;
            }
            $itemsCount = count($items);
            $data["br"] = $itemsCount - 18 < 0 ? str_repeat("<br>", 18 - $itemsCount) : "";
            $data['items'] = $items;
            $logoContent = file_get_contents(public_path('assets/images/probusiness.png'));
            $logoData = base64_encode($logoContent);
            $data["logo"] = 'data:image/png;base64,' . $logoData;
            $htmlFilePath = public_path('assets/templates/PLANTILLA_COTIZACION_FINAL.html');
            $htmlContent = file_get_contents($htmlFilePath);
            $pagosContent = file_get_contents(public_path('assets/images/pagos-full.jpg'));
            $pagosData = base64_encode($pagosContent);
            $data["pagos"] = 'data:image/png;base64,' . $pagosData;
            //replace {{name}} with data['name']
            foreach ($data as $key => $value) {
                //if value is a number parse to 2 decimals with comma as unit separator and dot as decimal separator
                if (is_numeric($value)) {
                    if ($value == 0) {
                        $value = '-';
                    }
                    if ($key != "ID" && $key != "phone" && $key != "qtysuppliers" && $key != "advalorempercent") {
                        $value = number_format((float)$value, 2, '.', ',');
                    }
                }
                if ($key == "antidumping" && $antidumping == "ANTIDUMPING") {
                    $antidumpingHtml = '<tr style="background:#FFFF33">
                    <td style="border-top:none!important;border-bottom:none!important" colspan="3">ANTIDUMPING</td>
                    <td style="border-top:none!important;border-bottom:none!important" ></td>
                    <td style="border-top:none!important;border-bottom:none!important" >$' . number_format((float)$data['antidumping'], 2, '.', ',') . '</td>
                    <td style="border-top:none!important;border-bottom:none!important" >USD</td>
                    </tr>';
                    $htmlContent = str_replace('{{antidumping}}', $antidumpingHtml, $htmlContent);
                    //search items with class ipm and set border none
                }
                if ($key == "items") {
                    $itemsHtml = "";
                    $total = 0;
                    $cantidad = 0;
                    foreach ($value as $item) {
                        $total += $item['total'];
                        $cantidad += $item['qty'];
                        $itemsHtml .= '<tr>
                        <td colspan="1">' . $item['index'] . '</td>
                        <td colspan="5">' . $item['name'] . '</td>
                        <td colspan="1">' . $item['qty'] . '</td>
                        <td colspan="2">$ ' . $item['costounit'] . '</td>
                        <td colspan="1">$ ' . $item['preciounit'] . '</td>
                        <td colspan="1">$ ' . number_format((float)$item['total'], 2, '.', ',') . '</td>
                        <td colspan="1">S/. ' . $item['preciounitpen'] . '</td>
                    </tr>';
                    }
                    $itemsHtml .= '<tr>
                    <td colspan="6" >TOTAL</td>
                    <td >' . $cantidad . '</td>
                    <td colspan="2" style="border:none!important"></td>
                    <td style="border:none!important"></td>
                    <td >$ ' . number_format((float)$total, 2, '.', ',') . '</td>
                    <td style="border:none!important"></td>

                </tr>';
                    $htmlContent = str_replace('{{' . $key . '}}', $itemsHtml, $htmlContent);
                } else {
                    $htmlContent = str_replace('{{' . $key . '}}', $value, $htmlContent);
                }
            }
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);

            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Obtener el contenido del PDF como string
            $pdfContent = $dompdf->output();
            //save pdf in temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'cotizacion_') . '.pdf';
            file_put_contents($tempFile, $pdfContent);
            return $tempFile;
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            Log::error("Error en la fórmula de la celda: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Excepción generateBoletaForSend: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Procesa una sola fila del Excel
     */
    private function processSingleRow($sheet, $newSheet, $newRow, $currentRow, $clientName, $clientType, $dataSystem)
    {
        // Insertar nueva fila
        if ($newRow > 2) {
            $newSheet->insertNewRowBefore($newRow);
        }

        // Combinar celdas de descripción
        $newSheet->mergeCells('F' . $newRow . ':M' . $newRow);

        // Copiar datos
        $rowData = [
            'A' => $clientName,
            'B' => $clientType,
            'E' => $sheet->getCell('B' . $currentRow)->getValue(),
            'F' => $sheet->getCell('E' . $currentRow)->getValue(),
            'N' => $sheet->getCell('M' . $currentRow)->getValue(),
            'O' => $sheet->getCell('O' . $currentRow)->getValue(),
            'P' => $sheet->getCell('S' . $currentRow)->getValue(),
            'Q' => 0,
            'R' => $sheet->getCell('R' . $currentRow)->getValue(),
            'S' => 0.035,
            'U' => $sheet->getCell('T' . $currentRow)->getValue()
        ];
        
        Log::info('Row data: ' . json_encode($rowData));
        
        foreach ($rowData as $column => $value) {
            $newSheet->setCellValue($column . $newRow, $value);
        }

        // Buscar datos del sistema para el cliente
        foreach ($dataSystem as $data) {
            if ($this->isNameMatch(trim($clientName), trim($data->nombre))) {
                $newSheet->setCellValue('C' . $newRow, $data->documento);
                $newSheet->setCellValue('D' . $newRow, $data->telefono);
                $newSheet->setCellValue('T' . $newRow, $data->peso);
                break;
            }
        }

        // Aplicar estilos
        $this->applyRowStyles($newSheet, $newRow);
    }

    /**
     * Configura los productos en la hoja principal (filas 36-39)
     */
    private function configureProductsInMainSheet($objPHPExcel, $data, $antidumpingSum)
    {
        $sheet1 = $objPHPExcel->getSheet(0); // Hoja principal
        $productsCount = count($data['cliente']['productos']);
        
        // Colores
        $greenColor = "009999";
        $whiteColor = "FFFFFF";
        $borders = [
            'borders' => [
                'allborders' => [
                    'style' => Border::BORDER_THIN,
                ],
            ],
        ];

        Log::info('Configurando productos en hoja principal, cantidad: ' . $productsCount);

        // Configurar CBM en J11 (referencia a la hoja 2)
        $CBMTotal = Coordinate::stringFromColumnIndex(count($data['cliente']['productos']) + 2) . "7";
        $sheet1->setCellValue('J11', "='2'!" . $CBMTotal);
        Log::info('CBM configurado en J11: ' . "='2'!" . $CBMTotal);

        // Limpiar filas 36-39 primero
        for ($row = 36; $row <= 39; $row++) {
            for ($col = 1; $col <= 12; $col++) {
                $cell = Coordinate::stringFromColumnIndex($col) . $row;
                $sheet1->setCellValue($cell, ''); // Establecer el valor de la celda como vacío
                $sheet1->getStyle($cell)->applyFromArray([]); // Eliminar cualquier estilo aplicado a la celda
            }
        }

        $lastRow = 0;
        $InitialColumn = 'C';

        // Remover bordes si hay menos de 3 productos
        if ($productsCount < 3) {
            $substract = 3 - $productsCount;
            for ($i = 0; $i < $substract; $i++) {
                $row = 36 + $i + $productsCount;
                $sheet1->getStyle('B' . $row . ':L' . $row)->applyFromArray([]);
            }
        }

        // Configurar cada producto
        for ($index = 0; $index < $productsCount; $index++) {
            $row = 36 + $index;
            
            // Insertar nueva fila si hay más de 7 productos
            if ($index >= 7 && $index != $productsCount) {
                $sheet1->insertNewRowBefore($row, 1);
            }

            Log::info('Configurando producto ' . ($index + 1) . ' en fila ' . $row);

            // Configurar valores
            $sheet1->setCellValue('B' . $row, $index + 1);
            $sheet1->getStyle('B' . $row)->getFont()->setBold(false);
            
            $sheet1->setCellValue('C' . $row, $data['cliente']['productos'][$index]["nombre"]);
            $sheet1->setCellValue('F' . $row, "='2'!" . $InitialColumn . '10'); // Cantidad
            $sheet1->getStyle('F' . $row)->getFont()->setBold(false);
            $sheet1->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet1->setCellValue('G' . $row, "='2'!" . $InitialColumn . '8'); // Precio unitario
            $sheet1->getStyle('G' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $sheet1->setCellValue('I' . $row, "='2'!" . $InitialColumn . '46'); // Costo unitario
            $sheet1->getStyle('I' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $sheet1->setCellValue('J' . $row, "='2'!" . $InitialColumn . '44'); // Costo total
            $sheet1->getStyle('J' . $row)->getFont()->setBold(false);
            $sheet1->getStyle('J' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $sheet1->setCellValue('K' . $row, "='2'!" . $InitialColumn . '47'); // Costo en soles

            // Combinar celdas
            $sheet1->mergeCells('C' . $row . ':E' . $row);
            $sheet1->mergeCells('G' . $row . ':H' . $row);
            $sheet1->mergeCells('K' . $row . ':L' . $row);

            // Aplicar estilos
            $style = $sheet1->getStyle('K' . $row);
            $style->getFill()->setFillType(Fill::FILL_SOLID);
            $style->getFill()->getStartColor()->setARGB($greenColor);
            $style->getFont()->getColor()->setARGB(Color::COLOR_WHITE);

            // Aplicar bordes
            $sheet1->getStyle('B' . $row . ':L' . $row)->applyFromArray($borders);

            // Aplicar estilos a todas las columnas
            $columnsToApply = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
            foreach ($columnsToApply as $column) {
                $sheet1->getStyle($column . $row)->getFont()->setName('Calibri');
                $sheet1->getStyle($column . $row)->getFont()->setSize(11);
                $sheet1->getStyle($column . $row)->getFont()->setBold(true);
                $sheet1->getStyle($column . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                if ($column == 'K') {
                    $sheet1->getStyle($column . $row)->getNumberFormat()->setFormatCode('"S/." #,##0.00_-');
                }
            }

            $InitialColumn = $this->incrementColumn($InitialColumn);
            $lastRow = $row;
        }

        // Manejar filas no usadas
        $notUsedDefaultRows = 3 - $productsCount;
        if ($notUsedDefaultRows >= 0) {
            for ($i = 0; $i <= $notUsedDefaultRows; $i++) {
                $row = 36 + $productsCount + $i;
                $sheet1->getStyle('B' . $row . ':L' . $row)->applyFromArray([
                    'borders' => [
                        'allborders' => [
                            'style' => Border::BORDER_NONE,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
                
                // Establecer fondo blanco en columna K
                $style = $sheet1->getStyle('K' . $row);
                $style->getFill()->setFillType(Fill::FILL_SOLID);
                $style->getFill()->getStartColor()->setARGB($whiteColor);
            }
        }

        // Configurar fila de totales
        $lastRow++;
        
        if ($productsCount >= 7) {
            $sheet1->unmergeCells('B' . $lastRow . ':L' . $lastRow);
            $sheet1->mergeCells('B' . $lastRow . ':E' . $lastRow);
        }
        
        if ($notUsedDefaultRows >= 0) {
            $sheet1->mergeCells('C' . $lastRow . ':E' . $lastRow);
            $sheet1->unmergeCells('C' . $lastRow . ':E' . $lastRow);
            $sheet1->mergeCells('B' . $lastRow . ':E' . $lastRow);
        }

        $sheet1->setCellValue('B' . $lastRow, "TOTAL");
        $sheet1->getStyle('B' . $lastRow)->getFont()->setBold(true);
        $sheet1->getStyle('B' . $lastRow . ':E' . $lastRow)->applyFromArray($borders);
        $sheet1->getStyle('B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet1->setCellValue('F' . $lastRow, "=SUM(F36:F" . ($lastRow - 1) . ")");
        $sheet1->getStyle('F' . $lastRow)->applyFromArray($borders);
        $sheet1->getStyle('F' . $lastRow)->getFont()->setBold(true);
        $sheet1->getStyle('F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet1->setCellValue('J' . $lastRow, "=SUM(J36:J" . ($lastRow - 1) . ")");
        $sheet1->getStyle('J' . $lastRow)->getFont()->setBold(true);
        $sheet1->getStyle('J' . $lastRow)->applyFromArray($borders);
        $sheet1->getStyle('J' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        $sheet1->getStyle('J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet1->getStyle('B' . $lastRow . ':L' . $lastRow)->getFont()->setSize(11);

        // Manejar antidumping si existe
        if ($antidumpingSum > 0) {
            $rowToCheck = 23;
            $sheet1->insertNewRowBefore($rowToCheck, 1);
            $newRowIndex = $rowToCheck;
            
            $sheet1->setCellValue('B' . $newRowIndex, "ANTIDUMPING");
            $sheet1->setCellValue('K' . $newRowIndex, $antidumpingSum);
            
            // Establecer fondo amarillo
            $yellowColor = 'FFFF33';
            $style = $sheet1->getStyle('B' . $newRowIndex . ':L' . $newRowIndex);
            $style->getFill()->setFillType(Fill::FILL_SOLID);
            $style->getFill()->getStartColor()->setARGB($yellowColor);
            
            $sheet1->setCellValue('K24', "=SUM(K20:K23)");
        }

        Log::info('Productos configurados en hoja principal exitosamente');
    }

    /**
     * Actualiza los campos calculados (FOB, Logística, Impuestos) en el Excel
     */
    private function updateCalculatedFieldsInExcel($objPHPExcel, $fob, $logistica, $impuestos, $montoFinal, $antidumpingSum)
    {
        $sheet1 = $objPHPExcel->getSheet(0); // Hoja principal
        
        Log::info('Actualizando campos calculados en Excel');
        Log::info('FOB: ' . $fob . ', Logística: ' . $logistica . ', Impuestos: ' . $impuestos . ', Monto Final: ' . $montoFinal);

        try {
            // Forzar recálculo de fórmulas
            $objPHPExcel->getActiveSheet()->getParent()->getCalculationEngine()->flushInstance();
            $objPHPExcel->setActiveSheetIndex(0);
            $objPHPExcel->getActiveSheet()->calculateColumnWidths();

            if ($antidumpingSum > 0) {
                // CON antidumping: usar valores directos
                Log::info('Actualizando valores CON antidumping');
                
                // Actualizar FOB (K30)
                if ($fob > 0) {
                    $sheet1->setCellValue('K30', $fob);
                    Log::info('K30 (FOB) actualizado: ' . $fob);
                }
                
                // Actualizar Logística (K31) 
                if ($logistica > 0) {
                    $sheet1->setCellValue('K31', $logistica);
                    Log::info('K31 (Logística) actualizado: ' . $logistica);
                }
                
                // Actualizar Impuestos totales (K32)
                if ($impuestos > 0) {
                    $sheet1->setCellValue('K32', $impuestos);
                    Log::info('K32 (Impuestos) actualizado: ' . $impuestos);
                }
                
            } else {
                // SIN antidumping: usar valores directos
                Log::info('Actualizando valores SIN antidumping');
                
                // Actualizar FOB (K29)
                if ($fob > 0) {
                    $sheet1->setCellValue('K29', $fob);
                    Log::info('K29 (FOB) actualizado: ' . $fob);
                }
                
                // Actualizar Logística (K30)
                if ($logistica > 0) {
                    $sheet1->setCellValue('K30', $logistica);
                    Log::info('K30 (Logística) actualizado: ' . $logistica);
                }
                
                // Actualizar Impuestos totales (K31)
                if ($impuestos > 0) {
                    $sheet1->setCellValue('K31', $impuestos);
                    Log::info('K31 (Impuestos) actualizado: ' . $impuestos);
                }
            }

            // Actualizar mensaje de WhatsApp con valores correctos
            $clientName = $sheet1->getCell('C8')->getValue();
            $whatsappMessage = "Hola " . $clientName . " 😁 un gusto saludarte!
A continuación te envío la cotización final de tu importación📋📦.
🙋‍♂️ PAGO PENDIENTE :
☑️Costo CBM: $" . number_format($logistica, 2) . "
☑️Impuestos: $" . number_format($impuestos, 2) . "
☑️ Total: $" . number_format($logistica + $impuestos, 2) . "
Pronto le aviso nuevos avances, que tengan buen día🚢
Último día de pago:";
            
            $sheet1->setCellValue('N20', $whatsappMessage);
            Log::info('Mensaje WhatsApp actualizado');

            // Forzar recálculo final
            $objPHPExcel->getActiveSheet()->getParent()->getCalculationEngine()->flushInstance();
            $objPHPExcel->getActiveSheet()->calculateColumnWidths();

            Log::info('Campos calculados actualizados exitosamente en Excel');

        } catch (\Exception $e) {
            Log::error('Error actualizando campos en Excel: ' . $e->getMessage());
        }
    }

    /**
     * MIGRACIÓN COMPLETA del método getFinalCotizacionExcelv2 de CodeIgniter
     * Este método reemplaza la implementación actual con toda la lógica de CodeIgniter
     * IMPORTANTE: Recibe $objPHPExcel como primer parámetro (igual que el original)
     */
    public function getFinalCotizacionExcelv2($objPHPExcel, $data, $idContenedor)
    {
        try {
            // GOD IMPLEMENTATION
            $newSheet = $objPHPExcel->createSheet();
            $newSheet->setTitle('3');
            
            /**Base Styles */
            $grayColor = 'F8F9F9';
            $blueColor = '1F618D';
            $yellowColor = 'FFFF33';
            $greenColor = "009999";
            $whiteColor = "FFFFFF";
            $borders = array(
                'borders' => array(
                    'allborders' => array(
                        'style' => Border::BORDER_THIN,
                    ),
                ),
            );
            
            /**Apply Tributes Calc Zones Rows Title */
            $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B3:G3');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B3', 'Calculo de Tributos');
            $style = $objPHPExcel->getActiveSheet()->getStyle('B3');
            $style->getFill()->setFillType(Fill::FILL_SOLID);
            $style->getFill()->getStartColor()->setARGB($grayColor);
            $objPHPExcel->getActiveSheet()->getStyle('B3:G3')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B5', 'Nombres');
            $objPHPExcel->getActiveSheet()->getStyle('B5')->getFill()->setFillType(Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle('B5')->getFill()->getStartColor()->setARGB($blueColor);
            $objPHPExcel->getActiveSheet()->getStyle('B5')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
            $objPHPExcel->getActiveSheet()->getStyle('B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B6', 'Peso');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B7', "Valor CBM");
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B8', 'Valor Unitario');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B9', 'Valoracion');
            $objPHPExcel->getActiveSheet()->getStyle('B9')->getFill()->setFillType(Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle('B9')->getFill()->getStartColor()->setARGB($yellowColor);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B10', 'Cantidad');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B11', 'Valor FOB');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B12', 'Valor FOB Valoracion');
            $objPHPExcel->getActiveSheet()->getStyle('B12')->getFill()->setFillType(Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle('B12')->getFill()->getStartColor()->setARGB($yellowColor);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B13', 'Distribucion %');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B14', 'Flete');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B15', 'Valor CFR');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B16', 'CFR Valorizado');
            $objPHPExcel->getActiveSheet()->getStyle('B16')->getFill()->setFillType(Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle('B16')->getFill()->getStartColor()->setARGB($yellowColor);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B17', 'Seguro');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B18', 'Valor CIF');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B19', 'CIF Valorizado');
            $objPHPExcel->getActiveSheet()->getStyle('B19')->getFill()->setFillType(Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle('B19')->getFill()->getStartColor()->setARGB($yellowColor);
            $objPHPExcel->getActiveSheet()->getColumnDimension("B")->setAutoSize(true);
            
            $InitialColumn = 'C';
            $totalRows = 0;
            $cbmTotal = 0;
            $pesoTotal = 0;
            $logistica = 0;
            $impuestos = 0;
            $fob = 0;
            $tarifa = $data['cliente']['tarifa'];
            $sheet1 = $objPHPExcel->getSheet(0);
            
            // first iterate for tributes zone, set values and apply styles to cells
            foreach ($data['cliente']['productos'] as $producto) {
                // Validar y convertir valores a numéricos
                $precioUnitario = is_numeric($producto["precio_unitario"] ?? 0) ? (float)$producto["precio_unitario"] : 0;
                $valoracion = is_numeric($producto["valoracion"] ?? 0) ? (float)$producto["valoracion"] : 0;
                $cantidad = is_numeric($producto["cantidad"] ?? 0) ? (float)$producto["cantidad"] : 0;
                $cbm = is_numeric($producto['cbm'] ?? 0) ? (float)$producto['cbm'] : 0;
                
                $objPHPExcel->getActiveSheet()->getColumnDimension($InitialColumn)->setAutoSize(true);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '5', $producto["nombre"]);
                
                // APLY BACKGROUND COLOR BLUE AND LETTERS WHITE
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '5')->getFill()->setFillType(Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '5')->getFill()->getStartColor()->setARGB($blueColor);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '5')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '6', 0);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '7', 0);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '8', $precioUnitario);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '9', $valoracion);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '10', $cantidad);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '11', "=" . $InitialColumn . "8*" . $InitialColumn . "10");
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '12', "=" . $InitialColumn . "10*" . $InitialColumn . "9");
                
                // set format currency with dollar symbol
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '8')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '11')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '12')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $InitialColumn = $this->incrementColumn($InitialColumn);
                $totalRows++;
                $cbmTotal += $cbm;
            }
            
            $pesoTotal = is_numeric($data['cliente']['productos'][0]['peso'] ?? 0) ? (float)$data['cliente']['productos'][0]['peso'] : 0;
            $objPHPExcel->getActiveSheet()->getColumnDimension($InitialColumn)->setAutoSize(true);
            
            $tipoCliente = trim($data['cliente']["tipo_cliente"] ?? '');
            $volumen = is_numeric($data['cliente']['volumen'] ?? 0) ? (float)$data['cliente']['volumen'] : 0;
            Log::info('Tipo Cliente: ' . $tipoCliente);
            
            $tipoClienteCell = $this->incrementColumn($InitialColumn, 3) . '6';
            $tipoClienteCellValue = $this->incrementColumn($InitialColumn, 3) . '7';
            $tarifaCell = $this->incrementColumn($InitialColumn, 4) . '6';
            $tarifaCellValue = $this->incrementColumn($InitialColumn, 4) . '7';
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($tipoClienteCell, "Tipo Cliente");
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($tarifaCell, "Tarifa");
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($tipoClienteCellValue, $tipoCliente);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($tarifaCellValue, $tarifa);
            
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCell)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCell)->getAlignment()->setShrinkToFit(true);
            
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCell)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCell)->getAlignment()->setShrinkToFit(true);
            
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCellValue)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCellValue)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCellValue)->getAlignment()->setShrinkToFit(true);
            
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCellValue)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCellValue)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCellValue)->getAlignment()->setShrinkToFit(true);
            
            // apply borders to cells
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCell)->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCell)->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle($tipoClienteCellValue)->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle($tarifaCellValue)->applyFromArray($borders);
            
            // create remaining zones and apply styles
            $InitialColumnLetter = $this->incrementColumn($InitialColumn, -1);
            $LastColumnLetter = $InitialColumn;
            
            // Asegurarse de que la hoja 2 (tributos) esté activa antes de aplicar los bordes
            $objPHPExcel->setActiveSheetIndex(2);
            
            $objPHPExcel->getActiveSheet()->getStyle('B5:' . $InitialColumn . '19')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('B28:' . $InitialColumn . '32')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('B40:' . $InitialColumn . '40')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('B43:' . $InitialColumn . '47')->applyFromArray($borders);
            
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '5')->getFill()->setFillType(Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '5')->getFill()->getStartColor()->setARGB($blueColor);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '5', "Total");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '5')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '6', $pesoTotal > 1000 ? round($pesoTotal / 1000, 2) : $pesoTotal);
            if ($pesoTotal > 1000) {
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '6')->getNumberFormat()->setFormatCode('0.00" tn"');
            } else {
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '6')->getNumberFormat()->setFormatCode('0.00" Kg"');
            }
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '7')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '10', "=SUM(C10:" . $InitialColumnLetter . "10)");
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '11', "=SUM(C11:" . $InitialColumnLetter . "11)");
            
            $VFOBCell = $InitialColumn . '11';
            $CBMTotal = $InitialColumn . "7";
            $FleteCell = $InitialColumn . '14';
            $CobroCell = $InitialColumn . '40';
            
            $cbmPrimerProducto = is_numeric($data['cliente']['productos'][0]['cbm'] ?? 0) ? (float)$data['cliente']['productos'][0]['cbm'] : 0;
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '7', $cbmPrimerProducto);
            $cbmTotalProductos = $volumen;
            
            $tarifaValue = is_numeric($tarifa ?? 0) ? (float)$tarifa : 0;
            $cbmTotalProductos = round($cbmTotalProductos, 2);
            
            if (trim(strtoupper($tipoCliente)) == "NUEVO") {
                switch ($cbmTotalProductos) {
                    case $cbmTotalProductos < 0.59 && $cbmTotalProductos > 0:
                        $tarifaValue = 280;
                        break;
                    case $cbmTotalProductos < 1.00 && $cbmTotalProductos > 0.59:
                        $tarifaValue = 375;
                        break;
                    case $cbmTotalProductos < 2.00 && $cbmTotalProductos > 1.00:
                        $tarifaValue = 375;
                        break;
                    case $cbmTotalProductos < 3.00 && $cbmTotalProductos > 2.00:
                        $tarifaValue = 350;
                        break;
                    case $cbmTotalProductos <= 4.10 && $cbmTotalProductos > 3.00:
                        $tarifaValue = 325;
                        break;
                    case $cbmTotalProductos > 4.10:
                        $tarifaValue = 300;
                }
            } else if (trim(strtoupper($tipoCliente)) == "ANTIGUO") {
                switch ($cbmTotalProductos) {
                    case $cbmTotalProductos < 0.59 && $cbmTotalProductos > 0:
                        $tarifaValue = 260;
                        break;
                    case $cbmTotalProductos < 1.00 && $cbmTotalProductos > 0.59:
                        $tarifaValue = 350;
                        break;
                    case $cbmTotalProductos <= 2.09 && $cbmTotalProductos > 1.00:
                        $tarifaValue = 350;
                        break;
                    case $cbmTotalProductos <= 3.09 && $cbmTotalProductos > 2.09:
                        $tarifaValue = 325;
                        break;
                    case $cbmTotalProductos <= 4.10 && $cbmTotalProductos > 3.09:
                        $tarifaValue = 300;
                        break;
                    case $cbmTotalProductos > 4.10:
                        $tarifaValue = 280;
                }
            } else if (trim(strtoupper($tipoCliente)) == "SOCIO") {
                switch ($cbmTotalProductos) {
                    case $cbmTotalProductos < 0.60:
                        $tarifaValue = 250;
                        break;
                    case $cbmTotalProductos < 1.00:
                        $tarifaValue = 250;
                        break;
                    case $cbmTotalProductos < 2.00:
                        $tarifaValue = 250;
                        break;
                    case $cbmTotalProductos < 3.00:
                        $tarifaValue = 250;
                        break;
                    case $cbmTotalProductos < 4.00:
                        $tarifaValue = 250;
                        break;
                    case $cbmTotalProductos >= 4.10:
                        $tarifaValue = 250;
                }
            }
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($tarifaCellValue, $tarifaValue);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue(
                $InitialColumn . '14',
                "=IF($CBMTotal<1, $tarifaCellValue*0.6, $tarifaCellValue*0.6*$CBMTotal)"
            );
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue(
                $InitialColumn . '40',
                "=IF($CBMTotal<1, $tarifaCellValue*0.4,$tarifaCellValue*0.4*$CBMTotal)"
            );
            
            $antidumpingSum = 0;
            $InitialColumn = 'C';
            
            // second iteration for each product and set values and apply styles
            foreach ($data['cliente']['productos'] as $producto) {
                // Validar y convertir valores a numéricos
                $antidumping = is_numeric($producto["antidumping"] ?? 0) ? (float)$producto["antidumping"] : 0;
                $adValorem = is_numeric($producto["ad_valorem"] ?? 0) ? (float)$producto["ad_valorem"] : 0;
                $percepcion = is_numeric($producto['percepcion'] ?? 0.035) ? (float)$producto['percepcion'] : 0.035;
                $cantidad = is_numeric($producto["cantidad"] ?? 0) ? (float)$producto["cantidad"] : 0;
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '13', "=" . $InitialColumn . '11/' . $VFOBCell);
                $distroCell = $InitialColumn . '13';
                
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '13')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '14', "=" . $FleteCell . '*' . $InitialColumn . '13');
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '14')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '15', "=" . $InitialColumn . '11+' . $InitialColumn . '14');
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '15')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $cfrCell = $InitialColumn . '15';
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '16', "=" . $InitialColumn . '12+' . $InitialColumn . '14');
                $cfrvCell = $InitialColumn . '16';
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '16')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $seguroCell = $InitialColumn . '17';
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '17')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '17', "=IF(" . $LastColumnLetter . "11>5000,100*" . $distroCell . ",50*" . $distroCell . ")");
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '18', "=" . $cfrCell . '+' . $seguroCell . "");
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '19', "=" . $cfrvCell . '+' . $seguroCell . "");
                
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '18')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '19')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $quantityCell = $InitialColumn . '10';
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '26', ($antidumping * $cantidad) == 0 ? 0 : "=" . $InitialColumn . '10*' . $antidumping);
                $antidumpingSum += ($antidumping * $cantidad);
                
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '27', $adValorem);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '27')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '27')->getFont()->getColor()->setARGB(Color::COLOR_RED);
                
                $AdValoremCell = $InitialColumn . '28';
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue(
                    $InitialColumn . '28',
                    "=MAX(" . $InitialColumn . "19," . $InitialColumn . "18)*" . $InitialColumn . "27"
                );
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '28')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '29', "=" . (16 / 100) . "*(" . "MAX(" . $InitialColumn . "19," . $InitialColumn . "18)+" . $AdValoremCell . ")");
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '29')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '30', "=" . (2 / 100) . "*(" . "MAX(" . $InitialColumn . "19," . $InitialColumn . "18)+" . $AdValoremCell . ")");
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '30')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue(
                    $InitialColumn . '31',
                    "=" . $percepcion . "*(MAX(" . $InitialColumn . '18,' . $InitialColumn . '19) +' . $InitialColumn . '28+' . $InitialColumn . '29+' . $InitialColumn . '30)'
                );
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '31')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $sum = "=SUM(" . $InitialColumn . "28:" . $InitialColumn . "31)";
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '32', $sum);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '32')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '40', "=" . $distroCell . "*" . $CobroCell);
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '40')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '43', $producto["nombre"] ?? '');
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '45', $cantidad);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue(
                    $InitialColumn . '44',
                    "=SUM(" . $InitialColumn . "15," . $InitialColumn . "40," . $InitialColumn . "32,(" . $InitialColumn . "26" . "))"
                );
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '44')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '45', $cantidad);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '46', "=SUM(" . $InitialColumn . "44/" . $InitialColumn . "45)");
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '46')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '47', "=" . $InitialColumn . "46*3.7");
                $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '47')->getNumberFormat()->setFormatCode('"S/." #,##0.00_-');
                
                $InitialColumn++;
            }
            
            // Continue with final columns styling
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '11')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '11')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '12', "=SUM(C12:" . $InitialColumnLetter . "12)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '12')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '12')->getFont()->setBold(true);
            
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '14')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '14')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '15', "=SUM(C15:" . $InitialColumnLetter . "15)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '15')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '15')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '16', "=SUM(C16:" . $InitialColumnLetter . "16)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '16')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '16')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '17', "=SUM(C17:" . $InitialColumnLetter . "17)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '17')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '17')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '18', "=SUM(C18:" . $InitialColumnLetter . "18)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '18')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '18')->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '19', "=SUM(C19:" . $InitialColumnLetter . "19)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '19')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '19')->getFont()->setBold(true);
            
            $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B23:E23');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B23', 'Tributos Aplicables');
            $style = $objPHPExcel->getActiveSheet()->getStyle('B23');
            $style->getFill()->setFillType(Fill::FILL_SOLID);
            $style->getFill()->getStartColor()->setARGB($grayColor);
            $objPHPExcel->getActiveSheet()->getStyle('B23:E23')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('B23')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B26', 'ANTIDUMPING');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B28', 'AD VALOREM');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B29', 'IGV');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B30', 'IPM');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B31', 'PERCEPCION');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B32', 'TOTAL');
            
            $objPHPExcel->getActiveSheet()->getStyle('B26:' . $InitialColumn . '26')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('C27:' . $InitialColumn . '27')->applyFromArray($borders);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '26', "=SUM(C26:" . $InitialColumnLetter . "26)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '27', "=SUM(C27:" . $InitialColumnLetter . "27)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '27')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '28', "=SUM(C28:" . $InitialColumnLetter . "28)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '28')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '29', "=SUM(C29:" . $InitialColumnLetter . "29)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '29')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '30', "=SUM(C30:" . $InitialColumnLetter . "30)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '30')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '31', "=SUM(C31:" . $InitialColumnLetter . "31)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '31')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '32', "=SUM($InitialColumn" . "28:" . $InitialColumn . "31)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '32')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            // Costos Destinos
            $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B37:E37');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B37', 'Costos Destinos');
            $style = $objPHPExcel->getActiveSheet()->getStyle('B37');
            $style->getFill()->setFillType(Fill::FILL_SOLID);
            $style->getFill()->getStartColor()->setARGB($grayColor);
            $objPHPExcel->getActiveSheet()->getStyle('B37:E37')->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('B37')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B40', 'ITEM');
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '40')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $objPHPExcel->setActiveSheetIndex(2)->mergeCells('B41:E41');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B43', 'ITEM');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B44', 'COSTO TOTAL');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B45', 'CANTIDAD');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B46', 'COSTO UNITARIO');
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue('B47', 'COSTO SOLES');
            
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '44', "=SUM(C44" . ":" . $InitialColumnLetter . "44)");
            $productsCount = count($data['cliente']['productos']);
            //C column + products count
            $ColumndIndex = Coordinate::stringFromColumnIndex($productsCount + 2);
            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue('J20', "=MAX('3'!C27:" . $ColumndIndex . "27)");
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '43', "Total");
            $objPHPExcel->setActiveSheetIndex(2)->setCellValue($InitialColumn . '44', "=SUM(C44:" . $InitialColumnLetter . "44)");
            $objPHPExcel->getActiveSheet()->getStyle($InitialColumn . '44')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $columnaIndex = Coordinate::stringFromColumnIndex($productsCount + 3);
            
            $objPHPExcel->setActiveSheetIndex(0);
            $objPHPExcel->getActiveSheet()->setCellValue('K14', "='3'!" . $columnaIndex . "11");
            
            // Construir la fórmula para sumar los valores de las celdas en las columnas 14 y 17
            $formula = "='3'!" . $columnaIndex . "14 + '3'!" . $columnaIndex . "17";
            $objPHPExcel->getActiveSheet()->setCellValue('K15', $formula);
            
            $objPHPExcel->getActiveSheet()->setCellValue('K20', "='3'!" . $columnaIndex . "28");
            $objPHPExcel->getActiveSheet()->setCellValue('K21', "='3'!" . $columnaIndex . "29");
            $objPHPExcel->getActiveSheet()->setCellValue('K22', "='3'!" . $columnaIndex . "30");
            $objPHPExcel->getActiveSheet()->setCellValue('K25', "='3'!" . $columnaIndex . "31");
            
            $objPHPExcel->getActiveSheet()->setCellValue('K30', "=IF(J11<1, '3'!" . $tarifaCellValue . ", '3'!" . $tarifaCellValue . "*J11)");
            $LogisticaValue = $objPHPExcel->getActiveSheet()->getCell('K30')->getCalculatedValue();
            $CobroCellValue = $objPHPExcel->getActiveSheet()->getCell('K30')->getCalculatedValue();
            $ImpuestosCellValue = round($objPHPExcel->getActiveSheet()->getCell('K31')->getCalculatedValue(), 2);
            
            // Limpiar filas 36-39
            for ($row = 36; $row <= 39; $row++) {
                for ($col = 1; $col <= 12; $col++) {
                    $cell = Coordinate::stringFromColumnIndex($col) . $row;
                    $objPHPExcel->getActiveSheet()->setCellValue($cell, '');
                    $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray(array());
                }
            }
            
            // Productos en la hoja principal (rows 36+)
            $lastRow = 0;
            $InitialColumn = 'C';
            
            if ($productsCount < 3) {
                $substract = 3 - $productsCount;
                for ($i = 0; $i < $substract; $i++) {
                    $row = 36 + $i + $productsCount;
                    $objPHPExcel->getActiveSheet()->getStyle('B' . $row . ':L' . $row)->applyFromArray(array());
                }
            }
            // Guardar los estilos de la fila 36 del template (SIN bordes) para aplicarlos a todas las filas de productos
            $templateRowStyles = [];
            foreach (range('B', 'L') as $col) {
                $styleArray = $objPHPExcel->getActiveSheet()->getStyle($col . '36')->exportArray();
                // Eliminar los bordes del array de estilos para no sobrescribirlos
                unset($styleArray['borders']);
                $templateRowStyles[$col] = $styleArray;
            }

            for ($index = 0; $index < $productsCount; $index++) {
                $row = 36 + $index;
                if ($index >= 3 && $index != $productsCount) {
                    $sheet = $objPHPExcel->getActiveSheet();
                    $sheet->insertNewRowBefore($row, 1);
                    
                    // Aplicar los estilos del template a la nueva fila (sin bordes)
                    foreach (range('B', 'L') as $col) {
                        $sheet->getStyle($col . $row)->applyFromArray($templateRowStyles[$col]);
                    }
                }
                
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $index + 1);
                $objPHPExcel->getActiveSheet()->getStyle('B' . $row)->getFont()->setBold(false);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $row, $data['cliente']['productos'][$index]["nombre"]);
                $objPHPExcel->getActiveSheet()->setCellValue('F' . $row, "='3'!" . $InitialColumn . 10);
                $objPHPExcel->getActiveSheet()->getStyle('F' . $row)->getFont()->setBold(false);
                $objPHPExcel->getActiveSheet()->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $objPHPExcel->getActiveSheet()->setCellValue('G' . $row, "='3'!" . $InitialColumn . 8);
                $objPHPExcel->getActiveSheet()->setCellValue('J11', "='3'!" . $CBMTotal);
                // Formato USD con 2 decimales
                $objPHPExcel->getActiveSheet()->getStyle('G' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
                $objPHPExcel->getActiveSheet()->setCellValue('I' . $row, "='3'!" . $InitialColumn . 46);
                $objPHPExcel->getActiveSheet()->getStyle('I' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
                $objPHPExcel->getActiveSheet()->setCellValue('J' . $row, "='3'!" . $InitialColumn . 44);
                $objPHPExcel->getActiveSheet()->getStyle('J' . $row)->getFont()->setBold(false);
                $objPHPExcel->getActiveSheet()->getStyle('J' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
                
                $JCellVal = $objPHPExcel->getActiveSheet()->getCell('J' . $row)->getValue();
                $objPHPExcel->getActiveSheet()->setCellValue('K' . $row, "='3'!" . $InitialColumn . 47);
                
                // Merge cells PRIMERO
                $objPHPExcel->getActiveSheet()->mergeCells('C' . $row . ':E' . $row);
                $objPHPExcel->getActiveSheet()->mergeCells('G' . $row . ':H' . $row);
                $objPHPExcel->getActiveSheet()->mergeCells('K' . $row . ':L' . $row);
                
                // Aplicar estilos de fuente y alineación
                $columnsToApply = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
                foreach ($columnsToApply as $column) {
                    $objPHPExcel->getActiveSheet()->getStyle($column . $row)->getFont()->setName('Calibri');
                    $objPHPExcel->getActiveSheet()->getStyle($column . $row)->getFont()->setSize(11);
                    $objPHPExcel->getActiveSheet()->getStyle($column . $row)->getFont()->setBold(true);
                    $objPHPExcel->getActiveSheet()->getStyle($column . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    if ($column == 'K') {
                        $objPHPExcel->getActiveSheet()->getStyle($column . $row)->getNumberFormat()->setFormatCode('"S/." #,##0.00_-');
                    }
                }
                
                // Aplicar color de fondo a la columna K
                $style = $objPHPExcel->getActiveSheet()->getStyle('K' . $row);
                $style->getFill()->setFillType(Fill::FILL_SOLID);
                $style->getFill()->getStartColor()->setARGB($greenColor);
                $style->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                
                $InitialColumn = $this->incrementColumn($InitialColumn);
                $lastRow = $row;
            }
            
            // Aplicar bordes a TODAS las filas de productos DESPUÉS del loop
            // Esto asegura que todas las filas tengan bordes sin importar si fueron insertadas o ya existían
            for ($index = 0; $index < $productsCount; $index++) {
                $row = 36 + $index;
                
                // Aplicar bordes directamente usando getBorders() para cada celda
                $allColumns = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
                foreach ($allColumns as $col) {
                    $cellStyle = $objPHPExcel->getActiveSheet()->getStyle($col . $row);
                    $cellStyle->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                    $cellStyle->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
                    $cellStyle->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
                    $cellStyle->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
                }
            }
            
            // Limpiar filas no usadas del template (solo cuando hay menos de 3 productos)
            // El template tiene 3 filas por defecto (36, 37, 38)
            $notUsedDefaultRows = 3 - $productsCount;
            if ($notUsedDefaultRows > 0) {
                for ($i = 0; $i < $notUsedDefaultRows; $i++) {
                    $row = 36 + $productsCount + $i;
                    // Limpiar todos los estilos y bordes de las filas no usadas
                    $objPHPExcel->getActiveSheet()->getStyle('B' . $row . ':L' . $row)->applyFromArray(array(
                        'borders' => array(
                            'allborders' => array(
                                'style' => Border::BORDER_NONE,
                            ),
                        ),
                    ));
                    // Limpiar fondo de columna K
                    $style = $objPHPExcel->getActiveSheet()->getStyle('K' . $row);
                    $style->getFill()->setFillType(Fill::FILL_SOLID);
                    $style->getFill()->getStartColor()->setARGB($whiteColor);
                }
            }
            
            $lastRow++;
            
          
            if ($notUsedDefaultRows >= 0) {
                $objPHPExcel->getActiveSheet()->mergeCells('C' . $lastRow . ':E' . $lastRow);
                $objPHPExcel->getActiveSheet()->unmergeCells('C' . $lastRow . ':E' . $lastRow);
                $objPHPExcel->getActiveSheet()->mergeCells('B' . $lastRow . ':E' . $lastRow);
            }
            
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $lastRow, "TOTAL");
            $objPHPExcel->getActiveSheet()->getStyle('B' . $lastRow)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle('B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $lastRow, "=SUM(F36:F" . ($lastRow - 1) . ")");
            $objPHPExcel->getActiveSheet()->getStyle('F' . $lastRow)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle('F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $objPHPExcel->getActiveSheet()->setCellValue('J' . $lastRow, "=SUM(J36:J" . ($lastRow - 1) . ")");
            $objPHPExcel->getActiveSheet()->getStyle('J' . $lastRow)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle('J' . $lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
            $objPHPExcel->getActiveSheet()->getStyle('J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Aplicar bordes solo a las columnas B, F y J de la fila TOTAL
            $objPHPExcel->getActiveSheet()->getStyle('B' . $lastRow . ':E' . $lastRow)->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('F' . $lastRow)->applyFromArray($borders);
            $objPHPExcel->getActiveSheet()->getStyle('J' . $lastRow)->applyFromArray($borders);
            
            // Establecer tamaño de fuente
            $objPHPExcel->getActiveSheet()->getStyle('B' . $lastRow . ':L' . ($lastRow + 1))->getFont()->setSize(11);
            //apply for total row=lastRow+1
            
            $cellToCheck = 'I22';
            $rowToCheck = 23;
            $sheet = $objPHPExcel->getActiveSheet();
            
            // Verificar si se cumple la condición
            if (is_numeric($antidumpingSum) && $antidumpingSum != 0) {
                $sheet->insertNewRowBefore($rowToCheck, 1);
                $newRowIndex = $rowToCheck;
                $sheet->setCellValue('B' . $newRowIndex, "ANTIDUMPING");
                $sheet->setCellValue('K' . $newRowIndex, $antidumpingSum);
                
                $style = $sheet->getStyle('B' . $newRowIndex . ':L' . $newRowIndex);
                $style->getFill()->setFillType(Fill::FILL_SOLID);
                $style->getFill()->getStartColor()->setARGB($yellowColor);
                $objPHPExcel->getActiveSheet()->setCellValue('K24', "=SUM(K20:K23)");
            }
            
            // Inicializar variables con valores por defecto
            $montoFinal = 0;
            
            if ($objPHPExcel->getActiveSheet()->getCell('B23')->getValue() == "ANTIDUMPING") {
                try {
                    $montoFinal = $objPHPExcel->getActiveSheet()->getCell('K31')->getCalculatedValue();
                } catch (\Exception $e) {
                    Log::warning('Error calculando K31: ' . $e->getMessage());
                    $montoFinal = 0;
                }
            }
            
            if ($sheet1->getCell('B23')->getValue() == "ANTIDUMPING") {
                try {
                    $fob = is_numeric($sheet1->getCell('K30')->getCalculatedValue()) ? $sheet1->getCell('K30')->getCalculatedValue() : 0;
                    $logistica = is_numeric($sheet1->getCell('K31')->getCalculatedValue()) ? $sheet1->getCell('K31')->getCalculatedValue() : 0;
                    $impuestos = is_numeric($sheet1->getCell('K32')->getCalculatedValue()) ? $sheet1->getCell('K32')->getCalculatedValue() : 0;
                } catch (\Exception $e) {
                    Log::warning('Error calculando valores con antidumping: ' . $e->getMessage());
            $fob = 0;
            $logistica = 0;
            $impuestos = 0;
                }
            } else {
                try {
                    $fob = is_numeric($sheet1->getCell('K29')->getCalculatedValue()) ? $sheet1->getCell('K29')->getCalculatedValue() : 0;
                    $logistica = is_numeric($sheet1->getCell('K30')->getCalculatedValue()) ? $sheet1->getCell('K30')->getCalculatedValue() : 0;
                    $impuestos = is_numeric($sheet1->getCell('K31')->getCalculatedValue()) ? $sheet1->getCell('K31')->getCalculatedValue() : 0;
                } catch (\Exception $e) {
                    Log::warning('Error calculando valores sin antidumping: ' . $e->getMessage());
                    $fob = 0;
                    $logistica = 0;
                    $impuestos = 0;
                }
            }
            
            // Configurar hoja principal (Sheet 0)
            $objPHPExcel->getActiveSheet()->mergeCells('C8:C9');
            $objPHPExcel->getActiveSheet()->getStyle('C8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->setCellValue('C8', $data['cliente']['nombre']);
            $objPHPExcel->getActiveSheet()->setCellValue('C10', $data['cliente']['dni']);
            $objPHPExcel->getActiveSheet()->setCellValue('C11', $data['cliente']['telefono']);
            $objPHPExcel->getActiveSheet()->setCellValue('J9', $pesoTotal >= 1000 ? $pesoTotal / 1000 . " Tn" : $pesoTotal . " Kg");
            
            $objPHPExcel->getActiveSheet()->getStyle('J11')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER . ' "m3"');
            $objPHPExcel->getActiveSheet()->getStyle('J11')->getNumberFormat()->setFormatCode('#,##0.00');
            
            $objPHPExcel->getActiveSheet()->setCellValue('I11', "CBM");
            $objPHPExcel->getActiveSheet()->getStyle('J9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $objPHPExcel->getActiveSheet()->getColumnDimension("I")->setAutoSize(true);
            
            $objPHPExcel->getActiveSheet()->setCellValue('J10', "");
            $objPHPExcel->getActiveSheet()->getStyle('K10')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $objPHPExcel->getActiveSheet()->setCellValue('L10', "");
            
            $objPHPExcel->getActiveSheet()->setCellValue('F11', $tipoCliente);
            
            $ClientName = $objPHPExcel->getActiveSheet()->getCell('C8')->getValue();
            $objPHPExcel->getActiveSheet()->getStyle('C8')->getAlignment()->setWrapText(true);
            
            $N20CellValue =
                "Hola " . $ClientName . " 😁 un gusto saludarte!
        A continuación te envío la cotización final de tu importación📋📦.
        🙋‍♂️ PAGO PENDIENTE :
        ☑️Costo CBM: $" . $CobroCellValue . "
        ☑️Impuestos: $" . $ImpuestosCellValue . "
        ☑️ Total: $" . ($ImpuestosCellValue + $CobroCellValue) . "
        Pronto le aviso nuevos avances, que tengan buen día🚢
        Último día de pago:";
            
            $objPHPExcel->getActiveSheet()->setCellValue('N20', $N20CellValue);
            
            // remove page 2
            $objPHPExcel->removeSheetByIndex(1);
            // set sheet 3 title to 2
            $objPHPExcel->setActiveSheetIndex(1);
            $objPHPExcel->getActiveSheet()->setTitle('2');
            
            $objWriter = IOFactory::createWriter($objPHPExcel, 'Xlsx');
            // Agregar timestamp al nombre del archivo para evitar sobrescrituras
            $timestamp = date('YmdHis');
            $excelFileName = 'Cotizacion' . ($data['cliente']['nombre'] ?? 'Cliente') . '_' . $timestamp . '.xlsx';
            
            // Crear directorio si no existe
            $directory = public_path('storage/cotizacion_final/' . $idContenedor);
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
            
            // La ruta para la BD no debe incluir 'storage/' porque Storage::disk('public')->url() ya lo agrega
            $excelFilePath = 'cotizacion_final/' . $idContenedor . '/' . $excelFileName;
            $fullPath = public_path('storage/' . $excelFilePath);
            
            $objPHPExcel->setActiveSheetIndex(0);
            
            // Obtener valores después del recálculo con validación
            $sheet1 = $objPHPExcel->getActiveSheet();
            
            try {
                $montoFinal = is_numeric($sheet1->getCell('K30')->getCalculatedValue()) 
                    ? $sheet1->getCell('K30')->getCalculatedValue() : 0;
            } catch (\Exception $e) {
                Log::warning('Error calculando K30: ' . $e->getMessage());
                $montoFinal = 0;
            }
            
            if ($objPHPExcel->getActiveSheet()->getCell('B23')->getValue() == "ANTIDUMPING") {
                try {
                    $montoFinal = is_numeric($objPHPExcel->getActiveSheet()->getCell('K31')->getCalculatedValue()) 
                        ? $objPHPExcel->getActiveSheet()->getCell('K31')->getCalculatedValue() : 0;
            } catch (\Exception $e) {
                    Log::warning('Error calculando K31 con antidumping: ' . $e->getMessage());
                    $montoFinal = 0;
                }
            }
            
            if ($sheet1->getCell('B23')->getValue() == "ANTIDUMPING") {
                try {
                    $fob = is_numeric($sheet1->getCell('K30')->getCalculatedValue()) ? $sheet1->getCell('K30')->getCalculatedValue() : 0;
                    $logistica = is_numeric($sheet1->getCell('K31')->getCalculatedValue()) ? $sheet1->getCell('K31')->getCalculatedValue() : 0;
                    $impuestos = is_numeric($sheet1->getCell('K32')->getCalculatedValue()) ? $sheet1->getCell('K32')->getCalculatedValue() : 0;
                } catch (\Exception $e) {
                    Log::warning('Error en valores finales con antidumping: ' . $e->getMessage());
                $fob = 0;
                    $logistica = 0;
                    $impuestos = 0;
                }
            } else {
                try {
                    $fob = is_numeric($sheet1->getCell('K29')->getCalculatedValue()) ? $sheet1->getCell('K29')->getCalculatedValue() : 0;
                    $logistica = is_numeric($sheet1->getCell('K30')->getCalculatedValue()) ? $sheet1->getCell('K30')->getCalculatedValue() : 0;
                    $impuestos = is_numeric($sheet1->getCell('K31')->getCalculatedValue()) ? $sheet1->getCell('K31')->getCalculatedValue() : 0;
                } catch (\Exception $e) {
                    Log::warning('Error en valores finales sin antidumping: ' . $e->getMessage());
                    $fob = 0;
                    $logistica = 0;
                    $impuestos = 0;
                }
            }
            
            $objPHPExcel->setActiveSheetIndex(1);
            // Recalcular logística basado en cbm
            $logisticaCalculada = ($cbmTotalProductos < 1.00) ? $tarifaValue : ($cbmTotalProductos * $tarifaValue);
            $logistica = is_numeric($logisticaCalculada) ? $logisticaCalculada : $logistica;
            
            $objWriter->save($fullPath);
            
            return [
                'id' => $data['id'] ?? null,
                'id_contenedor' => $idContenedor,
                'id_tipo_cliente' => $data['cliente']['id_tipo_cliente'] ?? null,
                'nombre' => $data['cliente']['nombre'] ?? '',
                'documento' => $data['cliente']['dni'] ?? '',
                'correo' => $data['cliente']['correo'] ?? '',
                'whatsapp' => $data['cliente']['telefono'] ?? '',
                'volumen_final' => is_numeric($volumen) ? $volumen : 0,
                'monto_final' => is_numeric($montoFinal) ? $montoFinal : 0,
                'tarifa_final' => is_numeric($tarifaValue) ? $tarifaValue : 0,
                'impuestos_final' => is_numeric($impuestos) ? $impuestos : 0,
                'logistica_final' => is_numeric($logistica) ? $logistica : 0,
                'fob_final' => is_numeric($fob) ? $fob : 0,
                'peso_final' => is_numeric($pesoTotal) ? $pesoTotal : 0,
                'estado' => 'PENDIENTE',
                "excel_file_name" => $excelFileName,
                "excel_file_path" => $excelFilePath,
                "cotizacion_final_url" => $excelFilePath
            ];
            
        } catch (\Exception $e) {
            echo 'Excepción capturada: ',  $e->getMessage(), "\n";
            Log::error('Error en getFinalCotizacionExcelv2: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            Log::error('Line: ' . $e->getLine());
            Log::error('File: ' . $e->getFile());
            throw $e;
        }
    }
    
    /**
     * Configura la hoja de tributos con toda la lógica migrada
     */
    private function setupTributosSheetModern($spreadsheet, $sheet, $data, $idContenedor)
    {
        $spreadsheet->setActiveSheetIndex(2); // Activar hoja de tributos
        
        // Colores base
        $grayColor = 'F8F9F9';
        $blueColor = '1F618D';
        $yellowColor = 'FFFF33';
        $greenColor = "009999";
        $whiteColor = "FFFFFF";
        
        // Estilos de bordes
        $borders = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        
        // Título principal
        $sheet->mergeCells('B3:G3');
        $sheet->setCellValue('B3', 'Calculo de Tributos');
        $sheet->getStyle('B3')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle('B3')->getFill()->getStartColor()->setARGB($grayColor);
        $sheet->getStyle('B3:G3')->applyFromArray($borders);
        $sheet->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Configurar encabezados
        $headers = [
            'B5' => 'Nombres', 'B6' => 'Peso', 'B7' => 'Valor CBM', 'B8' => 'Valor Unitario',
            'B9' => 'Valoracion', 'B10' => 'Cantidad', 'B11' => 'Valor FOB', 'B12' => 'Valor FOB Valoracion',
            'B13' => 'Distribucion %', 'B14' => 'Flete', 'B15' => 'Valor CFR', 'B16' => 'CFR Valorizado',
            'B17' => 'Seguro', 'B18' => 'Valor CIF', 'B19' => 'CIF Valorizado'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Aplicar estilos especiales a encabezados
        $sheet->getStyle('B5')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle('B5')->getFill()->getStartColor()->setARGB($blueColor);
        $sheet->getStyle('B5')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
        $sheet->getStyle('B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $yellowHeaders = ['B9', 'B12', 'B16', 'B19'];
        foreach ($yellowHeaders as $cell) {
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle($cell)->getFill()->getStartColor()->setARGB($yellowColor);
        }
        
        $sheet->getColumnDimension('B')->setAutoSize(true);
        
        // Procesar productos y configurar fórmulas
        $this->processProductsInTributosSheet($sheet, $data, $borders, $blueColor, $yellowColor);
        
        // Configurar secciones adicionales
        $this->setupAdditionalSections($sheet, $data, $borders, $grayColor, $greenColor);
    }
    
    /**
     * Procesa los productos en la hoja de tributos
     */
    private function processProductsInTributosSheet($sheet, $data, $borders, $blueColor, $yellowColor)
    {
        $InitialColumn = 'C';
        $productos = $data['cliente']['productos'];
        $tarifa = $data['cliente']['tarifa'];
        $tipoCliente = trim($data['cliente']['tipo_cliente']);
        $volumen = $data['cliente']['volumen'];
        $pesoTotal = isset($productos[0]['peso']) && is_numeric($productos[0]['peso']) ? (float)$productos[0]['peso'] : 0;
        
        // Validar y calcular tarifa según tipo de cliente
        $volumenNumerico = is_numeric($volumen) ? (float)$volumen : 0;
        $tarifaBase = is_numeric($tarifa) ? (float)$tarifa : 0;
        $tarifaValue = $this->calculateTarifaByTipoCliente($tipoCliente, $volumenNumerico, $tarifaBase);
        
        $antidumpingSum = 0;
        
        foreach ($productos as $index => $producto) {
            $column = $this->incrementColumn($InitialColumn, $index);
            $sheet->getColumnDimension($column)->setAutoSize(true);
            
            // Validar y convertir valores numéricos
            $precioUnitario = is_numeric($producto["precio_unitario"]) ? (float)$producto["precio_unitario"] : 0;
            $valoracion = is_numeric($producto["valoracion"]) ? (float)$producto["valoracion"] : 0;
            $cantidad = is_numeric($producto["cantidad"]) ? (float)$producto["cantidad"] : 0;
            $antidumping = is_numeric($producto["antidumping"]) ? (float)$producto["antidumping"] : 0;
            
            // Configurar datos básicos del producto
            $sheet->setCellValue($column . '5', $producto["nombre"]);
            $sheet->getStyle($column . '5')->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle($column . '5')->getFill()->getStartColor()->setARGB($blueColor);
            $sheet->getStyle($column . '5')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
            
            $sheet->setCellValue($column . '6', 0);
            $sheet->setCellValue($column . '7', 0);
            $sheet->setCellValue($column . '8', $precioUnitario);
            $sheet->setCellValue($column . '9', $valoracion);
            $sheet->setCellValue($column . '10', $cantidad);
            $sheet->setCellValue($column . '11', "=" . $column . "8*" . $column . "10");
            $sheet->setCellValue($column . '12', "=" . $column . "10*" . $column . "9");
            
            // Aplicar formato de moneda
            $currencyCells = [$column . '8', $column . '9', $column . '11', $column . '12'];
            foreach ($currencyCells as $cell) {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            }
            
            $antidumpingSum += $antidumping * $cantidad;
        }
        
        // Configurar columna total y fórmulas complejas
        $this->setupTotalColumnAndFormulas($sheet, $data, $productos, $pesoTotal, $tarifaValue, $blueColor);
    }
    
    /**
     * Configura la columna total y las fórmulas complejas
     */
    private function setupTotalColumnAndFormulas($sheet, $data, $productos, $pesoTotal, $tarifaValue, $blueColor)
    {
        $totalColumn = $this->incrementColumn('C', count($productos));
        $lastProductColumn = $this->incrementColumn('C', count($productos) - 1);
        
        // Configurar columna total
        $sheet->getStyle($totalColumn . '5')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($totalColumn . '5')->getFill()->getStartColor()->setARGB($blueColor);
        $sheet->setCellValue($totalColumn . '5', "Total");
        $sheet->getStyle($totalColumn . '5')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
        
        // Configurar peso (validar que sea numérico)
        $pesoNumerico = is_numeric($pesoTotal) ? (float)$pesoTotal : 0;
        $sheet->setCellValue($totalColumn . '6', $pesoNumerico > 1000 ? round($pesoNumerico / 1000, 2) : $pesoNumerico);
        if ($pesoNumerico > 1000) {
            $sheet->getStyle($totalColumn . '6')->getNumberFormat()->setFormatCode('0.00" tn"');
        } else {
            $sheet->getStyle($totalColumn . '6')->getNumberFormat()->setFormatCode('0.00" Kg"');
        }
        
        // Configurar CBM y totales básicos
        // IMPORTANTE: Usar el CBM del primer producto (como en la versión original)
        $cbmPrimerProducto = isset($productos[0]['cbm']) && is_numeric($productos[0]['cbm']) 
            ? (float)$productos[0]['cbm'] : 0;
        $sheet->setCellValue($totalColumn . '7', $cbmPrimerProducto);
        $sheet->getStyle($totalColumn . '7')->getFont()->setBold(true);
        
        // Configurar suma de cantidades y valores FOB
        $sheet->setCellValue($totalColumn . '10', "=SUM(C10:" . $lastProductColumn . "10)");
        $sheet->setCellValue($totalColumn . '11', "=SUM(C11:" . $lastProductColumn . "11)");
        $sheet->getStyle($totalColumn . '11')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        $sheet->getStyle($totalColumn . '11')->getFont()->setBold(true);
        
        // Configurar tipo cliente y tarifa
        $tipoClienteCell = $this->incrementColumn($totalColumn, 3) . '6';
        $tipoClienteCellValue = $this->incrementColumn($totalColumn, 3) . '7';
        $tarifaCell = $this->incrementColumn($totalColumn, 4) . '6';
        $tarifaCellValue = $this->incrementColumn($totalColumn, 4) . '7';
        
        $sheet->setCellValue($tipoClienteCell, "Tipo Cliente");
        $sheet->setCellValue($tarifaCell, "Tarifa");
        $sheet->setCellValue($tipoClienteCellValue, $data['cliente']['tipo_cliente']);
        $sheet->setCellValue($tarifaCellValue, $tarifaValue);
        
        // Configurar fórmulas principales
        $CBMTotal = $totalColumn . "7";
        $sheet->setCellValue($totalColumn . '14', "=IF($CBMTotal<1, $tarifaCellValue*0.6, $tarifaCellValue*0.6*$CBMTotal)");
        $sheet->setCellValue($totalColumn . '40', "=IF($CBMTotal<1, $tarifaCellValue*0.4, $tarifaCellValue*0.4*$CBMTotal)");
        
        // Configurar fórmulas complejas para cada producto
        $this->setupComplexFormulasForProducts($sheet, $data['cliente']['productos'], $totalColumn);
    }
    
    /**
     * Configura fórmulas complejas para cada producto
     */
    private function setupComplexFormulasForProducts($sheet, $productos, $totalColumn)
    {
        $InitialColumn = 'C';
        $FleteCell = $totalColumn . '14';
        $CobroCell = $totalColumn . '40';
        
        foreach ($productos as $index => $producto) {
            $column = $this->incrementColumn($InitialColumn, $index);
            
            // Fórmulas de distribución y cálculos
            $sheet->setCellValue($column . '13', "=" . $column . '11/' . $totalColumn . '11');
            $sheet->getStyle($column . '13')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            
            $sheet->setCellValue($column . '14', "=" . $FleteCell . '*' . $column . '13');
            $sheet->getStyle($column . '14')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $sheet->setCellValue($column . '15', "=" . $column . '11+' . $column . '14');
            $sheet->getStyle($column . '15')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $sheet->setCellValue($column . '16', "=" . $column . '12+' . $column . '14');
            $sheet->getStyle($column . '16')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            // Seguro
            $distroCell = $column . '13';
            $sheet->setCellValue($column . '17', "=IF(" . $totalColumn . "11>5000,100*" . $distroCell . ",50*" . $distroCell . ")");
            $sheet->getStyle($column . '17')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            // CIF
            $sheet->setCellValue($column . '18', "=" . $column . '15+' . $column . '17');
            $sheet->setCellValue($column . '19', "=" . $column . '16+' . $column . '17');
            $sheet->getStyle($column . '18')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $sheet->getStyle($column . '19')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            // Configurar tributos específicos
            $this->setupTributesForProduct($sheet, $column, $producto);
            
            // Costos destino y resumen final
            $sheet->setCellValue($column . '40', "=" . $distroCell . "*" . $CobroCell);
            $sheet->getStyle($column . '40')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            
            $sheet->setCellValue($column . '43', $producto["nombre"]);
            $sheet->setCellValue($column . '44', "=SUM(" . $column . "15," . $column . "40," . $column . "32,(" . $column . "26))");
            $sheet->getStyle($column . '44')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $sheet->setCellValue($column . '45', $producto["cantidad"]);
            $sheet->setCellValue($column . '46', "=SUM(" . $column . "44/" . $column . "45)");
            $sheet->getStyle($column . '46')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            $sheet->setCellValue($column . '47', "=" . $column . "46*3.7");
            $sheet->getStyle($column . '47')->getNumberFormat()->setFormatCode('"S/." #,##0.00_-');
        }
    }
    
    /**
     * Configura tributos específicos para un producto
     */
    private function setupTributesForProduct($sheet, $column, $producto)
    {
        // Validar y convertir valores numéricos
        $antidumping = is_numeric($producto["antidumping"]) ? (float)$producto["antidumping"] : 0;
        $cantidad = is_numeric($producto["cantidad"]) ? (float)$producto["cantidad"] : 0;
        $adValorem = is_numeric($producto["ad_valorem"]) ? (float)$producto["ad_valorem"] : 0;
        $percepcion = is_numeric($producto['percepcion']) ? (float)$producto['percepcion'] : 0.035;
        
        // Antidumping
        $antidumpingValue = $antidumping * $cantidad;
        $sheet->setCellValue($column . '26', $antidumpingValue == 0 ? 0 : "=" . $column . '10*' . $antidumping);
        $sheet->getStyle($column . '26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        // Ad Valorem
        $sheet->setCellValue($column . '27', $adValorem);
        $sheet->getStyle($column . '27')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle($column . '27')->getFont()->getColor()->setARGB(Color::COLOR_RED);
        
        $AdValoremCell = $column . '28';
        $sheet->setCellValue($AdValoremCell, "=MAX(" . $column . "19," . $column . "18)*" . $column . "27");
        $sheet->getStyle($AdValoremCell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        // IGV, IPM, Percepción
        $sheet->setCellValue($column . '29', "=" . (16/100) . "*(" . "MAX(" . $column . "19," . $column . "18)+" . $AdValoremCell . ")");
        $sheet->getStyle($column . '29')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($column . '30', "=" . (2/100) . "*(" . "MAX(" . $column . "19," . $column . "18)+" . $AdValoremCell . ")");
        $sheet->getStyle($column . '30')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($column . '31', "=" . $percepcion . "*(MAX(" . $column . '18,' . $column . '19) +' . $column . '28+' . $column . '29+' . $column . '30)');
        $sheet->getStyle($column . '31')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($column . '32', "=SUM(" . $column . "28:" . $column . "31)");
        $sheet->getStyle($column . '32')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
    }
    
    /**
     * Configura secciones adicionales (tributos aplicables, costos destinos)
     */
    private function setupAdditionalSections($sheet, $data, $borders, $grayColor, $greenColor)
    {
        $productos = $data['cliente']['productos'];
        $totalColumn = $this->incrementColumn('C', count($productos));
        $lastProductColumn = $this->incrementColumn('C', count($productos) - 1);
        
        // Sección de tributos aplicables
        $sheet->mergeCells('B23:E23');
        $sheet->setCellValue('B23', 'Tributos Aplicables');
        $sheet->getStyle('B23')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle('B23')->getFill()->getStartColor()->setARGB($grayColor);
        $sheet->getStyle('B23:E23')->applyFromArray($borders);
        $sheet->getStyle('B23')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $tributosLabels = [
            'B26' => 'ANTIDUMPING', 'B28' => 'AD VALOREM', 'B29' => 'IGV',
            'B30' => 'IPM', 'B31' => 'PERCEPCION', 'B32' => 'TOTAL'
        ];
        
        foreach ($tributosLabels as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }
        
        // Sección de costos destinos
        $sheet->mergeCells('B37:E37');
        $sheet->setCellValue('B37', 'Costos Destinos');
        $sheet->getStyle('B37')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle('B37')->getFill()->getStartColor()->setARGB($grayColor);
        $sheet->getStyle('B37:E37')->applyFromArray($borders);
        $sheet->getStyle('B37')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('B40', 'ITEM');
        $sheet->setCellValue('B43', 'ITEM');
        $sheet->setCellValue('B44', 'COSTO TOTAL');
        $sheet->setCellValue('B45', 'CANTIDAD');
        $sheet->setCellValue('B46', 'COSTO UNITARIO');
        $sheet->setCellValue('B47', 'COSTO SOLES');
        
        // Configurar totales finales
        $this->setupFinalTotals($sheet, $totalColumn, $lastProductColumn, $borders);
    }
    
    /**
     * Configura totales finales en las columnas
     */
    private function setupFinalTotals($sheet, $totalColumn, $lastProductColumn, $borders)
    {
        // Totales de tributos
        $sheet->setCellValue($totalColumn . '26', "=SUM(C26:" . $lastProductColumn . "26)");
        $sheet->getStyle($totalColumn . '26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($totalColumn . '28', "=SUM(C28:" . $lastProductColumn . "28)");
        $sheet->getStyle($totalColumn . '28')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($totalColumn . '29', "=SUM(C29:" . $lastProductColumn . "29)");
        $sheet->getStyle($totalColumn . '29')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($totalColumn . '30', "=SUM(C30:" . $lastProductColumn . "30)");
        $sheet->getStyle($totalColumn . '30')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($totalColumn . '31', "=SUM(C31:" . $lastProductColumn . "31)");
        $sheet->getStyle($totalColumn . '31')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        $sheet->setCellValue($totalColumn . '32', "=SUM($totalColumn" . "28:" . $totalColumn . "31)");
        $sheet->getStyle($totalColumn . '32')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        // Totales generales
        $sheet->setCellValue($totalColumn . '15', "=SUM(C15:" . $lastProductColumn . "15)");
        $sheet->getStyle($totalColumn . '15')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        $sheet->getStyle($totalColumn . '15')->getFont()->setBold(true);
        
        $sheet->setCellValue($totalColumn . '43', "Total");
        $sheet->setCellValue($totalColumn . '44', "=SUM(C44:" . $lastProductColumn . "44)");
        $sheet->getStyle($totalColumn . '44')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        
        // Aplicar bordes finales
        $lastColumnWithBorders = $this->incrementColumn($totalColumn, 1);
        $sheet->getStyle('B5:' . $lastColumnWithBorders . '19')->applyFromArray($borders);
        $sheet->getStyle('B28:' . $lastColumnWithBorders . '32')->applyFromArray($borders);
        $sheet->getStyle('B40:' . $lastColumnWithBorders . '47')->applyFromArray($borders);
    }
    
    /**
     * Configura la hoja principal con datos del cliente y referencias
     */
    private function setupMainSheetModern($spreadsheet, $sheet, $data, $idContenedor)
    {
        $spreadsheet->setActiveSheetIndex(0); // Activar hoja principal
        
        // Configurar información básica del cliente
        $sheet->mergeCells('C8:C9');
        $sheet->getStyle('C8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C8')->getAlignment()->setWrapText(true);
        $sheet->setCellValue('C8', $data['cliente']['nombre']);
        $sheet->setCellValue('C10', $data['cliente']['dni']);
        $sheet->setCellValue('C11', $data['cliente']['telefono']);
        $sheet->setCellValue('F11', $data['cliente']['tipo_cliente']);
        
        // Configurar peso y volumen (validar que sea numérico)
        $pesoTotal = isset($data['cliente']['productos'][0]['peso']) && is_numeric($data['cliente']['productos'][0]['peso']) 
            ? (float)$data['cliente']['productos'][0]['peso'] : 0;
        $sheet->setCellValue('J9', $pesoTotal >= 1000 ? ($pesoTotal / 1000) . " Tn" : $pesoTotal . " Kg");
        $sheet->setCellValue('I11', "CBM");
        $sheet->getStyle('J11')->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Configurar referencias y fórmulas principales
        $this->setupMainSheetFormulas($sheet, $data);
        
        // Configurar productos en la hoja principal
        $this->setupProductsInMainSheetModern($sheet, $data);
        
        // Configurar mensaje de WhatsApp
        $this->setupWhatsAppMessageModern($sheet, $data);
    }
    
    /**
     * Configura las fórmulas principales en la hoja principal
     */
    private function setupMainSheetFormulas($sheet, $data)
    {
        $productsCount = count($data['cliente']['productos']);
        $columnaIndex = Coordinate::stringFromColumnIndex($productsCount + 2);
        
        // Configurar CBM en J11 como FÓRMULA que referencia la hoja de tributos (como en original)
        // En el original: $objPHPExcel->getActiveSheet()->setCellValue('J11', "='3'!" . $CBMTotal);
        $CBMTotalCell = $columnaIndex . "7"; // Celda de CBM en la hoja de tributos
        $sheet->setCellValue('J11', "='2'!" . $CBMTotalCell);
        $sheet->getStyle('J11')->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Configurar valores principales
        $sheet->setCellValue('K14', "='2'!" . $columnaIndex . "11"); // FOB
        $sheet->setCellValue('K15', "='2'!" . $columnaIndex . "14 + '2'!" . $columnaIndex . "17"); // Flete + Seguro
        $sheet->setCellValue('K20', "='2'!" . $columnaIndex . "28"); // Ad Valorem
        $sheet->setCellValue('K21', "='2'!" . $columnaIndex . "29"); // IGV
        $sheet->setCellValue('K22', "='2'!" . $columnaIndex . "30"); // IPM
        $sheet->setCellValue('K25', "='2'!" . $columnaIndex . "31"); // Percepción
        
        // Calcular tarifa
        $tarifaValue = $this->calculateTarifaByTipoCliente(
            $data['cliente']['tipo_cliente'], 
            $data['cliente']['volumen'], 
            $data['cliente']['tarifa']
        );
        
        // Configurar fórmulas principales
        $sheet->setCellValue('K29', "=K14"); // FOB
        $sheet->setCellValue('K30', "=IF(J11<1, " . $tarifaValue . ", " . $tarifaValue . "*J11)"); // Logística
        $sheet->setCellValue('K31', "=K20+K21+K22+K25"); // Impuestos totales
        $sheet->setCellValue('K32', "=K29+K30+K31"); // Total final
        
        // Verificar si hay antidumping
        $antidumpingSum = 0;
        foreach ($data['cliente']['productos'] as $producto) {
            $antidumping = is_numeric($producto["antidumping"]) ? (float)$producto["antidumping"] : 0;
            $cantidad = is_numeric($producto["cantidad"]) ? (float)$producto["cantidad"] : 0;
            $antidumpingSum += $antidumping * $cantidad;
        }
        
        if ($antidumpingSum > 0) {
            // Insertar fila para antidumping
            $sheet->insertNewRowBefore(23, 1);
            $sheet->setCellValue('B23', "ANTIDUMPING");
            $sheet->setCellValue('K23', $antidumpingSum);
            
            // Aplicar estilo amarillo
            $yellowColor = 'FFFF33';
            $sheet->getStyle('B23:L23')->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle('B23:L23')->getFill()->getStartColor()->setARGB($yellowColor);
            
            $sheet->setCellValue('K24', "=SUM(K20:K23)");
            
            // Ajustar fórmulas para incluir antidumping
            $sheet->setCellValue('K30', "=K14"); // FOB
            $sheet->setCellValue('K31', "=IF(J11<1, " . $tarifaValue . ", " . $tarifaValue . "*J11)"); // Logística
            $sheet->setCellValue('K32', "=K20+K21+K22+K23+K25"); // Impuestos con antidumping
            $sheet->setCellValue('K33', "=K30+K31+K32"); // Total final con antidumping
        }
    }
    
    /**
     * Configura los productos en las filas 36-39 de la hoja principal
     */
    private function setupProductsInMainSheetModern($sheet, $data)
    {
        $productos = $data['cliente']['productos'];
        $productsCount = count($productos);
        
        // Colores y estilos
        $greenColor = "009999";
        $whiteColor = "FFFFFF";
        $borders = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        
        // Limpiar filas 36-39 primero
        for ($row = 36; $row <= 39; $row++) {
            for ($col = 1; $col <= 12; $col++) {
                $cell = Coordinate::stringFromColumnIndex($col) . $row;
                $sheet->setCellValue($cell, '');
                $sheet->getStyle($cell)->applyFromArray([]);
            }
        }
        
        $InitialColumn = 'C';
        
        // Configurar cada producto
        for ($index = 0; $index < $productsCount; $index++) {
            $row = 36 + $index;
            $column = $this->incrementColumn($InitialColumn, $index);
            
            if ($row <= 39) { // Solo para las primeras 4 filas por defecto
                $sheet->setCellValue('B' . $row, $index + 1);
                $sheet->setCellValue('C' . $row, $productos[$index]["nombre"]);
                $sheet->setCellValue('F' . $row, "='2'!" . $column . '10'); // Cantidad
                $sheet->setCellValue('G' . $row, "='2'!" . $column . '8'); // Precio unitario
                $sheet->setCellValue('I' . $row, "='2'!" . $column . '46'); // Costo unitario
                $sheet->setCellValue('J' . $row, "='2'!" . $column . '44'); // Costo total
                $sheet->setCellValue('K' . $row, "='2'!" . $column . '47'); // Costo en soles
                
                // Combinar celdas
                $sheet->mergeCells('C' . $row . ':E' . $row);
                $sheet->mergeCells('G' . $row . ':H' . $row);
                $sheet->mergeCells('K' . $row . ':L' . $row);
                
                // Aplicar estilos
                $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID);
                $sheet->getStyle('K' . $row)->getFill()->getStartColor()->setARGB($greenColor);
                $sheet->getStyle('K' . $row)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                
                // Aplicar formatos
                $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                $sheet->getStyle('K' . $row)->getNumberFormat()->setFormatCode('"S/." #,##0.00_-');
                
                // Bordes
                $sheet->getStyle('B' . $row . ':L' . $row)->applyFromArray($borders);
            }
        }
        
        // Configurar fila de totales
        if ($productsCount > 0) {
            $lastRow = min(39, 36 + $productsCount - 1) + 1;
            if ($lastRow <= 40) {
                // Mergear celdas de la fila de totales (igual que las filas de productos)
                $sheet->mergeCells('B' . $lastRow . ':E' . $lastRow);
                $sheet->mergeCells('G' . $lastRow . ':H' . $lastRow);
                $sheet->mergeCells('K' . $lastRow . ':L' . $lastRow);
                
                // Establecer valores
                $sheet->setCellValue('B' . $lastRow, "TOTAL");
                $sheet->setCellValue('F' . $lastRow, "=SUM(F36:F" . ($lastRow - 1) . ")");
                $sheet->setCellValue('J' . $lastRow, "=SUM(J36:J" . ($lastRow - 1) . ")");
                
                // Aplicar bordes a toda la fila
                $sheet->getStyle('B' . $lastRow . ':L' . $lastRow)->applyFromArray($borders);
                
                // Aplicar estilos
                $sheet->getStyle('B' . $lastRow)->getFont()->setBold(true);
                $sheet->getStyle('B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F' . $lastRow)->getFont()->setBold(true);
                $sheet->getStyle('F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J' . $lastRow)->getFont()->setBold(true);
                $sheet->getStyle('J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                
                // Aplicar tamaño de fuente
                $sheet->getStyle('B' . $lastRow . ':L' . $lastRow)->getFont()->setSize(11);
            }
        }
        
        // Limpiar filas no utilizadas (eliminar bordes de filas vacías)
        if ($productsCount < 4) {
            for ($row = (36 + $productsCount); $row <= 39; $row++) {
                // Remover bordes de las filas no utilizadas
                $sheet->getStyle('B' . $row . ':L' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                    ],
                ]);
            }
        }
    }
    
    /**
     * Configura el mensaje de WhatsApp
     */
    private function setupWhatsAppMessageModern($sheet, $data)
    {
        $clientName = $data['cliente']['nombre'];
        $message = "Hola " . $clientName . " 😁 un gusto saludarte!\n" .
                  "A continuación te envío la cotización final de tu importación📋📦.\n" .
                  "🙋‍♂️ PAGO PENDIENTE :\n" .
                  "Pronto le aviso nuevos avances, que tengan buen día🚢\n" .
                  "Último día de pago:";
        
        $sheet->setCellValue('N20', $message);
    }
    
    /**
     * Valida y sanitiza los datos para evitar errores de valores no numéricos
     */
    private function validateAndSanitizeData($data)
    {
        // Validar datos del cliente
        if (!isset($data['cliente'])) {
            throw new \Exception('Datos de cliente no encontrados');
        }
        
        // Sanitizar valores numéricos del cliente
        $data['cliente']['tarifa'] = is_numeric($data['cliente']['tarifa'] ?? 0) ? (float)$data['cliente']['tarifa'] : 0;
        $data['cliente']['volumen'] = is_numeric($data['cliente']['volumen'] ?? 0) ? (float)$data['cliente']['volumen'] : 0;
        
        // Validar y sanitizar productos
        if (!isset($data['cliente']['productos']) || !is_array($data['cliente']['productos'])) {
            throw new \Exception('Productos no encontrados o formato inválido');
        }
        
        foreach ($data['cliente']['productos'] as &$producto) {
            // Sanitizar valores numéricos de cada producto
            $producto['precio_unitario'] = is_numeric($producto['precio_unitario'] ?? 0) ? (float)$producto['precio_unitario'] : 0;
            $producto['valoracion'] = is_numeric($producto['valoracion'] ?? 0) ? (float)$producto['valoracion'] : 0;
            $producto['cantidad'] = is_numeric($producto['cantidad'] ?? 0) ? (float)$producto['cantidad'] : 0;
            $producto['antidumping'] = is_numeric($producto['antidumping'] ?? 0) ? (float)$producto['antidumping'] : 0;
            $producto['ad_valorem'] = is_numeric($producto['ad_valorem'] ?? 0) ? (float)$producto['ad_valorem'] : 0;
            $producto['percepcion'] = is_numeric($producto['percepcion'] ?? 0.035) ? (float)$producto['percepcion'] : 0.035;
            $producto['peso'] = is_numeric($producto['peso'] ?? 0) ? (float)$producto['peso'] : 0;
            $producto['cbm'] = is_numeric($producto['cbm'] ?? 0) ? (float)$producto['cbm'] : 0;
            
            // Asegurar que el nombre del producto no esté vacío
            $producto['nombre'] = trim($producto['nombre'] ?? 'Producto sin nombre');
            if (empty($producto['nombre'])) {
                $producto['nombre'] = 'Producto sin nombre';
            }
        }
        unset($producto); // Liberar referencia
        
        Log::info('Datos sanitizados exitosamente para cliente: ' . ($data['cliente']['nombre'] ?? 'Sin nombre'));
        
        return $data;
    }
    
    /**
     * Registra información sobre la plantilla cargada para debugging
     */
    private function logTemplateInfo($spreadsheet)
    {
        try {
            $sheetCount = $spreadsheet->getSheetCount();
            Log::info('Información de plantilla cargada:');
            Log::info('- Total de hojas: ' . $sheetCount);
            
            for ($i = 0; $i < $sheetCount; $i++) {
                $sheet = $spreadsheet->getSheet($i);
                $sheetTitle = $sheet->getTitle();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                
                Log::info('- Hoja ' . $i . ': "' . $sheetTitle . '" (Filas: ' . $highestRow . ', Columnas: ' . $highestColumn . ')');
                
                // Log de algunas celdas clave para verificar el contenido
                if ($i === 0) { // Solo para la hoja principal
                    $sampleCells = ['A1', 'B1', 'C8', 'F11', 'J9', 'K14'];
                    foreach ($sampleCells as $cell) {
                        try {
                            $value = $sheet->getCell($cell)->getValue();
                            if (!empty($value)) {
                                Log::info('  - Celda ' . $cell . ': "' . substr($value, 0, 50) . '"');
                            }
                        } catch (\Exception $e) {
                            // Ignorar errores de celdas individuales
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error al obtener información de plantilla: ' . $e->getMessage());
        }
    }
}
