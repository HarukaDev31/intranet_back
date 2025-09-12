<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Contenedor;

class GeneralExportService
{
    public function exportarClientes(Request $request, $query=null)
    {
        try{
            // Obtener datos filtrados
            $datosExport = $this->obtenerDatosParaExportar($request, $query);
            
            // Crear el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            // Configurar encabezados
            $this->configurarEncabezados($sheet);
            
            // Llenar datos y obtener información de dimensiones
            $infoDimensiones = $this->llenarDatosExcel($sheet, $datosExport);
            
            // Aplicar formato y estilos
            // $this->aplicarFormatoExcel($sheet, $infoDimensiones);
            
            // Generar y descargar archivo
            return $this->generarDescargaExcel($spreadsheet);
        } catch (\Exception $e) {
            Log::error('Error al exportar clientes: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo generar el archivo.'], 500);
        }
    }
    /**
     * Obtiene los datos filtrados para la exportación
     */
    private function obtenerDatosParaExportar(Request $request, $id)
    {
        //consulta base (para obtener carga,f_cierre,asesor,fecha,cliente,f_updated_at,nombre,documento,correo,telefono,tipo_cliente,volumen,volumen_china,fob,logistica,impuesto,tarifa)
        $query = Cliente::query()
            ->select('clientes.*', 'tipo_cliente.name')
            ->leftJoin('contenedor_consolidado_tipo_cliente as tipo_cliente', 'clientes.id_tipo_cliente', '=', 'tipo_cliente.id')
            ->where('clientes.id_contenedor', $id);

        //obtener datos de la tabla carga_consolidado_contenedor
        $contenedor = Contenedor::find($id);


        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.fecha');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $clientes = $query->get();

        $datosExport = [];
        $index = 1;
        foreach ($clientes as $cliente) {
            $datosExport[] = [
                'n' => $index++,
                'carga' => $contenedor->carga ?? '',
                'fecha_cierre' => $contenedor->f_cierre ? Carbon::parse($contenedor->f_cierre)->format('d/m/Y') : '',
                'asesor' => $cliente->asesor ?? '',
                'COD' => $this->buildCod($contenedor, $cliente),
                'fecha' => $contenedor->fecha ?? '',
                'cliente' => $contenedor->nombre ?? '',
                'documento' => $cliente->documento ?? '',
                'correo' => $cliente->correo ?? '',
                'telefono' => $cliente->telefono ?? '',
                'tipo_cliente' => $cliente->name ?? '',
                'volumen' => $cliente->volumen ?? '',
                'volumen_china' => $cliente->volumen_china ?? '',
                'fob' => $cliente->fob ?? '',
                'logistica' => $cliente->logistica ?? '',
                'impuesto' => $cliente->impuesto ?? '',
                'tarifa' => $cliente->tarifa ?? '',
            ];
        }
        return $datosExport;
    }
    //Genera el archivo Excel y lo prepara para descarga
    private function generarDescargaExcel($spreadsheet)
    {
        $fileName = 'Reporte_cotizaciones_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);
    }
    //Configura los encabezados del Excel
    private function configurarEncabezados($sheet)
    {
        $headers = [
            'B2' => 'Reporte de Clientes',
            'B3' => 'N°',
            'C3' => 'Carga',
            'D3' => 'F. Cierre',
            'E3' => 'Asesor',
            'F3' => 'COD',
            'G3' => 'Fecha',
            'H3' => 'Cliente',
            'I3' => 'Documento',
            'J3' => 'Correo',
            'K3' => 'Teléfono',
            'L3' => 'Tipo Cliente',
            'M3' => 'Volumen',
            'N3' => 'Volumen China',
            'O3' => 'FOB',
            'P3' => 'Logística',
            'Q3' => 'Impuesto',
            'R3' => 'Tarifa'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Estilos para el título
        $sheet->mergeCells('B2:R2');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('B2')->getAlignment()->setHorizontal('center');
        
                // Estilos para los encabezados de columna
        $sheet->getStyle('B3:R3')->getFont()->setBold(true);
        $sheet->getStyle('B3:U3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('B3:U3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B3:U3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }

    //Llena los datos en el Excel y obtiene información de dimensiones
    private function llenarDatosExcel($sheet, $datosExport)
    {
        $row = 4; // Comenzar desde la fila 4
        $maxServiceCount = 0;

        foreach ($datosExport as $data) {
            $col = 'B';
            foreach ($data as $key => $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        return [
            'totalRows' => $row - 1,
            'maxServiceCount' => $maxServiceCount
        ];
    }

    /**
     * Parse a date value safely and return formatted d/m/Y or empty string.
     * Accepts DateTime, Carbon, timestamps, or strings in common formats (Y-m-d, d/m/Y, etc.).
     */    private function safeFormatDate($date)
    {
        if (!$date) {
            return '';
        }

        try {
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->format('d/m/Y');
            } elseif (is_numeric($date)) {
                return Carbon::createFromTimestamp($date)->format('d/m/Y');
            } elseif (is_string($date)) {
                // Try common formats
                $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
                foreach ($formats as $format) {
                    $parsed = Carbon::createFromFormat($format, $date);
                    if ($parsed && $parsed->format($format) === $date) {
                        return $parsed->format('d/m/Y');
                    }
                }
                // Fallback to Carbon's parser
                return Carbon::parse($date)->format('d/m/Y');
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$date}. Error: " . $e->getMessage());
        }

        return '';
    }
    /**
     * Construye el COD: carga + fecha (dmy con año de 2 dígitos) + primeras 3 letras del nombre en mayúsculas
     */
    private function buildCod($contenedor, $cotizacion)
    {
        try {
            $carga = $contenedor->carga ?? '';
            $fechaPart = '';
            if (!empty($cotizacion->fecha)) {
                try {
                    $fechaPart = Carbon::parse($cotizacion->fecha)->format('dmy');
                } catch (\Exception $e) {
                    $fechaPart = date('dmy', strtotime($cotizacion->fecha ?? 'now'));
                }
            }
            $nombrePart = strtoupper(substr($cotizacion->nombre ?? '', 0, 3));
            return trim($carga . $fechaPart . $nombrePart);
        } catch (\Exception $e) {
            return $cotizacion->cod ?? '';
        }
    }
    /**
     * Aplica formato y estilos al Excel
     */    private function aplicarFormatoExcel($sheet, $infoDimensiones)
    {
        $totalRows = $infoDimensiones['totalRows'];
        $maxServiceCount = $infoDimensiones['maxServiceCount'];

        // Ajustar ancho de columnas
        foreach (range('B', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Bordes para todo el rango de datos
        $sheet->getStyle("B3:R{$totalRows}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Alineación vertical para todas las celdas con datos
        $sheet->getStyle("B4:R{$totalRows}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    }
}