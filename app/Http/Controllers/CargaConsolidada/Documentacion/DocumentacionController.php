<?php

namespace App\Http\Controllers\CargaConsolidada\Documentacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\DocumentacionFolder;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\DocumentacionFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\BaseDatos\ProductosController;
class DocumentacionController extends Controller
{
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
            $folders = DocumentacionFolder::with(['files' => function($query) use ($id) {
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
                
                // Procesar los archivos de la carpeta
                if ($folder->files->count() > 0) {
                    foreach ($folder->files as $file) {
                        $fileData = [
                            'id' => $folder->id,
                            'id_file' => $file->id,
                            'file_url' => $file->file_url,
                            'type' => $file->file_type,
                            'lista_embarque_url' => $listaEmbarqueUrl
                        ];
                        
                        // Combinar datos de la carpeta con datos del archivo
                        $result[] = array_merge($folderData, $fileData);
                    }
                } else {
                    // Carpeta sin archivos
                    $folderData['id_file'] = null;
                    $folderData['file_url'] = null;
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
            }

            // Procesar excel de confirmación
            if ($request->hasFile('excel_confirmacion')) {
                $this->processFileUpload($proveedor, 'excel_confirmacion', $request->file('excel_confirmacion'), $data);
            }

            // Procesar packing list
            if ($request->hasFile('packing_list')) {
                $this->processFileUpload($proveedor, 'packing_list', $request->file('packing_list'), $data);
            }

            // Remover campos que no se deben actualizar
            unset($data['id'], $data['idProveedor'], $data['file_comercial'], $data['excel_confirmacion'], $data['packing_list']);
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
            
            $maxFileSize = 100*1024*1024;
            if ($file->getSize() > $maxFileSize) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo excede el tamaño máximo permitido (100MB)'
                ], 400);
            }

            // Validar extensión del archivo
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            $fileExtension = strtolower($file->getClientOriginalExtension());
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de archivo no permitido'
                ], 400);
            }

            // Generar nombre único para el archivo
            $filename = time() . '_' . uniqid() . '.' . $fileExtension;
            
            // Ruta de almacenamiento
            $path = 'agentecompra/documentacion/';
            
            // Guardar archivo
            $fileUrl = $file->storeAs($path, $filename, 'public');

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
}
