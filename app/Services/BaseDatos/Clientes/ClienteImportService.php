<?php

namespace App\Services\BaseDatos\Clientes;

use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\ImportCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use App\Models\PedidoCurso;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Facades\DB;

class ClienteImportService
{
    private const EXCEL_IMPORTS_STORAGE_PATH = 'public/excel-imports/';
    /**
     * Importar clientes desde Excel
     */
    public function importarClientes(Request $request)
    {
        try {
            if (!$request->hasFile('excel_file')) {
                return [
                    'success' => false,
                    'message' => 'No se ha proporcionado ningún archivo'
                ];
            }

            $file = $request->file('excel_file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->getRealPath();

            // Validar tipo de archivo
            $allowedTypes = ['xlsx', 'xls', 'xlsm'];
            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (!in_array($fileExtension, $allowedTypes)) {
                return [
                    'success' => false,
                    'message' => 'El archivo debe ser un Excel (.xlsx o .xls)'
                ];
            }
            //guardar el archivo en el storage
            $tempPath = $file->store('temp');
            $fullTempPath = storage_path('app/' . $tempPath);
            $importId = $this->crearRegistroImportacion($fileName, $fullTempPath);

            // Procesar el archivo
            $resultado = $this->procesarExcel($fullTempPath, $importId, $fileName);

            return [
                'success' => true,
                'message' => 'Importación completada exitosamente',
                'data' => $resultado
            ];
        } catch (\Exception $e) {
            Log::error('Error al importar clientes: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al importar clientes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear registro de importación
     */
    private function crearRegistroImportacion($fileName, $path)
    {
        return ImportCliente::create([
            'nombre_archivo' => $fileName,
            'cantidad_rows' => 0,
            'ruta_archivo' => $path,
            'empresa_id' => 1,
            'usuario_id' => 1,
            'estadisticas' => [],
            'tipo_importacion' => 'clientes',
        ])->id;
    }

    /**
     * Procesar archivo Excel
     */
    private function procesarExcel($filePath, $importId, $fileName)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $stats = [
            'total' => 0,
            'creados' => 0,
            'actualizados' => 0,
            'errores' => 0,
            'detalles' => []
        ];

        $highestRow = $worksheet->getHighestRow();

        // Empezar desde la fila 3 (asumiendo que las filas 1-2 son encabezados)
        for ($row = 3; $row <= $highestRow; $row++) {

            try {
                $data = $this->leerFilaExcel($worksheet, $row);

                // Verificar si la fila tiene datos válidos (no está vacía o es una celda mergeada)
                if ($this->esFilaValida($data)) {
                    if ($this->validarDatosFila($data)) {
                        $this->procesarFilaCliente($data, $importId, $stats, $row);
                        $stats['total']++;
                    } else {
                        $stats['errores']++;
                        $stats['detalles'][] = "Fila {$row}: Datos incompletos o inválidos";
                        // No hacer break, continuar con la siguiente fila
                    }
                } else {
                    // Fila vacía o con celdas mergeadas, continuar sin procesar
                    Log::info("Fila {$row}: Fila vacía o con celdas mergeadas, omitiendo");
                    continue;
                }

            } catch (\Exception $e) {
                $stats['errores']++;
                $stats['detalles'][] = "Fila {$row}: Error - " . $e->getMessage();
                Log::error("Error procesando fila {$row}: " . $e->getMessage());
            }
        }

        // Actualizar estado de importación
        $this->actualizarEstadoImportacion($importId, $stats);

        return $stats;
    }

    /**
     * Leer datos de una fila del Excel
     */
    private function leerFilaExcel($worksheet, $row)
    {
        return [
            'cliente' => $this->obtenerValorCelda($worksheet, $row, 'B'),
            'dni' => $this->obtenerValorCelda($worksheet, $row, 'C'),
            'ruc' => $this->obtenerValorCelda($worksheet, $row, 'I'),
            'razon_social' => $this->obtenerValorCelda($worksheet, $row, 'J'),
            'correo' => $this->obtenerValorCelda($worksheet, $row, 'D'),
            'whatsapp' => $this->obtenerValorCelda($worksheet, $row, 'E'),
            'fecha' => $this->obtenerValorCelda($worksheet, $row, 'G'),
            'servicio' => $this->obtenerValorCelda($worksheet, $row, 'F'),
            'carga' => $this->obtenerValorCelda($worksheet, $row, 'H'),
        ];
    }

    /**
     * Obtener valor de una celda específica
     */
    private function obtenerValorCelda($worksheet, $row, $col)
    {
        $cellValue = $worksheet->getCell($col . $row)->getValue();
        return $cellValue ? trim($cellValue) : '';
    }

    /**
     * Verificar si una fila contiene datos válidos (no está vacía o es una celda mergeada)
     */
    private function esFilaValida($data)
    {
        // Verificar si al menos uno de los campos principales tiene contenido
        $camposPrincipales = ['cliente', 'dni', 'ruc', 'correo', 'whatsapp'];
        
        foreach ($camposPrincipales as $campo) {
            if (!empty(trim($data[$campo]))) {
                return true;
            }
        }
        
        // Si todos los campos principales están vacíos, la fila no es válida
        return false;
    }

    /**
     * Validar datos de una fila
     */
    private function validarDatosFila($data)
    {
        // Validar que al menos tenga nombre y documento
        return !empty($data['cliente']);
    }

    /**
     * Procesar fila de cliente
     */
    private function procesarFilaCliente($data, $importId, &$stats, $row)
    {
        DB::beginTransaction();
        try {
            $clienteExistente = null;
            
            // Limpiar espacios del teléfono/WhatsApp
            $whatsappLimpio = preg_replace('/\s+/', '', $data['whatsapp']);
            
            // Buscar secuencialmente: primero por teléfono, luego por DNI, luego por RUC
            if (!empty($whatsappLimpio)) {
                $clienteExistente = Cliente::where('telefono', 'LIKE', '%' . $whatsappLimpio . '%')->first();
            }
            
            // Si no encontró por teléfono, buscar por DNI
            if (!$clienteExistente && !empty(trim($data['dni']))) {
                $clienteExistente = Cliente::where('documento', $data['dni'])->first();
            }
            
            // Si no encontró por DNI, buscar por RUC
            if (!$clienteExistente && !empty(trim($data['ruc']))) {
                $clienteExistente = Cliente::where('ruc', $data['ruc'])->first();
            }
            
            Log::info($clienteExistente);
            if ($clienteExistente) {
                // Actualizar cliente existente
                $clienteExistente->update([
                    'nombre' => $data['cliente'],
                    'ruc' => $data['ruc'],
                    'empresa' => $data['razon_social'],
                    'correo' => $data['correo'],
                    'telefono' => $data['whatsapp'],
                    'fecha' => $this->convertirFechaExcel($data['fecha']),
                ]);
                if ($data['servicio'] == 'CONSOLIDADO') {
                    $carga = $data['carga'];
                    $carga = explode('#', $carga)[1];
                    $consolidado = Contenedor::where('carga', $carga)->first();
                    $cotizacion = Cotizacion::create([
                        'id_contenedor' => $consolidado->id,
                        'id_tipo_cliente' => 1,
                        'id_cliente' => $clienteExistente->id,
                        'fecha' => $this->convertirFechaExcel($data['fecha']),
                        'nombre' => $data['cliente'],
                        'documento' => $data['dni'],
                        'correo' => $data['correo'],
                        'telefono' => $data['whatsapp'],
                        'id_cliente_importacion' => $importId,
                        'estado_cliente' => 'NO RESERVADO',
                        'estado_cotizador' => 'CONFIRMADO',
                    ]);
                    
                } else {
                    PedidoCurso::create([
                        'id_cliente' => $clienteExistente->id,
                        'id_cliente_importacion' => $importId,
                        'fecha' => $this->convertirFechaExcel($data['fecha']),
                        'nombre' => $data['cliente'],
                    ]);
                }

                $stats['actualizados']++;
                $stats['detalles'][] = "Fila {$row}: Cliente actualizado - {$data['cliente']}";
            } else {
                // Crear nuevo cliente
                $cliente = Cliente::create([
                    'nombre' => $data['cliente'],
                    'documento' => $data['dni'],
                    'ruc' => $data['ruc'],
                    'empresa' => $data['razon_social'],
                    'correo' => $data['correo'],
                    'telefono' => $data['whatsapp'],
                    'fecha' => $this->convertirFechaExcel($data['fecha']),
                    'id_cliente_importacion' => $importId,
                ]);
    
                if (trim($data['servicio']) == 'CONSOLIDADO') {
                    $carga = $data['carga'];
                    $carga = explode('#', $carga)[1];
                    $consolidado = Contenedor::where('carga', $carga)->first();
                    $cotizacion = Cotizacion::create([
                        'id_contenedor' => $consolidado->id,
                        'id_tipo_cliente' => 1,
                        'id_cliente' => $cliente->id,
                        'fecha' => $this->convertirFechaExcel($data['fecha']),
                        'nombre' => $data['cliente'],
                        'documento' => $data['dni'],
                        'correo' => $data['correo'],
                        'telefono' => $data['whatsapp'],
                        'id_cliente_importacion' => $importId,
                        'estado_cliente' => 'NO RESERVADO',
                        'estado_cotizador' => 'CONFIRMADO',
                    ]);
                } else {
                    PedidoCurso::create([
                        'id_cliente' => $cliente->id,
                        'id_cliente_importacion' => $importId,
                        'servicio' => 'CURSO',
                        'fecha' => $this->convertirFechaExcel($data['fecha']),
                        'nombre' => $data['cliente'],
                    ]);
                }
                $stats['creados']++;
                $stats['detalles'][] = "Fila {$row}: Cliente creado - {$data['cliente']}";
            }
            DB::commit();
        } catch (\Exception $e) {
            Log::error('Error al procesar fila cliente: ' . $e->getMessage());
            DB::rollBack();
        }
    }
    private function convertirFechaExcel($excelDate)
    {
        // Si está vacío, retornar string vacío
        if (empty($excelDate)) {
            return '';
        }

        // Si es un string de fecha normal, intentar parsearlo
        if (is_string($excelDate)) {
            // Intentar diferentes formatos de fecha
            $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y', 'Y-m-d H:i:s'];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $excelDate);
                    return $date->format('d-m-Y');
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // Si es un número (formato Excel serial)
        if (is_numeric($excelDate)) {
            try {
                // Convertir número serial de Excel a timestamp
                // Excel usa 1 = 1 de enero de 1900, pero hay un bug: considera 1900 como año bisiesto
                $excelEpoch = 25569; // Días desde 1/1/1900 hasta 1/1/1970
                $unixTimestamp = ($excelDate - $excelEpoch) * 86400; // 86400 segundos por día

                $date = Carbon::createFromTimestamp($unixTimestamp);
                return $date->format('d-m-Y');
            } catch (\Exception $e) {
                return '';
            }
        }

        // Si es un objeto Carbon o DateTime
        if ($excelDate instanceof Carbon || $excelDate instanceof \DateTime) {
            return $excelDate->format('d-m-Y');
        }

        return '';
    }
    /**
     * Parsear fecha desde string
     */
    private function parsearFecha($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        //FORMAT EXCEL  fecha in format 45292 TO d-m-Y


        try {
            // Intentar diferentes formatos de fecha
            $formats = [
                'd/m/Y',
                'Y-m-d',
                'd-m-Y',
                'm/d/Y',
                'Y/m/d'
            ];

            foreach ($formats as $format) {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date !== false) {
                    return $date;
                }
            }

            // Si no coincide con ningún formato, intentar parse automático
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("No se pudo parsear la fecha: {$dateString}");
            return null;
        }
    }

    /**
     * Actualizar estado de importación
     */
    private function actualizarEstadoImportacion($importId, $stats)
    {
        $estado = $stats['errores'] > 0 ? 'CON_ERRORES' : 'COMPLETADO';

        ImportCliente::where('id', $importId)->update([
            'cantidad_rows' => $stats['total'],
            'estadisticas' => $stats
        ]);
    }

    /**
     * Obtener lista de importaciones
     */
    public function obtenerListaImportaciones()
    {
        return ImportCliente::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($import) {
                return [
                    'id' => $import->id,
                    'nombre_archivo' => $import->nombre_archivo,
                    'created_at' => $import->created_at->format('d/m/Y H:i:s'),
                    'cantidad_rows' => $import->cantidad_rows,
                    'estadisticas' => $import->estadisticas,
                    'ruta_archivo' => $this->generateImageUrl($import->ruta_archivo),
                ];
            });
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
        
        // Generar URL completa desde storage
        return Storage::disk('public')->url($ruta);
    }
    /**
     * Eliminar importación
     */
    public function eliminarImportacion($id)
    {
        try {
            PedidoCurso::where('id_cliente_importacion', $id)->delete();
            Cotizacion::where('id_cliente_importacion', $id)->delete();
            Cliente::where('id_cliente_importacion', $id)->delete();

            $import = ImportCliente::findOrFail($id);

            // Eliminar clientes asociados a esta importación

            // Eliminar registro de importación
            $import->delete();

            return [
                'success' => true,
                'message' => 'Importación eliminada exitosamente'
            ];
        } catch (\Exception $e) {
            Log::error('Error al eliminar importación: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar importación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Descargar plantilla de importación
     */
    public function descargarPlantilla(Request $request)
    {
        try {
            $tipo = $request->get('tipo', 'clientes');
            $path = storage_path('app/templates/plantilla_' . $tipo . '.xlsx');

            if (!file_exists($path)) {
                $this->crearPlantilla($path, $tipo);
            }

            return response()->download($path, 'plantilla_' . $tipo . '.xlsx');
        } catch (\Exception $e) {
            Log::error('Error al descargar plantilla: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear plantilla de importación
     */
    private function crearPlantilla($path, $tipo)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configurar encabezados según el tipo
        if ($tipo === 'clientes') {
            $headers = [
                'A1' => 'NOMBRE_CLIENTE',
                'B1' => 'DNI',
                'C1' => 'RUC',
                'D1' => 'RAZON_SOCIAL',
                'E1' => 'CORREO',
                'F1' => 'WHATSAPP',
                'G1' => 'FECHA_REGISTRO'
            ];
        } else {
            $headers = [
                'A1' => 'NOMBRE_CLIENTE',
                'B1' => 'DNI',
                'C1' => 'CORREO',
                'D1' => 'WHATSAPP',
                'E1' => 'FECHA_REGISTRO'
            ];
        }

        // Aplicar encabezados
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Ajustar ancho de columnas
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Crear directorio si no existe
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Guardar archivo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }
}
