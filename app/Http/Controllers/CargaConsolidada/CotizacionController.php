<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;

class CotizacionController extends Controller
{
    public function index(Request $request, $idContenedor)
    {
        try {
            $user = Auth::user();
            $query = Cotizacion::where('id_contenedor', $idContenedor);

            // Aplicar filtros básicos
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('documento', 'LIKE', "%{$search}%")
                        ->orWhere('telefono', 'LIKE', "%{$search}%");
                });
            }

            // Filtrar por estado si se proporciona
            if ($request->has('estado') && !empty($request->estado)) {
                $query->where('estado', $request->estado);
            }

            // Filtrar por fecha si se proporciona
            if ($request->has('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->has('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }

            // Aplicar filtros según el rol del usuario
            switch ($user->rol) {
                case Usuario::ROL_COTIZADOR:
                    if ($user->id_usuario != 28791) {
                        $query->where('id_usuario', $user->id_usuario);
                    }
                    $query->where('estado_cotizador', 'CONFIRMADO');
                    break;

                case Usuario::ROL_DOCUMENTACION:
                    $query->where('estado_cotizador', 'CONFIRMADO')
                        ->whereNotNull('estado_cliente');
                    break;

                case Usuario::ROL_COORDINACION:
                    $query->where('estado_cotizador', 'CONFIRMADO')
                        ->whereNotNull('estado_cliente');
                    break;
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'fecha');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $results = $query->paginate($perPage, ['*'], 'page', $page);

            // Transformar los datos para la respuesta
            $data = $results->map(function ($cotizacion) {
                return [
                    'id' => $cotizacion->id,
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'telefono' => $cotizacion->telefono,
                    'correo' => $cotizacion->correo,
                    'fecha' => $cotizacion->fecha,
                    'estado' => $cotizacion->estado,
                    'estado_cliente' => $cotizacion->name,
                    'estado_cotizador' => $cotizacion->estado_cotizador,
                    'monto' => $cotizacion->monto,
                    'monto_final' => $cotizacion->monto_final,
                    'volumen' => $cotizacion->volumen,
                    'volumen_final' => $cotizacion->volumen_final,
                    'tarifa' => $cotizacion->tarifa,
                    'qty_item' => $cotizacion->qty_item,
                    'fob' => $cotizacion->fob,
                    'cotizacion_file_url' => $cotizacion->cotizacion_file_url,
                    'impuestos' => $cotizacion->impuestos,
                    'tipo_cliente' => $cotizacion->tipoCliente->name,

                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en index de cotizaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion store']);
    }

    public function show($id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion show']);
    }

    public function update(Request $request, $id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion update']);
    }

    public function destroy($id)
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            $cotizacionProveedor = CotizacionProveedor::where('id_cotizacion', $id);
            $cotizacionProveedor->delete();
            //delete cotizacion
            $cotizacion = Cotizacion::find($id);
            $cotizacion->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            return response()->json(['message' => 'Cotizacion borrada correctamente', 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al borrar cotizacion: ' . $e->getMessage()], 500);
        }
    }

    public function filterOptions()
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion filter options']);
    }

    /**
     * Obtener documentación de clientes para una cotización específica
     * Replica la funcionalidad del método showClientesDocumentacion de CodeIgniter
     */
    public function showClientesDocumentacion($id)
    {
        try {
            // Obtener la cotización principal con todas sus relaciones
            $cotizacion = Cotizacion::with([
                'documentacion',
                'proveedores',
                'documentacionAlmacen',
                'inspeccionAlmacen'
            ])
                ->where('id', $id)
                ->whereNotNull('estado')
                ->first();

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Debug: Verificar qué datos se obtienen
            Log::info('Cotización encontrada:', ['id' => $cotizacion->id, 'estado' => $cotizacion->estado]);
            Log::info('Documentación count:', ['count' => $cotizacion->documentacion->count()]);
            Log::info('Proveedores count:', ['count' => $cotizacion->proveedores->count()]);
            Log::info('Documentación almacén count:', ['count' => $cotizacion->documentacionAlmacen->count()]);
            Log::info('Inspección almacén count:', ['count' => $cotizacion->inspeccionAlmacen->count()]);

            // Debug: Verificar datos de proveedores
            if ($cotizacion->proveedores->count() > 0) {
                $firstProvider = $cotizacion->proveedores->first();
                Log::info('Primer proveedor:', [
                    'id' => $firstProvider->id,
                    'code_supplier' => $firstProvider->code_supplier,
                    'volumen_doc' => $firstProvider->volumen_doc,
                    'valor_doc' => $firstProvider->valor_doc,
                    'id_cotizacion' => $firstProvider->id_cotizacion
                ]);
            }

            // Transformar los datos para mantener la estructura original
            $files = $cotizacion->documentacion->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_url,
                    'folder_name' => $file->name,
                    'id_proveedor' => $file->id_proveedor
                ];
            });

            $filesAlmacenDocumentacion = $cotizacion->documentacionAlmacen->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_path,
                    'folder_name' => $file->file_name,
                    'file_name' => $file->file_name,
                    'id_proveedor' => $file->id_proveedor,
                    'file_ext' => $file->file_ext
                ];
            });

            $providers = $cotizacion->proveedores->map(function ($provider) {
                return [
                    'code_supplier' => $provider->code_supplier,
                    'id' => $provider->id,
                    'volumen_doc' => $provider->volumen_doc ? (float) $provider->volumen_doc : null,
                    'valor_doc' => $provider->valor_doc ? (float) $provider->valor_doc : null,
                    'factura_comercial' => $provider->factura_comercial,
                    'excel_confirmacion' => $provider->excel_confirmacion,
                    'packing_list' => $provider->packing_list
                ];
            });

            $filesAlmacenInspection = $cotizacion->inspeccionAlmacen->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_path,
                    'file_name' => $file->file_name,
                    'id_proveedor' => $file->id_proveedor,
                    'file_ext' => $file->file_type
                ];
            });

            // Debug: Verificar datos transformados
            Log::info('Files transformados:', ['count' => $files->count()]);
            Log::info('Providers transformados:', ['count' => $providers->count()]);
            if ($providers->count() > 0) {
                Log::info('Primer provider transformado:', $providers->first());
            }

            // Construir la respuesta similar a la original
            $result = [
                'id' => $cotizacion->id,
                'id_contenedor' => $cotizacion->id_contenedor,
                'id_tipo_cliente' => $cotizacion->id_tipo_cliente,
                'id_cliente' => $cotizacion->id_cliente,
                'fecha' => $cotizacion->fecha,
                'nombre' => $cotizacion->nombre,
                'documento' => $cotizacion->documento,
                'correo' => $cotizacion->correo,
                'telefono' => $cotizacion->telefono,
                'volumen' => $cotizacion->volumen,
                'cotizacion_file_url' => $cotizacion->cotizacion_file_url,
                'cotizacion_final_file_url' => $cotizacion->cotizacion_final_file_url,
                'estado' => $cotizacion->estado,
                'volumen_doc' => $cotizacion->volumen_doc,
                'valor_doc' => $cotizacion->valor_doc,
                'valor_cot' => $cotizacion->valor_cot,
                'volumen_china' => $cotizacion->volumen_china,
                'factura_comercial' => $cotizacion->factura_comercial,
                'id_usuario' => $cotizacion->id_usuario,
                'monto' => $cotizacion->monto,
                'fob' => $cotizacion->fob,
                'impuestos' => $cotizacion->impuestos,
                'tarifa' => $cotizacion->tarifa,
                'excel_comercial' => $cotizacion->excel_comercial,
                'excel_confirmacion' => $cotizacion->excel_confirmacion,
                'vol_selected' => $cotizacion->vol_selected,
                'estado_cliente' => $cotizacion->estado_cliente,
                'peso' => $cotizacion->peso,
                'tarifa_final' => $cotizacion->tarifa_final,
                'monto_final' => $cotizacion->monto_final,
                'volumen_final' => $cotizacion->volumen_final,
                'guia_remision_url' => $cotizacion->guia_remision_url,
                'factura_general_url' => $cotizacion->factura_general_url,
                'cotizacion_final_url' => $cotizacion->cotizacion_final_url,
                'estado_cotizador' => $cotizacion->estado_cotizador,
                'fecha_confirmacion' => $cotizacion->fecha_confirmacion,
                'estado_pagos_coordinacion' => $cotizacion->estado_pagos_coordinacion,
                'estado_cotizacion_final' => $cotizacion->estado_cotizacion_final,
                'impuestos_final' => $cotizacion->impuestos_final,
                'fob_final' => $cotizacion->fob_final,
                'note_administracion' => $cotizacion->note_administracion,
                'status_cliente_doc' => $cotizacion->status_cliente_doc,
                'logistica_final' => $cotizacion->logistica_final,
                'qty_item' => $cotizacion->qty_item,
                'id_cliente_importacion' => $cotizacion->id_cliente_importacion,
                'files' => $files,
                'files_almacen_documentacion' => $filesAlmacenDocumentacion,
                'providers' => $providers,
                'files_almacen_inspection' => $filesAlmacenInspection
            ];

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Documentación de clientes obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener documentación de clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentación de clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrae datos de una cotización desde un archivo Excel
     * @param array $cotizacion Array con información del archivo subido
     * @return array|string Datos extraídos o mensaje de error
     */
    public function getCotizacionData($cotizacion)
    {
        try {
            $objPHPExcel = IOFactory::load($cotizacion['tmp_name']);

            $sheet = $objPHPExcel->getSheet(0);
            $nombre = $sheet->getCell('B8')->getValue();
            $documento = $sheet->getCell('B9')->getValue();
            $correo = $sheet->getCell('B10')->getValue();
            $telefono = $sheet->getCell('B11')->getValue();
            $volumen = $sheet->getCell('I11')->getCalculatedValue();
            $valorCot = $sheet->getCell('J14')->getCalculatedValue();

            //get calculated value from cell e9
            $fecha = $sheet->getCell('E9')->getValue();
            if ($fecha == "=+TODAY()") {
                $fecha = date("Y-m-d");
            } else {
                $fecha = $this->convertDateFormat($fecha);
            }

            $tipoCliente = $sheet->getCell('E11')->getValue();

            //find if exists in table contenedor_consolidado_tipo_cliente with name = $tipoCliente else create new and get id
            $tipoClienteModel = TipoCliente::where('name', $tipoCliente)->first();
            if (!$tipoClienteModel) {
                $tipoClienteModel = TipoCliente::create(['name' => $tipoCliente]);
            }
            $idTipoCliente = $tipoClienteModel->id;

            if (trim($sheet->getCell('A23')->getValue()) == "ANTIDUMPING") {
                $monto = $sheet->getCell('J31')->getOldCalculatedValue();
                $fob = $sheet->getCell('J30')->getOldCalculatedValue();
                $impuestos = $sheet->getCell('J32')->getOldCalculatedValue();

                //get j24 and j26
                Log::error('20: ' . $sheet->getCell('J20')->getOldCalculatedValue());
                Log::error('21: ' . $sheet->getCell('J21')->getOldCalculatedValue());
                Log::error('22: ' . $sheet->getCell('J22')->getOldCalculatedValue());
                Log::error('23: ' . $sheet->getCell('J23')->getOldCalculatedValue());
                Log::error('24: ' . $sheet->getCell('J24')->getOldCalculatedValue());
                Log::error('26: ' . $sheet->getCell('J26')->getOldCalculatedValue());
                Log::error('impuestos: ' . $impuestos);
            } else {
                $monto = $sheet->getCell('J30')->getOldCalculatedValue();
                $fob = $sheet->getCell('J29')->getOldCalculatedValue();
                $impuestos = $sheet->getCell('J31')->getOldCalculatedValue();
            }

            $tarifa = $monto / (($volumen <= 0 ? 1 : $volumen) < 1.00 ? 1 : ($volumen <= 0 ? 1 : $volumen));
            $peso = $sheet->getCell('I9')->getOldCalculatedValue();
            $highestRow = $sheet->getHighestRow();
            $qtyItem = 0;

            for ($row = 36; $row <= $highestRow; $row++) {
                $cellValue = $sheet->getCell('A' . $row)->getValue();
                if (is_numeric($cellValue) && $cellValue > 0) {
                    $qtyItem++;
                }
            }

            return [
                'nombre' => $nombre,
                'documento' => $documento,
                'correo' => $correo,
                'telefono' => $telefono,
                'volumen' => $volumen,
                'id_tipo_cliente' => $idTipoCliente,
                'fecha' => $fecha,
                'valor_cot' => $valorCot,
                'monto' => $monto,
                'tarifa' => $tarifa,
                'peso' => $peso,
                'fob' => $fob,
                'impuestos' => $impuestos,
                'qty_item' => $qtyItem
            ];
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Incrementa una columna de Excel (ej: A -> B, Z -> AA)
     * @param string $column Columna actual
     * @param int $increment Cantidad a incrementar
     * @return string Nueva columna
     */
    public function incrementColumn($column, $increment = 1)
    {
        $column = strtoupper($column); // Asegurarse de que todas las letras sean mayúsculas
        $length = strlen($column);
        $number = 0;

        // Convertir la columna a un número
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($column[$i]) - ord('A') + 1);
        }

        // Incrementar el número
        $number += $increment;

        // Convertir el número de vuelta a una columna
        $newColumn = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $newColumn = chr(ord('A') + $remainder) . $newColumn;
            $number = intval(($number - 1) / 26);
        }

        return $newColumn;
    }

    /**
     * Convierte el formato de fecha del Excel a formato Y-m-d
     * @param mixed $fecha Fecha del Excel
     * @return string Fecha en formato Y-m-d
     */
    private function convertDateFormat($fecha)
    {
        if (is_numeric($fecha)) {
            // Si es un número de serie de Excel, convertirlo a fecha
            $timestamp = ($fecha - 25569) * 86400;
            return date('Y-m-d', $timestamp);
        }

        // Si es una cadena, intentar parsearla
        $parsedDate = date_parse($fecha);
        if ($parsedDate['error_count'] === 0) {
            return date('Y-m-d', strtotime($fecha));
        }

        // Si no se puede parsear, devolver la fecha actual
        return date('Y-m-d');
    }

    // Propiedades de la clase
    protected $maxFileSize = 1000000;
    protected $defaultHoursContactado = 60; // 1 hora por defecto

    /**
     * Almacena una nueva cotización
     */
    public function storeCotizacion($data, $cotizacion)
    {
        try {
            $fileUrl = $this->uploadSingleFile([
                "name" => $cotizacion['name'],
                "type" => $cotizacion['type'],
                "tmp_name" => $cotizacion['tmp_name'],
                "error" => $cotizacion['error'],
                "size" => $cotizacion['size']
            ], 'assets/images/agentecompra/');

            $dataToInsert = $this->getCotizacionData($cotizacion);
            $dataToInsert['cotizacion_file_url'] = $fileUrl;
            $dataToInsert['id_contenedor'] = $data['id_contenedor'];
            $dataToInsert['id_usuario'] = Auth::id();

            $cotizacionModel = Cotizacion::create($dataToInsert);

            if ($cotizacionModel) {
                $idCotizacion = $cotizacionModel->id;
                $dataToInsert['id_cotizacion'] = $idCotizacion;

                $dataEmbarque = $this->getEmbarqueData($cotizacion, $dataToInsert);
                Log::error('Data embarque: ' . json_encode($dataEmbarque));

                // Insertar proveedores
                foreach ($dataEmbarque as $proveedor) {
                    CotizacionProveedor::create($proveedor);
                }

                $nombre = $dataToInsert['nombre'];

                // Obtener fecha de cierre del contenedor
                $contenedor = Contenedor::find($data['id_contenedor']);
                $f_cierre = $contenedor ? $contenedor->f_cierre : 'fecha no disponible';

                $message = 'Hola ' . $nombre . ' pudiste revisar la cotización enviada? 
        Te comento que cerramos nuestro consolidado este ' . $f_cierre . ' Por favor si cuentas con alguna duda me avisas y puedo llamarte para aclarar tus dudas.';

                $telefono = preg_replace('/\s+/', '', $dataToInsert['telefono']);
                $telefono = $telefono ? $telefono . '@c.us' : '';

                $data_json = [
                    'message' => $message,
                    'phoneNumberId' => $telefono,
                ];

                // Aquí podrías insertar en la tabla de crons si existe
                // Por ahora solo retornamos éxito

                return [
                    'id' => $idCotizacion,
                    'status' => "success"
                ];
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error en storeCotizacion: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene todos los tipos de cliente
     */
    public function getTipoCliente()
    {
        return TipoCliente::all();
    }

    /**
     * Elimina el archivo de cotización
     */
  

    /**
     * Elimina una cotización completa
     */
    public function deleteCotizacion($id)
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $cotizacion = Cotizacion::find($id);
            if ($cotizacion && $cotizacion->cotizacion_file_url) {
                if (file_exists($cotizacion->cotizacion_file_url)) {
                    unlink($cotizacion->cotizacion_file_url);
                }
            }

            $deleted = Cotizacion::destroy($id);

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            if ($deleted > 0) {
                return "success";
            }
            return false;
        } catch (Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            Log::error('Error en deleteCotizacion: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza el estado del cliente
     */
    public function updateStatusCliente($id_cotizacion, $status)
    {
        try {
            $user = Auth::user();
            if ($user->No_Grupo != 'Documentacion') {
                return 'success';
            }

            $cotizacion = Cotizacion::find($id_cotizacion);
            if (!$cotizacion) {
                return [
                    'status' => "error",
                    'message' => 'Cotización no encontrada'
                ];
            }

            $currentStatus = $cotizacion->status_cliente_doc;
            if ($currentStatus === 'Pendiente' || $currentStatus === 'Incompleto') {
                $cotizacion->update(['status_cliente_doc' => $status]);
                return 'success';
            } else {
                return [
                    'status' => "error",
                    'message' => 'Solo se puede cambiar el estado si está en Pendiente.'
                ];
            }
        } catch (Exception $e) {
            Log::error('Error en updateStatusCliente: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Refresca el archivo de cotización
     */
    public function refreshCotizacionFile($id)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return [
                    'status' => "error",
                    'message' => 'No se encontró la cotización con el ID proporcionado.'
                ];
            }

            $fileUrl = $cotizacion->cotizacion_file_url;
            if (!$fileUrl) {
                return [
                    'status' => "error",
                    'message' => 'No se encontró la URL del archivo de cotización.'
                ];
            }

            Log::info('Procesando archivo: ' . $fileUrl);

            $fileContents = $this->readFileFromMultipleSources($fileUrl);
            if ($fileContents === false || $fileContents === null || strlen($fileContents) == 0) {
                Log::error('No se pudo leer el archivo de cotización desde ninguna fuente: ' . $fileUrl);
                return [
                    'status' => "error",
                    'message' => 'El archivo de cotización no existe o no se puede leer.'
                ];
            }

            $originalExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);
            $tempFile = sys_get_temp_dir() . '/' . uniqid('cotizacion_', true) . '.' . $originalExtension;

            Log::info('Creando archivo temporal: ' . $tempFile);

            $bytesWritten = file_put_contents($tempFile, $fileContents);
            if ($bytesWritten === false) {
                Log::error('No se pudo crear el archivo temporal: ' . $tempFile);
                return [
                    'status' => "error",
                    'message' => 'Error al crear archivo temporal.'
                ];
            }

            if (!file_exists($tempFile) || !is_readable($tempFile)) {
                Log::error('El archivo temporal no es accesible: ' . $tempFile);
                return [
                    'status' => "error",
                    'message' => 'El archivo temporal no es accesible.'
                ];
            }

            $cotizacionFile = [
                'tmp_name' => $tempFile,
                'name' => basename($fileUrl),
                'size' => $bytesWritten,
                'type' => $this->getMimeType($originalExtension)
            ];

            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            try {
                $dataToInsert = $this->getCotizacionData($cotizacionFile);
                if (!$dataToInsert) {
                    throw new Exception('No se pudieron extraer datos del archivo de cotización');
                }

                if (isset($dataToInsert['fecha'])) {
                    unset($dataToInsert['fecha']);
                }

                $dataToInsert['updated_at'] = now();

                Log::info('Datos extraídos del archivo: ' . json_encode($dataToInsert));

                $cotizacion->update($dataToInsert);

                if (file_exists($tempFile)) {
                    unlink($tempFile);
                    Log::info('Archivo temporal eliminado: ' . $tempFile);
                }

                DB::statement('SET FOREIGN_KEY_CHECKS = 1');

                return [
                    'status' => "success",
                    'message' => 'Cotización actualizada exitosamente.'
                ];
            } catch (Exception $e) {
                if (isset($tempFile) && file_exists($tempFile)) {
                    unlink($tempFile);
                }
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Error en refreshCotizacionFile: ' . $e->getMessage());
            return [
                'status' => "error",
                'message' => 'Error al procesar la cotización: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene cargas disponibles
     */
    public function getCargasDisponibles()
    {
        $hoy = now()->format('Y-m-d');
        return Contenedor::where('f_cierre', '>=', $hoy)
            ->orderBy('carga', 'desc')
            ->get();
    }

    /**
     * Mueve una cotización a otro contenedor
     */
    public function moveCotizacionToConsolidado($idCotizacion, $idContenedorDestino)
    {
        try {
            DB::beginTransaction();

            $cotizacion = Cotizacion::find($idCotizacion);
            if ($cotizacion) {
                $cotizacion->update([
                    'id_contenedor' => $idContenedorDestino,
                    'estado_cotizador' => 'CONFIRMADO',
                    'updated_at' => now()
                ]);
            }

            CotizacionProveedor::where('id_cotizacion', $idCotizacion)
                ->update(['id_contenedor' => $idContenedorDestino]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en moveCotizacionToConsolidado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lee archivo desde múltiples fuentes
     */
    private function readFileFromMultipleSources($fileUrl)
    {
        Log::error('Intentando leer archivo: ' . $fileUrl);

        // 1. Ruta absoluta
        if (file_exists($fileUrl)) {
            Log::error('Leyendo archivo desde ruta absoluta: ' . $fileUrl);
            $content = file_get_contents($fileUrl);
            if ($content !== false) {
                Log::error('Archivo leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }
        }

        // 2. Ruta local
        $localPath = base_path() . '/' . ltrim($fileUrl, '/');
        if (file_exists($localPath)) {
            Log::error('Leyendo archivo desde ruta local: ' . $localPath);
            $content = file_get_contents($localPath);
            if ($content !== false) {
                Log::error('Archivo leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }
        }

        // 3. Storage de Laravel
        if (Storage::exists($fileUrl)) {
            Log::error('Leyendo archivo desde storage: ' . $fileUrl);
            $content = Storage::get($fileUrl);
            if ($content !== false) {
                Log::error('Archivo leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }
        }

        // 4. URL remota
        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            Log::error('Intentando leer archivo remoto: ' . $fileUrl);

            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,*/*',
                        'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                        'Cache-Control: no-cache'
                    ],
                    'follow_location' => true,
                    'max_redirects' => 5
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $content = @file_get_contents($fileUrl, false, $context);
            if ($content !== false && strlen($content) > 0) {
                Log::error('Archivo remoto leído exitosamente, tamaño: ' . strlen($content) . ' bytes');
                return $content;
            }

            // Fallback con cURL
            if (function_exists('curl_init')) {
                Log::error('Intentando con cURL...');
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fileUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,*/*',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                    'Cache-Control: no-cache'
                ]);

                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($content !== false && $httpCode == 200 && strlen($content) > 0) {
                    Log::error('Archivo remoto leído exitosamente con cURL, tamaño: ' . strlen($content) . ' bytes');
                    return $content;
                } else {
                    Log::error('Error cURL: ' . $error . ', HTTP Code: ' . $httpCode);
                }
            }
        }

        Log::error('No se pudo encontrar el archivo en ninguna ubicación: ' . $fileUrl);
        return false;
    }

    /**
     * Obtiene el tipo MIME basado en la extensión
     */
    private function getMimeType($extension)
    {
        $mimeTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv'
        ];

        return isset($mimeTypes[strtolower($extension)]) ? $mimeTypes[strtolower($extension)] : 'application/octet-stream';
    }

    /**
     * Sube un nuevo archivo de cotización
     */
    public function uploadCotizacionFile($id, $file)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return false;
            }

            $oldFileUrl = $cotizacion->cotizacion_file_url;
            if ($oldFileUrl && file_exists($oldFileUrl)) {
                unlink($oldFileUrl);
            }

            $fileUrl = $this->uploadSingleFile([
                "name" => $file['name'],
                "type" => $file['type'],
                "tmp_name" => $file['tmp_name'],
                "error" => $file['error'],
                "size" => $file['size']
            ], 'assets/images/agentecompra/');

            $dataToInsert = $this->getCotizacionData($file);
            $dataToInsert['cotizacion_file_url'] = $fileUrl;
            $dataToInsert['updated_at'] = now();

            // Verificar si existe fecha en la BD
            if ($cotizacion->fecha) {
                unset($dataToInsert['fecha']);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $cotizacion->update($dataToInsert);

            if ($cotizacion->wasChanged()) {
                $dataToInsert['id_cotizacion'] = $id;
                $dataToInsert['id_contenedor'] = $cotizacion->id_contenedor;

                // Obtener proveedores existentes
                $existingProviders = CotizacionProveedor::where('id_cotizacion', $id)
                    ->pluck('code_supplier')
                    ->toArray();

                // Obtener datos de embarque
                $dataEmbarque = $this->getEmbarqueDataModified($file, $dataToInsert);
                $newProviders = array_column($dataEmbarque, 'code_supplier');

                // Actualizar proveedores existentes
                foreach ($existingProviders as $code) {
                    if (in_array($code, $newProviders)) {
                        $key = array_search($code, $newProviders);
                        $dataToUpdate = $dataEmbarque[$key];
                        CotizacionProveedor::where('code_supplier', $code)
                            ->where('id_cotizacion', $id)
                            ->update($dataToUpdate);
                    } else {
                        CotizacionProveedor::where('code_supplier', $code)
                            ->where('id_cotizacion', $id)
                            ->delete();
                    }
                }

                // Insertar nuevos proveedores
                foreach ($dataEmbarque as $data) {
                    if (!in_array($data['code_supplier'], $existingProviders)) {
                        CotizacionProveedor::create($data);
                    }
                }

                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                return "success";
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            return false;
        } catch (Exception $e) {
            Log::error('Error en uploadCotizacionFile: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Muestra una cotización específica
     */
    public function showCotizacion($id)
    {
        return Cotizacion::find($id);
    }

    /**
     * Actualiza una cotización
     */
    public function updateCotizacion($data, $cotizacion)
    {
        try {
            $cotizacionModel = Cotizacion::find($data['id']);
            if (!$cotizacionModel) {
                return false;
            }

            $oldFileUrl = $cotizacionModel->cotizacion_file_url;
            if ($oldFileUrl && file_exists($oldFileUrl)) {
                unlink($oldFileUrl);
            }

            $fileUrl = $this->uploadSingleFile([
                "name" => $cotizacion['name'],
                "type" => $cotizacion['type'],
                "tmp_name" => $cotizacion['tmp_name'],
                "error" => $cotizacion['error'],
                "size" => $cotizacion['size']
            ], 'assets/images/agentecompra/');

            $data['cotizacion_file_url'] = $fileUrl;

            $updated = $cotizacionModel->update($data);
            return $updated ? "success" : false;
        } catch (Exception $e) {
            Log::error('Error en updateCotizacion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el estado de una cotización
     */
    public function updateEstadoCotizacion($id, $estado)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if ($cotizacion) {
                $updated = $cotizacion->update(['estado' => $estado]);
                return $updated ? "success" : false;
            }
            return false;
        } catch (Exception $e) {
            Log::error('Error en updateEstadoCotizacion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el estado general
     */
    public function updateEstado($id, $estado)
    {
        try {
            $user = Auth::user();
            $contenedor = Contenedor::find($id);

            if (!$contenedor) {
                return false;
            }

            if (in_array($user->No_Grupo, ['roleContenedorAlmacen'])) {
                $contenedor->update(['estado_china' => $estado]);
            } else {
                $contenedor->update(['estado' => $estado]);
            }

            return "success";
        } catch (Exception $e) {
            Log::error('Error en updateEstado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el estado de documentación
     */
    public function updateEstadoDocumentacion($id, $estado)
    {
        try {
            $contenedor = Contenedor::find($id);
            if ($contenedor) {
                $updated = $contenedor->update(['estado_documentacion' => $estado]);
                return $updated ? "success" : false;
            }
            return false;
        } catch (Exception $e) {
            Log::error('Error en updateEstadoDocumentacion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sube un archivo único
     */
    private function uploadSingleFile($file, $path)
    {
        try {
            $fileName = time() . '_' . $file['name'];
            $fullPath = public_path($path . $fileName);

            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                return $path . $fileName;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error en uploadSingleFile: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene datos de embarque (placeholder - implementar según necesidades)
     */
    private function getEmbarqueData($file, $data)
    {
        // Implementar según la lógica específica de tu aplicación
        return [];
    }

    /**
     * Obtiene datos de embarque modificados (placeholder - implementar según necesidades)
     */
    private function getEmbarqueDataModified($file, $data)
    {
        // Implementar según la lógica específica de tu aplicación
        return [];
    }
    public function deleteCotizacionFile($id)
    {
        try {
            $cotizacion = Cotizacion::find($id);
            if (!$cotizacion) {
                return false;
            }

            $oldFileUrl = $cotizacion->cotizacion_file_url;
            if ($oldFileUrl && file_exists($oldFileUrl)) {
                unlink($oldFileUrl);
            }

            $cotizacion->update(['cotizacion_file_url' => null]);
            return response()->json(['message' => 'Cotizacion file deleted successfully', 'success' => true]);
        } catch (Exception $e) {
            Log::error('Error en deleteCotizacionFile: ' . $e->getMessage());
            return false;
        }
    }
}
