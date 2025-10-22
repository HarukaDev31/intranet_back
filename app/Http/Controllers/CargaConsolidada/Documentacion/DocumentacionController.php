<?php

namespace App\Http\Controllers\CargaConsolidada\Documentacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\DocumentacionFolder;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\DocumentacionFile;
use App\Models\Usuario;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\BaseDatos\ProductosController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DocumentacionController extends Controller
{
    private $STATUS_NOT_CONTACTED = "NC";
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
    /**
     * Obtiene las carpetas de documentación y sus archivos para un contenedor específico
     */
    public function getDocumentationFolderFiles($id)
    {
        try {
            // Obtener el usuario autenticado
            $user = JWTAuth::user();
            $userGrupo = $user ? $user->getNombreGrupo() : null;
            $roleDocumentacion = 'Documentacion'; // Definir el rol de documentación

            // Obtener la URL de la lista de embarque del contenedor
            $contenedor = Contenedor::find($id);
            $listaEmbarqueUrl = $contenedor ? $contenedor->lista_embarque_url : null;

            // Obtener las carpetas con sus archivos usando Eloquent
            $folders = DocumentacionFolder::with(['files' => function ($query) use ($id) {
                $query->where('id_contenedor', $id);
            }])
                ->forContenedor($id)
                ->forUserGroup($userGrupo, $roleDocumentacion)
                ->get();

            // Transformar los datos para mantener la estructura original
            $result = [];
            foreach ($folders as $folder) {
                $folderData = $folder->toArray();

                // Agregar la URL de lista de embarque
                $folderData['lista_embarque_url'] = $listaEmbarqueUrl;
                Log::info('lista_embarque_url: ' . $listaEmbarqueUrl);
                Log::info('folder->id: ' . $folder->id);
                // Si el folder id es 1, establecer file_url con la URL de lista de embarque
                if ($folder->id == 1) {
                    $folderData['file_url'] = $listaEmbarqueUrl;
                }
                
                // Procesar los archivos de la carpeta
                if ($folder->files->count() > 0) {
                    foreach ($folder->files as $file) {
                        $fileData = [
                            'id' => $folder->id,
                            'id_file' => $file->id,
                            'type' => $file->file_type,
                            'lista_embarque_url' => $listaEmbarqueUrl
                        ];
                        if ($folder->id == 1) {
                            $fileData['file_url'] = $listaEmbarqueUrl;
                        }else{
                            $fileData['file_url'] = $this->generateImageUrl($file->file_url);
                        }
                      

                        // Combinar datos de la carpeta con datos del archivo
                        $result[] = array_merge($folderData, $fileData);
                    }
                } else {
                    // Carpeta sin archivos
                    $folderData['id_file'] = null;
                    $folderData['file_url'] = null;
                    if ($folder->id == 1) {
                        $folderData['file_url'] = $listaEmbarqueUrl;
                    }
                    $folderData['type'] = null;
                    $result[] = $folderData;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Carpetas de documentación obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener carpetas de documentación: ' . $e->getMessage()
            ], 500);
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
        //if ruta contains app.url but not /storage/ then add /storage/
        if (strpos($ruta, config('app.url')) !== false && strpos($ruta, '/storage/') === false) {
            $ruta = config('app.url') . '/storage/' . $ruta;
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

        // Codificar toda la ruta incluyendo el nombre del archivo para caracteres especiales como #
        $rutaEncoded = rawurlencode($ruta);

        return $baseUrl . '/' . $storagePath . '/' . $rutaEncoded;
    }

    /**
     * Actualiza la documentación del cliente
     */
    public function updateClienteDocumentacion(Request $request, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $data = $request->all();
            $files = $request->file();

            // Validar datos requeridos
            $request->validate([
                'id' => 'required|integer',
                'idProveedor' => 'required|integer'
            ]);

            $idCotizacion = $data['id'];
            $idProveedor = $idProveedor;
            $proveedor = CotizacionProveedor::find($idProveedor);
            if (!$proveedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                ], 404);
            }

            // Procesar archivo comercial
            if ($request->hasFile('file_comercial')) {
                $this->processFileUpload($proveedor, 'factura_comercial', $request->file('file_comercial'), $data);
            } else {
                unset($data['file_comercial']);
            }

            // Procesar excel de confirmación
            if ($request->hasFile('excel_confirmacion')) {
                $this->processFileUpload($proveedor, 'excel_confirmacion', $request->file('excel_confirmacion'), $data);
            } else {
                unset($data['excel_confirmacion']);
            }

            // Procesar packing list
            if ($request->hasFile('packing_list')) {
                $this->processFileUpload($proveedor, 'packing_list', $request->file('packing_list'), $data);
            } else {
                unset($data['packing_list']);
            }

            // Remover campos que no se deben actualizar
            unset($data['id'], $data['idProveedor']);
            $proveedor->update($data);
            $this->updateCotizacionTotals($idCotizacion);

            return response()->json([
                'success' => true,
                'message' => 'Documentación actualizada correctamente',
                'data' => $proveedor
            ]);
        } catch (\Exception $e) {
            Log::error('Error en updateClienteDocumentacion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar documentación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa la subida de un archivo
     */
    private function processFileUpload($proveedor, $fieldName, $file, &$data)
    {
        try {
            // Eliminar archivo anterior si existe
            if ($proveedor->$fieldName && Storage::exists($proveedor->$fieldName)) {
                Storage::delete($proveedor->$fieldName);
            }

            // Generar nombre único para el archivo
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Ruta de almacenamiento
            $path = 'assets/images/agentecompra/';

            // Guardar archivo
            $filePath = $file->storeAs($path, $filename, 'public');

            // Actualizar campo en los datos
            $data[$fieldName] = $filePath;
        } catch (\Exception $e) {
            Log::error("Error al procesar archivo {$fieldName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza los totales de valor y volumen en la cotización
     */
    private function updateCotizacionTotals($idCotizacion)
    {
        try {
            // Calcular totales
            $totales = CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->selectRaw('SUM(COALESCE(valor_doc, 0)) as total_valor_doc, SUM(COALESCE(volumen_doc, 0)) as total_volumen_doc')
                ->first();

            // Actualizar cotización
            Cotizacion::where('id', $idCotizacion)->update([
                'valor_doc' => $totales->total_valor_doc ?? 0,
                'volumen_doc' => $totales->total_volumen_doc ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar totales de cotización: ' . $e->getMessage());
            throw $e;
        }
    }
    public function deleteProveedorFacturaComercial(Request $request, $idProveedor)
    {
        $proveedor = CotizacionProveedor::find($idProveedor);
        $proveedor->factura_comercial = null;
        $proveedor->save();
        return response()->json([
            'success' => true,
            'message' => 'Factura comercial eliminada correctamente'
        ]);
    }

    public function deleteProveedorExcelConfirmacion(Request $request, $idProveedor)
    {
        $proveedor = CotizacionProveedor::find($idProveedor);
        $proveedor->excel_confirmacion = null;
        $proveedor->save();
        return response()->json([
            'success' => true,
            'message' => 'Excel de confirmación eliminada correctamente'
        ]);
    }


    public function deleteProveedorPackingList(Request $request, $idProveedor)
    {
        $proveedor = CotizacionProveedor::find($idProveedor);
        $proveedor->packing_list = null;
        $proveedor->save();
        return response()->json([
            'success' => true,
            'message' => 'Packing list eliminada correctamente'
        ]);
    }

    /**
     * Sube un archivo de documentación
     */
    public function uploadFileDocumentation(Request $request)
    {
        try {
            $idFolder = $request->idFolder;
            $idContenedor = $request->idContenedor;
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar que se haya enviado un archivo
            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se ha enviado ningún archivo'
                ], 400);
            }

            $file = $request->file('file');

            $maxFileSize = 200 * 1024 * 1024;
            if ($file->getSize() > $maxFileSize) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo excede el tamaño máximo permitido (100MB)'
                ], 400);
            }

            // Validar extensión del archivo
            $fileExtension = strtolower($file->getClientOriginalExtension());



            // Generar nombre único para el archivo
            $filename = time() . '_' . uniqid() . '.' . $fileExtension;

            // Ruta de almacenamiento
            $path = 'agentecompra/documentacion/';

            // Guardar archivo
            $fileUrl = $file->storeAs($path, $filename, 'public');

            // Log para debug
            Log::info('Archivo guardado:', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $fileUrl,
                'full_storage_path' => storage_path('app/public/' . $fileUrl),
                'exists' => Storage::disk('public')->exists($fileUrl)
            ]);

            // Eliminar archivo anterior si existe
            DocumentacionFile::where('id_folder', $idFolder)
                ->where('id_contenedor', $idContenedor)
                ->delete();

            // Crear nuevo registro en la base de datos
            $documentacionFile = new DocumentacionFile();
            $documentacionFile->id_folder = $idFolder;
            $documentacionFile->file_url = $fileUrl;
            $documentacionFile->id_contenedor = $idContenedor;

            $documentacionFile->save();

            // Obtener información de la carpeta para la notificación
            $folder = DocumentacionFolder::find($idFolder);
            $folderName = $folder ? $folder->folder_name : 'Documento';

            // Obtener información del contenedor
            $contenedor = Contenedor::find($idContenedor);
            $contenedorNombre = $contenedor ? $contenedor->carga : 'Contenedor';

            // Crear notificación para el perfil Coordinación
            if ($folder) {
                Notificacion::create([
                    'titulo' => 'Nuevo Documento Subido',
                    'mensaje' => "Se ha subido un nuevo documento en la carpeta '{$folderName}' para el contenedor {$contenedorNombre}",
                    'descripcion' => "Carpeta: {$folderName} | Contenedor: {$contenedorNombre} | Archivo: {$file->getClientOriginalName()} | Usuario: {$user->No_Nombres_Apellidos}",
                    'modulo' => Notificacion::MODULO_CARGA_CONSOLIDADA,
                    'rol_destinatario' => Usuario::ROL_COORDINACION,
                    'navigate_to' => "cargaconsolidada/completados/documentacion/{$idContenedor}",
                    'navigate_params' => [],
                    'tipo' => Notificacion::TIPO_INFO,
                    'icono' => 'mdi:file-upload',
                    'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                    'referencia_tipo' => 'documentacion_upload',
                    'referencia_id' => $idContenedor,
                    'activa' => true,
                    'creado_por' => $user->ID_Usuario,
                    'configuracion_roles' => [
                        Usuario::ROL_COORDINACION => [
                            'titulo' => 'Documento Subido - Revisar',
                            'mensaje' => "Nuevo documento en '{$folderName}' para revisar",
                            'descripcion' => "Contenedor: {$contenedorNombre} | Archivo: {$file->getClientOriginalName()}"
                        ]
                    ]
                ]);
            }

            // Si es el folder 9 (productos), procesar Excel
            if ($idFolder == 9 && $fileExtension == 'xlsx') {
                //instance  ProductosController and call importExcel
                $productosController = new ProductosController();
                // Corregido: crear un nuevo Request correctamente y pasar los datos necesarios
                $newRequest = new Request([
                    'excel_file' => $file,
                    'idContenedor' => $idContenedor
                ]);
                $productosController->importExcel($newRequest);
            }

            return response()->json([
                'success' => true,
                'message' => 'Archivo subido correctamente',
                'data' => [
                    'file_url' => $fileUrl,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en uploadFileDocumentation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importa productos desde Excel (folder 9)
     */
    private function importarProductosDesdeExcel($file, $idContenedor)
    {
        try {
            // Aquí implementarías la lógica de importación de Excel
            // Por ahora solo registramos que se procesó
            Log::info('Procesando Excel de productos para contenedor: ' . $idContenedor);

            // Ejemplo básico de procesamiento con Laravel Excel
            // Excel::import(new ProductosImport($idContenedor), $file);

        } catch (\Exception $e) {
            Log::error('Error al importar productos desde Excel: ' . $e->getMessage());
        }
    }

    /**
     * Descarga la factura comercial procesada
     */
    public function downloadFacturaComercial($idContenedor)
    {
        try {
            // Buscar archivos de documentación
            $facturaComercial = $this->getDocumentacionFile($idContenedor, 'Factura Comercial');
            if (!$facturaComercial) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró la factura comercial'
                ], 404);
            }

            $packingList = $this->getDocumentacionFile($idContenedor, 'Packing List');
            if (!$packingList) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró el packing list'
                ], 404);
            }

            $listaPartidas = $this->getDocumentacionFile($idContenedor, 'Lista de Partidas');
            if (!$listaPartidas) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró la lista de partidas'
                ], 404);
            }

            // Validar extensiones de archivo
            if (!$this->isExcelFile($facturaComercial->file_url)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La Factura Comercial no es un archivo de excel'
                ], 400);
            }

            if (!$this->isExcelFile($packingList->file_url)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El Packing List no es un archivo de excel'
                ], 400);
            }

            if (!$this->isExcelFile($listaPartidas->file_url)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La Lista de Partidas no es un archivo de excel'
                ], 400);
            }

            // Cargar archivos Excel
            $facturaPath = $this->getLocalPath($facturaComercial->file_url);
            $packingPath = $this->getLocalPath($packingList->file_url);
            $listaPath = $this->getLocalPath($listaPartidas->file_url);

            $objPHPExcel = IOFactory::load($facturaPath);
            $objPHPExcelPacking = IOFactory::load($packingPath);
            $objPHPExcelListaPartidas = IOFactory::load($listaPath);

            // Obtener datos del sistema
            $dataSystem = $this->getSystemData($idContenedor);
            Log::info('Datos del sistema: ' . json_encode($dataSystem));
            // Procesar Excel
            $processedExcel = $this->processExcelFiles(
                $objPHPExcel,
                $objPHPExcelPacking,
                $objPHPExcelListaPartidas,
                $dataSystem
            );

            // Generar archivo de salida
            $writer = new Xlsx($processedExcel);
            $outputPath = storage_path('app/public/temp/factura_procesada_' . $idContenedor . '.xlsx');

            // Crear directorio si no existe
            if (!file_exists(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            $writer->save($outputPath);


            return response()->download($outputPath, 'factura_procesada_' . $idContenedor . '.xlsx')
                ->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Error en downloadFacturaComercial: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar archivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene archivo de documentación por nombre de carpeta
     */
    private function getDocumentacionFile($idContenedor, $folderName)
    {
        return DB::table('contenedor_consolidado_documentacion_folders as main')
            ->join('contenedor_consolidado_documentacion_files as files', 'files.id_folder', '=', 'main.id')
            ->select('main.*', 'files.id as id_file', 'files.file_url')
            ->where('files.id_contenedor', $idContenedor)
            ->where('main.folder_name', $folderName)
            ->first();
    }

    /**
     * Valida si un archivo es Excel
     */
    private function isExcelFile($fileUrl)
    {
        $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
        return in_array($extension, ['xls', 'xlsx', 'xlsm']);
    }

    /**
     * Obtiene datos del sistema para el contenedor
     */
    private function getSystemData($idContenedor)
    {
        return DB::table('contenedor_consolidado_cotizacion as main')
            ->join('contenedor_consolidado_tipo_cliente', 'main.id_tipo_cliente', '=', 'contenedor_consolidado_tipo_cliente.id')
            ->select('nombre', 'volumen', 'volumen_doc', 'valor_doc', 'valor_cot', 'volumen_china', 'name', 'vol_selected')
            ->where('id_contenedor', $idContenedor)
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereNotNull('estado_cliente')
            ->whereNull('id_cliente_importacion')
            ->get();
    }

    /**
     * Procesa los archivos Excel
     */
    private function processExcelFiles($facturaExcel, $packingExcel, $listaPartidasExcel, $dataSystem)
    {
        try {
            // Crear mapeo de items a clientes desde packing list
            $itemToClientMap = $this->createItemToClientMap($packingExcel);

            // Procesar primera hoja
            $sheet0 = $facturaExcel->getSheet(0);
            $lastClientInfo = $this->processFirstSheet($sheet0, $itemToClientMap, $dataSystem, $listaPartidasExcel);

            // Procesar hojas adicionales
            $this->processAdditionalSheets($facturaExcel, $itemToClientMap, $dataSystem, $listaPartidasExcel, $lastClientInfo);

            // Aplicar estilos finales
            $this->applyFinalStyles($sheet0);

            return $facturaExcel;
        } catch (\Exception $e) {
            Log::error('Error procesando Excel: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea mapeo de items a clientes desde todas las hojas del packing list
     */
    private function createItemToClientMap($packingExcel)
    {
        $itemToClientMap = [];
        $sheetCount = $packingExcel->getSheetCount();

        Log::info('Procesando packing list con ' . $sheetCount . ' hojas');

        // Procesar todas las hojas del packing list
        for ($sheetIndex = 0; $sheetIndex < $sheetCount; $sheetIndex++) {
            $sheet = $packingExcel->getSheet($sheetIndex);
            $highestRow = $sheet->getHighestRow();

            Log::info('Procesando hoja ' . $sheetIndex . ' del packing list, filas: ' . $highestRow);

            for ($row = 26; $row <= $highestRow; $row++) {
                $itemId = $sheet->getCell('B' . $row)->getValue();
                $client = $sheet->getCell('C' . $row)->getValue();

                // Primero agregar datos válidos si existen
                if (!empty($itemId) && !empty($client)) {
                    // Verificar si no contienen "TOTAL" antes de agregar
                    if (stripos(trim($itemId), "TOTAL") === false && stripos(trim($client), "TOTAL") === false) {
                        $cleanItemId = trim($itemId);
                        $cleanClient = trim($client);

                        // Manejar duplicados: crear claves únicas para items duplicados
                        $originalKey = $cleanItemId;
                        $counter = 1;

                        // Si ya existe este itemId, crear una variación única
                        while (isset($itemToClientMap[$cleanItemId])) {
                            Log::info('ItemId duplicado encontrado: ' . $originalKey . ' (cliente existente: ' . $itemToClientMap[$originalKey] . ', nuevo cliente: ' . $cleanClient . ')');
                            $cleanItemId = $originalKey . '_' . $counter;
                            $counter++;
                        }

                        // Agregar múltiples variaciones del itemId para mayor compatibilidad
                        $itemToClientMap[$cleanItemId] = $cleanClient;
                        $itemToClientMap[strtoupper($cleanItemId)] = $cleanClient;
                        $itemToClientMap[strtolower($cleanItemId)] = $cleanClient;

                        // También mantener el original si es diferente
                        if ($cleanItemId !== $originalKey) {
                            // Crear array de clientes para el itemId original si no existe
                            if (!isset($itemToClientMap[$originalKey . '_ALL'])) {
                                $itemToClientMap[$originalKey . '_ALL'] = [];
                            }
                            if (!is_array($itemToClientMap[$originalKey . '_ALL'])) {
                                $itemToClientMap[$originalKey . '_ALL'] = [$itemToClientMap[$originalKey]];
                            }
                            $itemToClientMap[$originalKey . '_ALL'][] = $cleanClient;
                        }

                        Log::info('Mapeado: ' . $cleanItemId . ' -> ' . $cleanClient);
                    }
                }

                // Luego verificar si debemos parar (después de procesar la fila actual)
                if (stripos(trim($itemId), "TOTAL") !== false || stripos(trim($client), "TOTAL") !== false) {
                    Log::info('Encontrado TOTAL en hoja ' . $sheetIndex . ', fila ' . $row);
                    break;
                }
            }
        }

        Log::info('Mapeo completo creado con ' . count($itemToClientMap) . ' elementos');
        Log::info('Contenido del mapeo: ' . json_encode($itemToClientMap, JSON_UNESCAPED_UNICODE));
        return $itemToClientMap;
    }

    /**
     * Busca el cliente para un itemId con múltiples estrategias de búsqueda
     */
    private function findClientForItem($itemToClientMap, $itemN)
    {
        if (empty($itemN)) {
            Log::warning('ItemN está vacío, retornando cadena vacía');
            return '';
        }

        $cleanItemN = trim($itemN);
        Log::info('🔍 BUSCANDO CLIENTE PARA ITEM: "' . $cleanItemN . '"');
        Log::info('📋 Total items en mapeo: ' . count($itemToClientMap));

        // Estrategia 1: Búsqueda exacta
        if (isset($itemToClientMap[$cleanItemN])) {
            Log::info('✅ Cliente encontrado (exacto): ' . $cleanItemN . ' -> ' . $itemToClientMap[$cleanItemN]);
            return $itemToClientMap[$cleanItemN];
        } else {
            Log::info('❌ No encontrado en búsqueda exacta para: "' . $cleanItemN . '"');
        }

        // Estrategia 2: Búsqueda sin considerar mayúsculas/minúsculas
        if (isset($itemToClientMap[strtoupper($cleanItemN)])) {
            Log::info('Cliente encontrado (mayúsculas): ' . $cleanItemN . ' -> ' . $itemToClientMap[strtoupper($cleanItemN)]);
            return $itemToClientMap[strtoupper($cleanItemN)];
        }

        if (isset($itemToClientMap[strtolower($cleanItemN)])) {
            Log::info('Cliente encontrado (minúsculas): ' . $cleanItemN . ' -> ' . $itemToClientMap[strtolower($cleanItemN)]);
            return $itemToClientMap[strtolower($cleanItemN)];
        }

        // Estrategia 3: Búsqueda parcial (contiene) - SOLO para números
        if (is_numeric($cleanItemN)) {
            foreach ($itemToClientMap as $mappedItemId => $client) {
                // Solo buscar coincidencias parciales si ambos son números y tienen longitud similar
                if (is_numeric($mappedItemId) && abs(strlen($cleanItemN) - strlen($mappedItemId)) <= 1) {
                    if (stripos($mappedItemId, $cleanItemN) !== false || stripos($cleanItemN, $mappedItemId) !== false) {
                        Log::info('Cliente encontrado (parcial numérico): ' . $cleanItemN . ' -> ' . $client . ' (mapeado: ' . $mappedItemId . ')');
                        return $client;
                    }
                }
            }
        }

        // Estrategia 4: Búsqueda removiendo espacios y caracteres especiales - SOLO si hay coincidencia exacta
        $normalizedItemN = preg_replace('/[^a-zA-Z0-9]/', '', $cleanItemN);
        foreach ($itemToClientMap as $mappedItemId => $client) {
            $normalizedMapped = preg_replace('/[^a-zA-Z0-9]/', '', $mappedItemId);
            if (strcasecmp($normalizedItemN, $normalizedMapped) === 0 && strlen($normalizedItemN) > 0) {
                Log::info('Cliente encontrado (normalizado): ' . $cleanItemN . ' -> ' . $client . ' (mapeado: ' . $mappedItemId . ')');
                return $client;
            }
        }

        Log::warning('❌ Cliente no encontrado para itemId: ' . $cleanItemN);

        // Mostrar items similares para debug
        $numericKeys = array_filter(array_keys($itemToClientMap), 'is_numeric');
        sort($numericKeys, SORT_NUMERIC);
        $nearbyItems = [];
        $targetNum = is_numeric($cleanItemN) ? intval($cleanItemN) : 0;

        foreach ($numericKeys as $key) {
            $keyNum = intval($key);
            if (abs($keyNum - $targetNum) <= 3) { // Mostrar items ±3 del target
                $nearbyItems[] = $key . ' -> ' . $itemToClientMap[$key];
            }
        }

        if (!empty($nearbyItems)) {
            Log::warning('🔍 Items cercanos disponibles: ' . implode(', ', $nearbyItems));
        }

        // Si no hay cliente, devolver cadena vacía en lugar de texto
        return '';
    }

    /**
     * Procesa la primera hoja
     */
    private function processFirstSheet($sheet, $itemToClientMap, $dataSystem, $listaPartidasExcel)
    {
        // Insertar columnas nuevas
        $sheet->insertNewColumnBefore('C', 2);
        $sheet->setCellValue('D25', 'CLIENTE');
        $sheet->setCellValue('C25', 'TIPO DE CLIENTE');

        try {
            $sheet->removeColumn('E');
        } catch (\Exception $e) {
            Log::warning('Error removiendo columna E: ' . $e->getMessage());
        }

        $sheet->setCellValue('R25', 'ADVALOREM');
        $sheet->setCellValue('S25', 'ANTIDUMPING');
        $sheet->setCellValue('T25', 'VOL. SISTEMA');

        // Aplicar estilos de encabezado
        $this->applyHeaderStyles($sheet);

        // Procesar filas de datos y obtener información del último cliente
        $lastClientInfo = $this->processDataRows($sheet, $itemToClientMap, $dataSystem, $listaPartidasExcel, 26);

        return $lastClientInfo;
    }

    /**
     * Aplica estilos de encabezado
     */
    private function applyHeaderStyles($sheet)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ]
            ]
        ];

        $sheet->getStyle('A25:Z25')->getFont()->setBold(true);
        $sheet->getStyle('A25:Z25')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('R25:V25')->applyFromArray($styleArray);
    }

    /**
     * Procesa filas de datos
     */
    private function processDataRows($sheet, $itemToClientMap, $dataSystem, $listaPartidasExcel, $startRow)
    {
        $highestRow = $sheet->getHighestRow();
        $currentClient = "";
        $clientStartRow = 0;
        $clientEndRow = 0;
        $pendingMerge = [];

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $itemN = $sheet->getCell('B' . $row)->getValue();

            // Procesar datos válidos si no contiene TOTAL
            if (stripos(trim($itemN), "TOTAL") === false) {
                // Obtener cliente - búsqueda mejorada
                $client = $this->findClientForItem($itemToClientMap, $itemN);

                // Solo procesar cliente y merge si hay un cliente válido
                if (!empty($client)) {
                    // Procesar cliente y merge
                    $this->processClientMerge($sheet, $row, $client, $currentClient, $clientStartRow, $clientEndRow, $pendingMerge);
                } else {
                    Log::info('Item ' . $itemN . ' no tiene cliente asociado, se dejará vacío');
                }

                // Buscar información aduanera
                $this->processCustomsInfo($sheet, $row, $itemN, $listaPartidasExcel);

                // Buscar datos del sistema
                $this->processSystemData($sheet, $row, $client, $dataSystem);

                // Aplicar estilos
                $this->applyRowStyles($sheet, $row);
            }

            // Verificar si contiene TOTAL después de procesar
            if (stripos(trim($itemN), "TOTAL") !== false) {
                $this->processTotalRow($sheet, $row);
                break;
            }
        }

        // Aplicar merges pendientes
        $this->applyPendingMerges($sheet, $pendingMerge, $currentClient, $clientStartRow, $clientEndRow);

        // Retornar información del último cliente para continuidad entre hojas
        return [
            'lastClient' => $currentClient,
            'lastClientEndRow' => $clientEndRow,
            'lastProcessedRow' => $row - 1 // La última fila procesada antes del TOTAL
        ];
    }

    /**
     * Procesa fila de total
     */
    private function processTotalRow($sheet, $row)
    {
        try {
            $sheet->unmergeCells('B' . $row . ':P' . $row);
            $sheet->mergeCells('E' . $row . ':L' . $row);
        } catch (\Exception $e) {
            Log::warning('Error procesando fila total: ' . $e->getMessage());
        }
    }

    /**
     * Procesa merge de cliente
     */
    private function processClientMerge($sheet, $row, $client, &$currentClient, &$clientStartRow, &$clientEndRow, &$pendingMerge)
    {
        if ($client !== $currentClient) {
            if ($currentClient !== "" && $currentClient !== null && $clientStartRow > 0) {
                $pendingMerge[] = [
                    'client' => $currentClient,
                    'start' => $clientStartRow,
                    'end' => $clientEndRow
                ];
            }

            $currentClient = $client;
            $clientStartRow = $row;
        }

        $clientEndRow = $row;
        $sheet->setCellValue('D' . $row, $client);
    }

    /**
     * Procesa información aduanera
     */
    private function processCustomsInfo($sheet, $row, $itemN, $listaPartidasExcel)
    {
        $sheetListaPartidas = $listaPartidasExcel->getSheet(0);
        $mergedCells = $sheetListaPartidas->getMergeCells();

        foreach ($mergedCells as $range) {
            [$startCell, $endCell] = explode(':', $range);
            if (preg_match('/^B\d+$/', $startCell)) {
                $value = $sheetListaPartidas->getCell($startCell)->getValue();
                if (trim($value) == $itemN) {
                    preg_match('/\d+/', $startCell, $startMatches);
                    preg_match('/\d+/', $endCell, $endMatches);
                    $startRow = (int)$startMatches[0];
                    $endRow = (int)$endMatches[0];

                    for ($r = $startRow; $r <= $endRow; $r++) {
                        $adValorem = $sheetListaPartidas->getCell('G' . $r)->getValue();
                        if (trim($adValorem) == "FTA") {
                            $adValorem = $sheetListaPartidas->getCell('H' . $r)->getValue();
                        }

                        $antiDumping = $sheetListaPartidas->getCell('I' . $r)->getValue();
                        $sheet->setCellValue('R' . $row, $adValorem);
                        $sheet->setCellValue('S' . $row, $antiDumping == 0 ? "-" : $antiDumping);
                        break;
                    }
                    break;
                }
            }
        }
    }

    /**
     * Procesa datos del sistema
     */
    private function processSystemData($sheet, $row, $client, $dataSystem)
    {
        $volumen_cotizacion = "-";
        $volumen_selected = '';
        $volumen_china = "-";
        $volumen_doc = "-";
        $valor_doc = "-";
        $valor_cot = "-";
        $tipoCliente = "No existe en contenedor";

        foreach ($dataSystem as $item) {
            if ($this->isNameMatch($client, $item->nombre)) {
                $volumen_cotizacion = $item->volumen;
                $volumen_china = $item->volumen_china;
                $volumen_selected = $item->vol_selected ?? '';
                $volumen_doc = $item->volumen_doc;
                $valor_doc = $item->valor_doc;
                $tipoCliente = $item->name;
                break;
            }
        }

        // Seleccionar volumen apropiado
        if ($volumen_selected == 'volumen_doc') {
            $volumen_cotizacion = $volumen_doc;
        } elseif ($volumen_selected == 'volumen_china') {
            $volumen_cotizacion = $volumen_china;
        }

        $sheet->setCellValue('T' . $row, $volumen_cotizacion);
        $sheet->setCellValue('C' . $row, $tipoCliente);
    }

    /**
     * Aplica estilos a la fila
     */
    private function applyRowStyles($sheet, $row)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ]
            ]
        ];

        $sheet->getStyle('R' . $row . ':T' . $row)->applyFromArray($styleArray);
        $sheet->getStyle('R' . $row . ':T' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('R' . $row . ':T' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('R' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    }

    /**
     * Desmerge celdas de forma segura sin lanzar errores
     */
    private function safeUnmergeCells($sheet, $cellAddress)
    {
        try {
            // Obtener todas las celdas mergeadas
            $mergedCells = $sheet->getMergeCells();

            foreach ($mergedCells as $mergedRange) {
                // Si la celda está dentro del rango mergeado, desmergear
                if ($this->isCellInRange($cellAddress, $mergedRange)) {
                    $sheet->unmergeCells($mergedRange);
                    Log::info('🔓 Desmergeado: ' . $mergedRange . ' (contiene ' . $cellAddress . ')');
                    break;
                }
            }
        } catch (\Exception $e) {
            // Silenciar errores de unmerge - puede que la celda no esté mergeada
            Log::info('ℹ️ No se pudo desmergar ' . $cellAddress . ': ' . $e->getMessage());
        }
    }

    /**
     * Verifica si una celda está dentro de un rango
     */
    private function isCellInRange($cellAddress, $range)
    {
        try {
            // Parsear la celda objetivo
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($cellAddress);
            $cellCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($coordinate[0]);
            $cellRow = $coordinate[1];

            // Parsear el rango (ej: "C5:C7" -> ["C5", "C7"])
            $rangeParts = explode(':', $range);
            if (count($rangeParts) !== 2) {
                return false;
            }

            // Parsear coordenadas de inicio y fin del rango
            $startCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($rangeParts[0]);
            $endCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($rangeParts[1]);

            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startCoordinate[0]);
            $startRow = $startCoordinate[1];
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($endCoordinate[0]);
            $endRow = $endCoordinate[1];

            // Verificar si la celda está dentro del rango
            return ($cellCol >= $startCol && $cellCol <= $endCol && $cellRow >= $startRow && $cellRow <= $endRow);
        } catch (\Exception $e) {
            Log::warning('Error verificando si celda está en rango: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Aplica merges pendientes
     */
    private function applyPendingMerges($sheet, $pendingMerge, $currentClient, $clientStartRow, $clientEndRow)
    {
        if ($currentClient !== "" && $currentClient !== null && $clientStartRow > 0) {
            $pendingMerge[] = [
                'client' => $currentClient,
                'start' => $clientStartRow,
                'end' => $clientEndRow
            ];
        }

        foreach ($pendingMerge as $merge) {
            if ($merge['start'] < $merge['end'] && $merge['start'] > 0 && $merge['end'] > 0) {
                try {
                    Log::info('🔗 Procesando merge para cliente: ' . $merge['client'] . ' desde fila ' . $merge['start'] . ' hasta ' . $merge['end']);

                    // Primero desmergar todas las celdas en el rango para evitar conflictos
                    for ($row = $merge['start']; $row <= $merge['end']; $row++) {
                        $this->safeUnmergeCells($sheet, 'C' . $row);
                        $this->safeUnmergeCells($sheet, 'D' . $row);
                        $this->safeUnmergeCells($sheet, 'T' . $row);
                    }

                    // Ahora aplicar los nuevos merges
                    $sheet->mergeCells('C' . $merge['start'] . ':C' . $merge['end']);
                    $sheet->mergeCells('D' . $merge['start'] . ':D' . $merge['end']);
                    $sheet->mergeCells('T' . $merge['start'] . ':T' . $merge['end']);

                    Log::info('✅ Merge aplicado exitosamente para cliente: ' . $merge['client']);
                } catch (\Exception $e) {
                    Log::error('❌ Error merging cells for client ' . $merge['client'] . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Procesa merge de cliente para hojas adicionales
     */
    private function processAdditionalClientMerge($sheet, $row, $client, &$currentClient, &$clientStartRow, &$clientEndRow, &$pendingMerge)
    {
        if ($client !== $currentClient) {
            if ($currentClient !== "" && $currentClient !== null && $clientStartRow > 0) {
                $pendingMerge[] = [
                    'client' => $currentClient,
                    'start' => $clientStartRow,
                    'end' => $clientEndRow
                ];
            }

            $currentClient = $client;
            $clientStartRow = $row;
        }

        $clientEndRow = $row;
    }

    /**
     * Procesa hojas adicionales
     */
    private function processAdditionalSheets($facturaExcel, $itemToClientMap, $dataSystem, $listaPartidasExcel, $lastClientInfo)
    {
        $sheetCount = $facturaExcel->getSheetCount();
        if ($sheetCount <= 1) return;

        $sheet0 = $facturaExcel->getSheet(0);
        $highestFirstSheetRow = $this->getHighestRowFirstSheet($facturaExcel);
        Log::info('Fila más alta de la primera hoja: ' . $highestFirstSheetRow);
        Log::info('Total de hojas en factura comercial: ' . $sheetCount);
        Log::info('Elementos en itemToClientMap antes de procesar hojas adicionales: ' . count($itemToClientMap));

        for ($i = 1; $i < $sheetCount; $i++) {
            $sheet = $facturaExcel->getSheet($i);
            Log::info('Procesando hoja adicional ' . $i . ' - Nombre: ' . $sheet->getTitle());
            $lastClientInfo = $this->processAdditionalSheet($sheet, $sheet0, $itemToClientMap, $dataSystem, $listaPartidasExcel, $highestFirstSheetRow, $lastClientInfo);
        }
    }

    /**
     * Obtiene la fila más alta de la primera hoja (donde insertar elementos de hojas adicionales)
     */
    private function getHighestRowFirstSheet($facturaExcel)
    {
        $sheet0 = $facturaExcel->getSheet(0);
        $highestRow = $sheet0->getHighestRow();
        $lastValidRow = $highestRow;

        for ($row = 26; $row <= $highestRow; $row++) {
            $itemN = $sheet0->getCell('B' . $row)->getValue();
            Log::info('Item N: ' . $itemN);

            // Si encontramos TOTAL, la posición de inserción debe ser DESPUÉS del último elemento válido
            if (stripos(trim($itemN), "TOTAL") !== false) {
                // Retornar la fila de TOTAL para insertar ANTES de ella
                return $row;
            }

            // Si no es TOTAL y tiene contenido, actualizar última fila válida
            if (!empty(trim($itemN))) {
                $lastValidRow = $row + 1; // +1 para insertar después
            }
        }

        return $lastValidRow;
    }

    /**
     * Procesa hoja adicional
     */
    private function processAdditionalSheet($sheet, $sheet0, $itemToClientMap, $dataSystem, $listaPartidasExcel, &$highestFirstSheetRow, $lastClientInfo)
    {
        $highestRow = $sheet->getHighestRow();
        $startIndex = 26;

        // Variables para manejar merge de clientes - inicializar con info de hoja anterior
        $currentClient = $lastClientInfo['lastClient'] ?? "";
        $clientStartRow = $lastClientInfo['lastClientEndRow'] ?? 0;
        $clientEndRow = $lastClientInfo['lastClientEndRow'] ?? 0;
        $pendingMerge = [];

        Log::info('🔗 Iniciando hoja adicional con último cliente: "' . $currentClient . '" en fila ' . $clientStartRow);

        Log::info('=== INICIANDO PROCESAMIENTO DE HOJA ADICIONAL ===');
        Log::info('Hoja: ' . $sheet->getTitle() . ', Filas: ' . $highestRow);

        // Primero, mostrar todos los itemIds que encuentra en esta hoja
        $itemsEncontrados = [];
        for ($row = $startIndex; $row <= $highestRow; $row++) {
            $itemN = $sheet->getCell('B' . $row)->getValue();
            if (!empty($itemN) && stripos(trim($itemN), "TOTAL") === false) {
                $itemsEncontrados[] = trim($itemN);
            }
        }
        Log::info('ItemIds encontrados en hoja adicional: ' . implode(', ', $itemsEncontrados));

        for ($row = $startIndex; $row <= $highestRow; $row++) {
            $itemN = $sheet->getCell('B' . $row)->getValue();

            // Procesar datos válidos si no contiene TOTAL
            if (stripos(trim($itemN), "TOTAL") === false) {
                // Insertar nueva fila
                $sheet0->insertNewRowBefore($highestFirstSheetRow, 1);

                // Copiar datos del producto
                $this->copyProductData($sheet, $sheet0, $row, $highestFirstSheetRow);

                // Procesar cliente y datos del sistema - búsqueda mejorada
                Log::info('Procesando hoja adicional - Item: ' . $itemN . ' en fila: ' . $row . ' de hoja: ' . $sheet->getTitle());
                $client = $this->findClientForItem($itemToClientMap, $itemN);
                Log::info('Cliente encontrado para ' . $itemN . ': ' . $client);

                // Solo escribir cliente y procesar merge si hay un cliente válido
                if (!empty($client)) {
                    // ESCRIBIR EL CLIENTE EN LA COLUMNA D
                    $sheet0->setCellValue('D' . $highestFirstSheetRow, $client);

                    // Procesar merge de cliente (similar a processClientMerge pero adaptado)
                    $this->processAdditionalClientMerge($sheet0, $highestFirstSheetRow, $client, $currentClient, $clientStartRow, $clientEndRow, $pendingMerge);
                } else {
                    Log::info('Item ' . $itemN . ' no tiene cliente asociado en hoja adicional, se dejará vacío');
                }

                $this->processSystemData($sheet0, $highestFirstSheetRow, $client, $dataSystem);

                // Procesar información aduanera
                $this->processCustomsInfo($sheet0, $highestFirstSheetRow, $itemN, $listaPartidasExcel);

                // Aplicar estilos
                $this->applyAdditionalRowStyles($sheet0, $highestFirstSheetRow);

                $highestFirstSheetRow++;
            }

            // Verificar si contiene TOTAL después de procesar
            if (stripos(trim($itemN), "TOTAL") !== false) {
                break;
            }
        }

        // Aplicar merges pendientes al final
        $this->applyPendingMerges($sheet0, $pendingMerge, $currentClient, $clientStartRow, $clientEndRow);

        // Retornar información del último cliente para la siguiente hoja
        return [
            'lastClient' => $currentClient,
            'lastClientEndRow' => $clientEndRow,
            'lastProcessedRow' => $highestFirstSheetRow - 1
        ];
    }

    /**
     * Copia datos del producto
     */
    private function copyProductData($sourceSheet, $targetSheet, $sourceRow, $targetRow)
    {
        $targetSheet->setCellValue('B' . $targetRow, $sourceSheet->getCell('B' . $sourceRow)->getValue());
        $targetSheet->setCellValue('E' . $targetRow, $sourceSheet->getCell('D' . $sourceRow)->getValue());
        $targetSheet->setCellValue('M' . $targetRow, $sourceSheet->getCell('L' . $sourceRow)->getValue());
        $targetSheet->setCellValue('N' . $targetRow, $sourceSheet->getCell('M' . $sourceRow)->getValue());
        $targetSheet->setCellValue('O' . $targetRow, $sourceSheet->getCell('N' . $sourceRow)->getValue());
        $targetSheet->setCellValue('P' . $targetRow, $sourceSheet->getCell('O' . $sourceRow)->getValue());
        $targetSheet->setCellValue('Q' . $targetRow, '=M' . $targetRow . '*O' . $targetRow);

        // Merge de celdas
        $targetSheet->mergeCells('E' . $targetRow . ':L' . $targetRow);
    }

    /**
     * Aplica estilos a fila adicional
     */
    private function applyAdditionalRowStyles($sheet, $row)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ]
            ]
        ];

        $sheet->getStyle('R' . $row . ':T' . $row)->applyFromArray($styleArray);
        $sheet->getStyle('R' . $row . ':T' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('R' . $row . ':T' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('O' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        $sheet->getStyle('Q' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        $sheet->getStyle('R' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    }

    /**
     * Aplica estilos finales
     */
    private function applyFinalStyles($sheet)
    {
        $highestRow = $sheet->getHighestRow();

        // Configurar dimensiones de columnas
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(60);
        $sheet->getColumnDimension('R')->setWidth(20);
        $sheet->getColumnDimension('S')->setWidth(25);
        $sheet->getColumnDimension('T')->setWidth(15);

        // Aplicar colores de fondo
        $this->applyBackgroundColors($sheet, $highestRow);

        // Configurar fórmulas de totales
        $this->setTotalFormulas($sheet, $highestRow);
    }

    /**
     * Aplica colores de fondo
     */
    private function applyBackgroundColors($sheet, $highestRow)
    {
        $startColumn = 26;

        if ($highestRow > 1) {
            // Columna C - Rosa
            $sheet->getStyle('C' . $startColumn . ':C' . ($highestRow - 1))->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('f5b7b1');

            // Columna D - Gris
            $sheet->getStyle('D' . $startColumn . ':D' . ($highestRow - 1))->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('dcdde1');

            // Columnas R y S - Azul cielo
            $sheet->getStyle('R' . $startColumn . ':S' . ($highestRow - 1))->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('85c1e9');

            // Columna T - Rosa
            $sheet->getStyle('T' . $startColumn . ':T' . ($highestRow - 1))->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('f5b7b1');
        }
    }

    /**
     * Configura fórmulas de totales
     */
    private function setTotalFormulas($sheet, $highestRow)
    {
        $startColumn = 26;

        if ($highestRow > 0 && ($highestRow - 1) > 0) {
            $sheet->setCellValue('Q' . $highestRow, '=SUM(Q' . $startColumn . ':Q' . ($highestRow - 1) . ')');
            $sheet->setCellValue('T' . $highestRow, '=SUM(T' . $startColumn . ':T' . ($highestRow - 1) . ')');
        }
    }

    /**
     * Verifica si dos nombres coinciden con normalización avanzada
     */
    private function isNameMatch($name1, $name2)
    {
        // Normalizar ambos nombres
        $normalized1 = $this->normalizeClientName($name1);
        $normalized2 = $this->normalizeClientName($name2);

        Log::info('Comparando clientes: "' . $name1 . '" (' . $normalized1 . ') vs "' . $name2 . '" (' . $normalized2 . ')');

        // Comparación exacta
        if ($normalized1 === $normalized2) {
            Log::info('✅ Coincidencia exacta encontrada');
            return true;
        }

        // Comparación de similitud (85% o más)
        $similarity = 0;
        similar_text($normalized1, $normalized2, $similarity);
        if ($similarity >= 85) {
            Log::info('✅ Coincidencia por similitud encontrada: ' . round($similarity, 2) . '%');
            return true;
        }

        Log::info('❌ No hay coincidencia (similitud: ' . round($similarity, 2) . '%)');
        return false;
    }

    /**
     * Normaliza un nombre de cliente para comparación
     */
    private function normalizeClientName($name)
    {
        if (empty($name)) {
            return '';
        }

        // Convertir a string si no lo es
        $name = (string) $name;

        // Convertir a minúsculas
        $name = mb_strtolower($name, 'UTF-8');

        // Remover acentos y caracteres especiales
        $name = $this->removeAccents($name);

        // Remover caracteres que no sean letras, números o espacios
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);

        // Normalizar espacios (múltiples espacios a uno solo)
        $name = preg_replace('/\s+/', ' ', $name);

        // Quitar espacios al inicio y final
        $name = trim($name);

        return $name;
    }

    /**
     * Remueve acentos de un texto
     */
    private function removeAccents($text)
    {
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
            'ç' => 'c'
        ];

        return strtr($text, $accents);
    }
    public function deleteFileDocumentation(Request $request, $idFile)
    {
        $file = DocumentacionFile::find($idFile);
        $file->delete();
        return response()->json([
            'success' => true,
            'message' => 'Archivo eliminado correctamente'
        ]);
    }

    /**
     * Descarga la documentación completa en formato ZIP
     */
    public function downloadDocumentacionZip($id)
    {
        try {
            // Obtener carpetas y archivos de documentación
            $folders = DB::table('contenedor_consolidado_documentacion_folders as main')
                ->join('contenedor_consolidado_documentacion_files as files', 'files.id_folder', '=', 'main.id')
                ->select('main.*', 'files.id as id_file', 'files.file_url')
                ->where('files.id_contenedor', $id)
                ->get();

            if ($folders->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron archivos de documentación para este contenedor'
                ], 404);
            }

            // Crear nombre del archivo ZIP
            $zipName = 'contenedor_' . $id . '_' . time() . '.zip';
            $zipPath = storage_path('app/public/temp/' . $zipName);

            // Crear directorio temporal si no existe
            $tempDir = dirname($zipPath);
            if (!file_exists($tempDir)) {
                $created = mkdir($tempDir, 0755, true);
                Log::info('Directorio temporal creado: ' . $tempDir . ' - Resultado: ' . ($created ? 'exitoso' : 'fallido'));
            } else {
                Log::info('Directorio temporal ya existe: ' . $tempDir);
            }

            // Verificar permisos del directorio
            if (is_dir($tempDir)) {
                Log::info('Permisos del directorio temporal: ' . substr(sprintf('%o', fileperms($tempDir)), -4));
                Log::info('Directorio es escribible: ' . (is_writable($tempDir) ? 'SÍ' : 'NO'));
            }

            // Eliminar ZIP anterior si existe
            if (file_exists($zipPath)) {
                $deleted = unlink($zipPath);
                Log::info('ZIP anterior eliminado: ' . $zipPath . ' - Resultado: ' . ($deleted ? 'exitoso' : 'fallido'));
            }

            $zip = new \ZipArchive();

            Log::info('Intentando crear ZIP en: ' . $zipPath);
            Log::info('Directorio temporal existe: ' . (is_dir($tempDir) ? 'SÍ' : 'NO'));
            Log::info('Directorio temporal es escribible: ' . (is_writable($tempDir) ? 'SÍ' : 'NO'));

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                $filesAdded = 0;

                // Agregar archivos al ZIP
                foreach ($folders as $folder) {
                    if (empty($folder->file_url)) {
                        continue; // Saltar si no hay archivo
                    }

                    Log::info('Procesando archivo para ZIP:', [
                        'folder_name' => $folder->folder_name,
                        'file_url' => $folder->file_url,
                        'id_contenedor' => $id
                    ]);

                    // Verificar si es una URL externa o ruta local
                    if (filter_var($folder->file_url, FILTER_VALIDATE_URL)) {
                        Log::info('Archivo es URL externa, saltando: ' . $folder->file_url);
                        continue; // Saltar archivos externos
                    }

                    // Intentar múltiples rutas posibles para archivos locales
                    $filePaths = [
                        storage_path('app/public/' . $folder->file_url),
                        storage_path($folder->file_url),
                        public_path($folder->file_url),
                        base_path('storage/app/public/' . $folder->file_url)
                    ];

                    $fileFound = false;
                    $finalFilePath = null;

                    foreach ($filePaths as $filePath) {
                        if (file_exists($filePath) && is_readable($filePath)) {
                            $fileFound = true;
                            $finalFilePath = $filePath;
                            Log::info('Archivo encontrado en: ' . $filePath);
                            break;
                        }
                    }

                    if ($fileFound && $finalFilePath) {
                        // Obtener nombre del archivo
                        $fileName = basename($folder->file_url);

                        // Normalizar nombre del archivo (remover caracteres especiales)
                        $fileName = $this->sanitizeFileName($fileName);

                        // Crear estructura de carpetas en el ZIP
                        $zipFilePath = $folder->folder_name . '/' . $fileName;

                        // Agregar archivo al ZIP
                        if ($zip->addFile($finalFilePath, $zipFilePath)) {
                            $filesAdded++;
                            Log::info('Archivo agregado al ZIP exitosamente: ' . $zipFilePath);
                        } else {
                            Log::warning('No se pudo agregar al ZIP: ' . $finalFilePath);
                        }
                    } else {
                        Log::warning('Archivo local no encontrado en ninguna ruta:', [
                            'file_url' => $folder->file_url,
                            'tried_paths' => $filePaths
                        ]);
                    }
                }

                $closeResult = $zip->close();
                Log::info('ZIP cerrado - Resultado: ' . ($closeResult ? 'exitoso' : 'fallido'));

                if (!$closeResult) {
                    Log::error('Error al cerrar ZIP: ' . $zip->getStatusString());
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al cerrar archivo ZIP'
                    ], 500);
                }

                if ($filesAdded === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudieron agregar archivos al ZIP'
                    ], 500);
                }

                // Verificar que el archivo ZIP existe antes de descargarlo
                Log::info('Verificando archivo ZIP después de cerrarlo...');
                Log::info('Ruta del ZIP: ' . $zipPath);
                Log::info('Archivo existe: ' . (file_exists($zipPath) ? 'SÍ' : 'NO'));

                if (file_exists($zipPath)) {
                    Log::info('Tamaño del ZIP: ' . filesize($zipPath) . ' bytes');
                    Log::info('Permisos del ZIP: ' . substr(sprintf('%o', fileperms($zipPath)), -4));
                    Log::info('ZIP es legible: ' . (is_readable($zipPath) ? 'SÍ' : 'NO'));
                }

                if (!file_exists($zipPath)) {
                    Log::error('Archivo ZIP no encontrado después de crearlo: ' . $zipPath);
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al generar archivo ZIP'
                    ], 500);
                }

                Log::info('ZIP generado exitosamente con ' . $filesAdded . ' archivos: ' . $zipPath);

                // Retornar archivo ZIP para descarga
                return response()->download($zipPath, $zipName)
                    ->deleteFileAfterSend();
            } else {
                Log::error('Error al crear archivo ZIP');
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear archivo ZIP'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en downloadDocumentacionZip: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar ZIP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sanitiza el nombre del archivo para el ZIP
     */
    private function sanitizeFileName($fileName)
    {
        // Remover caracteres especiales y espacios
        $fileName = preg_replace('/[\x00-\x1F\x7F<>:"\/\\|?*]/', '', $fileName);

        // Normalizar espacios y guiones
        $fileName = str_replace([' ', '_'], '-', $fileName);

        // Remover caracteres duplicados
        $fileName = preg_replace('/-+/', '-', $fileName);

        // Limpiar al inicio y final
        $fileName = trim($fileName, '-');

        return $fileName;
    }

    /**
     * Limpia archivos obsoletos con URLs externas
     */
    /**
     * Obtiene la ruta local de un archivo, ya sea desde URL o ruta local
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

    public function cleanObsoleteFiles()
    {
        try {
            $obsoleteFiles = DocumentacionFile::where('file_url', 'like', 'https://%')
                ->orWhere('file_url', 'like', 'http://%')
                ->get();

            $cleanedCount = 0;
            foreach ($obsoleteFiles as $file) {
                Log::info('Limpiando archivo obsoleto: ' . $file->file_url);
                $file->delete();
                $cleanedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Se limpiaron ' . $cleanedCount . ' archivos obsoletos',
                'cleaned_count' => $cleanedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error limpiando archivos obsoletos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar archivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea una nueva carpeta de documentación con su archivo asociado
     */
    public function createDocumentacionFolder(Request $request)
    {
        try {
            // Validar request
            $request->validate([
                'folder_name' => 'required|string',
                'idContenedor' => 'required|integer',
                'file' => 'required|file|max:1000',
                'categoria' => 'nullable|string',
                'icon' => 'nullable|string'
            ]);

            // Obtener usuario autenticado
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar extensión del archivo
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            if (!in_array($request->file('file')->getClientOriginalExtension(), $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de archivo no permitido'
                ], 400);
            }

            // Generar nombre único y guardar archivo
            $filename = time() . '_' . uniqid() . '.' . $request->file('file')->getClientOriginalExtension();
            $path = 'assets/images/agentecompra/';
            $fileUrl = $request->file('file')->storeAs($path, $filename, 'public');

            if (!$fileUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al subir el archivo'
                ], 500);
            }

            // Verificar si el usuario tiene perfil de documentación
            $isDocumentationProfile = $user->getNombreGrupo() === $this->roleDocumentacion;

            // Crear carpeta de documentación
            DB::beginTransaction();
            try {
                $folder = DocumentacionFolder::create([
                    'id_contenedor' => $request->idContenedor,
                    'folder_name' => $request->folder_name,
                    'categoria' => $request->categoria,
                    'b_icon' => $request->icon,
                    'only_doc_profile' => $isDocumentationProfile,
                ]);

                if (!$folder) {
                    throw new \Exception('Error al crear la carpeta de documentación');
                }

                // Crear archivo de documentación
                $file = DocumentacionFile::create([
                    'id_folder' => $folder->id,
                    'file_url' => $fileUrl,
                    'id_contenedor' => $request->idContenedor
                ]);

                if (!$file) {
                    throw new \Exception('Error al crear el archivo de documentación');
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Carpeta y archivo creados exitosamente',
                    'data' => [
                        'folder' => $folder,
                        'file' => $file
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                // Si hay error, eliminar el archivo subido
                if ($fileUrl && Storage::disk('public')->exists($fileUrl)) {
                    Storage::disk('public')->delete($fileUrl);
                }
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error en createDocumentacionFolder: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear carpeta de documentación: ' . $e->getMessage()
            ], 500);
        }
    }
    public function downloadZipAdministracion($idContenedor)
    {
        try {
            // Obtener datos del contenedor
            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el contenedor'
                ], 404);
            }

            // Extraer datos de la factura comercial
            $productosData = $this->extractProductosFromFacturaComercial($idContenedor);
            
            if (empty($productosData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron productos en la factura comercial'
                ], 404);
            }

            // Verificar que los archivos de plantilla existen (locales o URLs)
            $templateFiles = [
                'plantilla_productos.xlsx', 
                'plantilla_stock.xlsx',
                'plantilla_precios.xlsx',
            ];
            
            $templatePath = public_path('assets/templates/');
            $existingFiles = [];
            
            foreach ($templateFiles as $file) {
                $fullPath = $templatePath . $file;
                if (file_exists($fullPath)) {
                    $existingFiles[] = $fullPath;
                } else {
                    Log::warning("Archivo de plantilla no encontrado: {$file}");
                }
            }
            
            if (empty($existingFiles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron archivos de plantilla para descargar'
                ], 404);
            }
            
            // Crear archivo ZIP temporal
            $zipFileName = 'plantillas_pobladas_' . $contenedor->carga . '_' . time() . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);
            
            // Crear directorio temp si no existe
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            
            if ($result !== TRUE) {
                throw new \Exception("No se pudo crear el archivo ZIP. Código de error: {$result}");
            }
            
            // Generar plantillas pobladas
            $poblatedFiles = $this->generatePoblatedTemplates($productosData, $contenedor->carga);
            
            // Agregar solo plantillas pobladas al ZIP
            foreach ($poblatedFiles as $filePath) {
                $fileName = basename($filePath);
                $zip->addFile($filePath, $fileName);
            }
            
            $zip->close();
            
            // Verificar que el ZIP se creó correctamente
            if (!file_exists($zipPath)) {
                throw new \Exception("El archivo ZIP no se creó correctamente");
            }
            
            // Limpiar archivos temporales
            foreach ($poblatedFiles as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Descargar el archivo ZIP
            return response()->download($zipPath, 'plantillas_pobladas_' . $contenedor->carga . '.zip')->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            Log::error('Error en downloadZipAdministracion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el zip: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Crea una carpeta de documentación para proveedor
     */
    public function createProveedorDocumentacionFolder(Request $request)
    {
        try {
            // Validar request
            $request->validate([
                'name' => 'required|string',
                'file' => 'required|file',
                'id_cotizacion' => 'required|integer',
                'id_proveedor' => 'required|integer'
            ]);

            // Obtener usuario autenticado
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $name = $request->name;
            $categoria = 'proveedor';
            $icon = $request->icon;
            $idCotizacion = $request->id_cotizacion;
            $idContenedor = Cotizacion::where('id', $idCotizacion)->first()->id_contenedor;
            $idProveedor = $request->id_proveedor;
            $file = $request->file('file');

            // Validar extensión del archivo
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (!in_array($fileExtension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de archivo no permitido'
                ], 400);
            }

            // Validar tamaño del archivo (1MB)
            $maxFileSize = 1000000;
            if ($file->getSize() > $maxFileSize) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo excede el tamaño máximo permitido (1MB)'
                ], 400);
            }

            // Generar nombre único y guardar archivo
            $filename = time() . '_' . uniqid() . '.' . $fileExtension;
            $path = 'assets/images/agentecompra/';
            $fileUrl = $file->storeAs($path, $filename, 'public');

            if (!$fileUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al subir el archivo'
                ], 500);
            }

            // Verificar si el usuario tiene perfil de documentación
            $isDocumentationProfile = $user->getNombreGrupo() === $this->roleDocumentacion;

            // Crear carpeta y archivo usando transacciones
            DB::beginTransaction();
            try {
                // Crear carpeta de documentación
                $folder = DocumentacionFolder::create([
                    'id_contenedor' => $idContenedor,
                    'folder_name' => $name,
                    'categoria' => $categoria,
                    'b_icon' => 'proveedor',
                    'only_doc_profile' => $isDocumentationProfile,
                ]);

                if (!$folder) {
                    throw new \Exception('Error al crear la carpeta de documentación');
                }

                // Crear archivo de documentación
                $documentacionFile = DocumentacionFile::create([
                    'id_folder' => $folder->id,
                    'file_url' => $fileUrl,
                    'id_contenedor' => $idContenedor
                ]);

                if (!$documentacionFile) {
                    throw new \Exception('Error al crear el archivo de documentación');
                }

                // Insertar en la tabla de documentación de proveedor
                $proveedorDocumentacion = DB::table($this->table_contenedor_cotizacion_documentacion)->insert([
                    'id_cotizacion' => $idCotizacion,
                    'name' => $name,
                    'file_url' => $fileUrl,
                    'id_proveedor' => $idProveedor
                ]);

                if (!$proveedorDocumentacion) {
                    throw new \Exception('Error al crear la documentación del proveedor');
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Carpeta de documentación para proveedor creada exitosamente',
                    'data' => [
                        'folder' => $folder,
                        'file' => $documentacionFile,
                        'proveedor_documentacion' => $proveedorDocumentacion
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                // Si hay error, eliminar el archivo subido
                if ($fileUrl && Storage::disk('public')->exists($fileUrl)) {
                    Storage::disk('public')->delete($fileUrl);
                }
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error en createProveedorDocumentacionFolder: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear carpeta de documentación para proveedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrae datos de productos de la factura comercial
     */
    private function extractProductosFromFacturaComercial($idContenedor)
    {
        $tempFile = null; // Para rastrear archivos temporales
        
        try {
            // Buscar archivo de factura comercial
            $facturaComercial = $this->getDocumentacionFile($idContenedor, 'Factura Comercial');
            if (!$facturaComercial || !$this->isExcelFile($facturaComercial->file_url)) {
                return [];
            }

            // Cargar archivo Excel
            $facturaPath = $this->getLocalPath($facturaComercial->file_url);
            
            // Si es un archivo temporal (descargado desde URL), marcarlo para limpieza
            // Verificamos si el path contiene el directorio temp y un timestamp
            if (strpos($facturaPath, storage_path('app/temp')) !== false && 
                preg_match('/\d+_\w+\.xlsx?$/', basename($facturaPath))) {
                $tempFile = $facturaPath;
            }
            
            $objPHPExcel = IOFactory::load($facturaPath);
            
            $productos = [];
            $sheetCount = $objPHPExcel->getSheetCount();
            
            // Procesar todas las hojas
            for ($sheetIndex = 0; $sheetIndex < $sheetCount; $sheetIndex++) {
                $sheet = $objPHPExcel->getSheet($sheetIndex);
                $highestRow = $sheet->getHighestRow();
                
                // Buscar filas de datos (empezando desde la fila 26 como en el código original)
                for ($row = 26; $row <= $highestRow; $row++) {
                    $itemN = $sheet->getCell('B' . $row)->getValue();
                    
                    // Verificar si no es una fila de total
                    if (!empty($itemN) && stripos(trim($itemN), "TOTAL") === false) {
                        $nombreProducto = $sheet->getCell('D' . $row)->getValue();
                        $cantidad = $sheet->getCell('L' . $row)->getValue();
                        $precio = $sheet->getCell('N' . $row)->getValue();
                        
                        // Validar que tenemos datos válidos
                        if (!empty($nombreProducto) && !empty($cantidad) && !empty($precio)) {
                            $precioVenta = floatval($precio) * 1.18; // Aplicar IGV
                            
                            $productos[] = [
                                'nombre' => trim($nombreProducto),
                                'cantidad' => floatval($cantidad),
                                'precio' => floatval($precio),
                                'precio_venta' => round($precioVenta, 2),
                                'codigo' => '' // Se generará después
                            ];
                        }
                    }
                }
            }
            
            // Generar códigos únicos para cada producto
            $productos = $this->generateProductCodes($productos, $idContenedor);
            
            return $productos;
            
        } catch (\Exception $e) {
            Log::error('Error extrayendo productos de factura comercial: ' . $e->getMessage());
            return [];
        } finally {
            // Limpiar archivo temporal si existe
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
                Log::info("Archivo temporal limpiado: {$tempFile}");
            }
        }
    }

    /**
     * Genera códigos únicos para los productos
     */
    private function generateProductCodes($productos, $idContenedor)
    {
        // Obtener carga del contenedor
        $contenedor = Contenedor::find($idContenedor);
        $carga = $contenedor ? $contenedor->carga : 'CONS';
        
        $codigosGenerados = [];
        $productosConCodigo = [];
        
        foreach ($productos as $producto) {
            $codigo = $this->generateUniqueCode($producto['nombre'], $carga, $codigosGenerados);
            $codigosGenerados[] = $codigo;
            
            $productosConCodigo[] = array_merge($producto, ['codigo' => $codigo]);
        }
        
        return $productosConCodigo;
    }

    /**
     * Genera un código único para un producto
     */
    private function generateUniqueCode($nombreProducto, $carga, $codigosExistentes)
    {
        // Limpiar y obtener palabras del nombre
        $palabras = preg_split('/\s+/', trim($nombreProducto));
        $palabras = array_filter($palabras, function($palabra) {
            return strlen(trim($palabra)) > 0;
        });
        
        // Obtener año actual
        $year = date('Y');
        
        $codigoBase = 'CONS' . $carga . '-' . $year;
        $longitudInicial = min(10, max(1, count($palabras)));
        
        // Intentar con diferentes longitudes hasta encontrar un código único
        for ($longitud = $longitudInicial; $longitud >= 1; $longitud--) {
            $iniciales = '';
            $caracteresUsados = 0;
            
            foreach ($palabras as $palabra) {
                if ($caracteresUsados >= $longitud) break;
                
                $palabraLimpia = preg_replace('/[^a-zA-Z0-9]/', '', $palabra);
                if (!empty($palabraLimpia)) {
                    $iniciales .= strtoupper(substr($palabraLimpia, 0, 1));
                    $caracteresUsados++;
                }
            }
            
            // Si no tenemos suficientes caracteres, usar más de las primeras palabras
            if ($caracteresUsados < $longitud) {
                foreach ($palabras as $palabra) {
                    if ($caracteresUsados >= $longitud) break;
                    
                    $palabraLimpia = preg_replace('/[^a-zA-Z0-9]/', '', $palabra);
                    for ($i = 1; $i < strlen($palabraLimpia) && $caracteresUsados < $longitud; $i++) {
                        $iniciales .= strtoupper(substr($palabraLimpia, $i, 1));
                        $caracteresUsados++;
                    }
                }
            }
            
            $codigoCompleto = $codigoBase . $iniciales;
            
            if (!in_array($codigoCompleto, $codigosExistentes)) {
                return $codigoCompleto;
            }
        }
        
        // Si no se puede generar un código único, agregar un número
        $contador = 1;
        do {
            $codigoCompleto = $codigoBase . 'PROD' . $contador;
            $contador++;
        } while (in_array($codigoCompleto, $codigosExistentes));
        
        return $codigoCompleto;
    }

    /**
     * Genera las plantillas pobladas
     */
    private function generatePoblatedTemplates($productosData, $carga)
    {
        $templatePath = public_path('assets/templates/');
        $tempPath = storage_path('app/temp/');
        $poblatedFiles = [];
        
        try {
            // Plantilla de productos
            $productosTemplate = $templatePath . 'plantilla_productos.xlsx';
            if (file_exists($productosTemplate)) {
                $productosPoblado = $this->populateProductosTemplate($productosTemplate, $productosData);
                $productosPath = $tempPath . '1_Laesystems_Plantilla_Productos.xlsx';
                $writer = new Xlsx($productosPoblado);
                $writer->save($productosPath);
                $poblatedFiles[] = $productosPath;
            }
            
            // Plantilla de stock
            $stockTemplate = $templatePath . 'plantilla_stock.xlsx';
            if (file_exists($stockTemplate)) {
                $stockPoblado = $this->populateStockTemplate($stockTemplate, $productosData);
                $stockPath = $tempPath . '1_Laesystems_Plantilla_Stock.xlsx';
                $writer = new Xlsx($stockPoblado);
                $writer->save($stockPath);
                $poblatedFiles[] = $stockPath;
            }
            
            // Plantilla de precios
            $preciosTemplate = $templatePath . 'plantilla_precios.xlsx';
            if (file_exists($preciosTemplate)) {
                $preciosPoblado = $this->populatePreciosTemplate($preciosTemplate, $productosData);
                $preciosPath = $tempPath . '1_Laesystems_Plantilla_Precios.xlsx';
                $writer = new Xlsx($preciosPoblado);
                $writer->save($preciosPath);
                $poblatedFiles[] = $preciosPath;
            }
            
        } catch (\Exception $e) {
            Log::error('Error generando plantillas pobladas: ' . $e->getMessage());
        }
        
        return $poblatedFiles;
    }

    /**
     * Pobla la plantilla de productos
     */
    private function populateProductosTemplate($templatePath, $productosData)
    {
        $excel = IOFactory::load($templatePath);
        $sheet = $excel->getActiveSheet();
        
        $row = 2; // Empezar en la fila 2
        foreach ($productosData as $producto) {
            $sheet->setCellValue('A' . $row, 1);
            $sheet->setCellValue('B' . $row, ''); // VACIO
            $sheet->setCellValue('C' . $row, $producto['codigo']);
            $sheet->setCellValue('D' . $row, ''); // VACIO
            $sheet->setCellValue('E' . $row, $producto['nombre']);
            $sheet->setCellValue('F' . $row, 'Gravado - Operación Onerosa');
            $sheet->setCellValue('G' . $row, 'UNIDAD (BIENES)');
            $sheet->setCellValue('H' . $row, ''); // VACIO
            $sheet->setCellValue('I' . $row, 'GENERAL');
            $sheet->setCellValue('J' . $row, ''); // VACIO
            $sheet->setCellValue('K' . $row, ''); // VACIO
            $sheet->setCellValue('L' . $row, ''); // VACIO
            $sheet->setCellValue('M' . $row, ''); // VACIO
            $sheet->setCellValue('N' . $row, $producto['precio_venta']);
            // O-P...AA -> VACIO (se dejan vacías por defecto)
            
            $row++;
        }
        
        return $excel;
    }

    /**
     * Pobla la plantilla de stock
     */
    private function populateStockTemplate($templatePath, $productosData)
    {
        $excel = IOFactory::load($templatePath);
        $sheet = $excel->getActiveSheet();
        
        $row = 2; // Empezar en la fila 2
        foreach ($productosData as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo']);
            $sheet->setCellValue('B' . $row, $producto['nombre']);
            $sheet->setCellValue('C' . $row, 'Gravado - Operación Onerosa');
            $sheet->setCellValue('D' . $row, ''); // VACIO
            $sheet->setCellValue('E' . $row, $producto['cantidad']);
            $sheet->setCellValue('F' . $row, ''); // VACIO
            $sheet->setCellValue('G' . $row, ''); // VACIO
            
            $row++;
        }
        
        return $excel;
    }

    /**
     * Pobla la plantilla de precios
     */
    private function populatePreciosTemplate($templatePath, $productosData)
    {
        $excel = IOFactory::load($templatePath);
        $sheet = $excel->getActiveSheet();
        
        $row = 2; // Empezar en la fila 2
        foreach ($productosData as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo']);
            $sheet->setCellValue('B' . $row, ''); // VACIO
            $sheet->setCellValue('C' . $row, ''); // VACIO
            $sheet->setCellValue('D' . $row, $producto['precio_venta']);
            
            $row++;
        }
        
        return $excel;
    }
}
