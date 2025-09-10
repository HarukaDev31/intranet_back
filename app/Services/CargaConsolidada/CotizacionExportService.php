<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class CotizacionExportService
{
    protected $cotizacionService;

    //Exportar a Excel
    public function exportarCotizacion(Request $request)
    {
        try{
            // Obtener datos filtrados
            // $datosExport = $this->obtenerDatosParaExportar($request);

            //Crea el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();


            //Configura los encabezados
            // $this->configurarEncabezados($sheet);

            //Llena los datos
            // $info = $this->llenarDatosExcel($sheet, $datosExport);

            //Aplica formato y estilos
            // $this->aplicarFormatoExcel($sheet, $info);

            //Genera el archivo Excel
            return $this->generarDescargaExcel($spreadsheet);

        }catch (\Throwable $e) {
            Log::error('Error exportarCotizacion: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar export: ' . $e->getMessage()
            ], 500);
        }
    }

    //Obtiene los datos filtrados para la exportación
    private function obtenerDatosParaExportar(Request $request)
    {
        $query = Cotizacion::query();

        $query->orderBy('created_at', 'desc');
        $cotizaciones = $query->get();

        $datosExport = [];

        foreach ($cotizaciones as $cotizacion) {
            $datosExport[] = [
                'cotizacion' => $cotizacion->cotizacion,
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
            'B2' => 'Reporte de Cotizaciones',
            'B3' => 'N',
            'C3' => 'Carga',
            'D3' => 'F. Cierre',
            'E3' => 'Asesor',
            'F3' => 'COD',
            'G3' => 'Fecha',
            'H3' => 'Fecha Modificación',
            'I3' => 'Nombre Cliente',
            'J3' => 'DNI/RUC',
            'K3' => 'Correo',
            'L3' => 'Whatsapp',
            'M3' => 'Tipo Cliente',
            'N3' => 'Volumen',
            'O3' => 'Volumen China',
            'P3' => 'Qty Item',
            'Q3' => 'FOB',
            'R3' => 'Logistica',
            'S3' => 'Impuesto',
            'T3' => 'Tarifa',
            'U3' => 'Estado',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Estilos para el título
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Estilos para los encabezados de columna
        $sheet->getStyle('A3:B3')->getFont()->setBold(true);
        $sheet->getStyle('A3:B3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('A3:B3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(50);
    }
    //Llena los datos en el Excel
    private function llenarDatosExcel($sheet, $datosExport)
    {
        $row = 4; // Inicia en la fila 4 después de los encabezados
        $n = 1; // Contador para la columna N

        foreach ($datosExport as $data) {
            $sheet->setCellValue('B' . $row, $data['cotizacion']);
            $sheet->setCellValue('C' . $row, $data['carga']);
            $sheet->setCellValue('D' . $row, $data['fecha_cierre']);
            $sheet->setCellValue('E' . $row, $data['asesor']);
            $sheet->setCellValue('F' . $row, $data['cod']);
            $sheet->setCellValue('G' . $row, isset($data['created_at']) ? Carbon::parse($data['created_at'])->format('Y-m-d') : '');
            $sheet->setCellValue('H' . $row, isset($data['updated_at']) ? Carbon::parse($data['updated_at'])->format('Y-m-d') : '');
            $sheet->setCellValue('I' . $row, $data['nombre_cliente']);
            $sheet->setCellValue('J' . $row, $data['dni_ruc']);
            $sheet->setCellValue('K' . $row, $data['correo']);
            $sheet->setCellValue('L' . $row, $data['whatsapp']);
            $sheet->setCellValue('M' . $row, $data['tipo_cliente']);
            $sheet->setCellValue('N' . $row, $data['volumen']);
            $sheet->setCellValue('O' . $row, $data['volumen_china']);
            $sheet->setCellValue('P' . $row, $data['qty_item']);
            $sheet->setCellValue('Q' . $row, $data['fob']);
            $sheet->setCellValue('R' . $row, $data['logistica']);
            $sheet->setCellValue('S' . $row, $data['impuesto']);
            $sheet->setCellValue('T' . $row, $data['tarifa']);
            $sheet->setCellValue('U' . $row, $data['estado']);
        
        $row++;
            $n++;
        }
        return [
            'lastRow' => $row - 1, 
            'totalRows' => count($datosExport)
        ];
    }

    /**
     * Obtiene la letra de columna de Excel a partir de un índice numérico.
     */
    private function getColumnLetter($column = 'A', $i = 1)
    {
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) + $i;
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
    }

    /**
     * Aplica formato y estilos al Excel
     */
    private function aplicarFormatoExcel($sheet, $info)
    {
        $lastRow = $info['lastRow'];
        $totalRows = $info['totalRows'];

        //Unir celdas para el título
        $sheet->mergeCells("B2:U2");

        //unir celdas de encabezados
        $sheet->mergeCells("B3:U3");

        //Configurar ancho de columnas
        $columnWidths = [
            'A' => 5,
            'B' => 20,
            'C' => 15,
            'D' => 15,
            'E' => 20,
            'F' => 10,
            'G' => 15,
            'H' => 20,
            'I' => 30,
            'J' => 15,
            'K' => 25,
            'L' => 15,
            'M' => 15,
            'N' => 10,
            'O' => 15,
            'P' => 10,
            'Q' => 15,
            'R' => 15,
            'S' => 10,
            'T' => 10,
            'U' => 15,
        ];

        //Aplicar los anchos de columna
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Bordes para todo el rango de datos
        $sheet->getStyle("A3:U{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Alineación para todo el rango de datos
        $sheet->getStyle("A3:U{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("A3:U{$lastRow}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A3:U{$lastRow}")->getAlignment()->setWrapText(true);

        // Formato de fecha para las columnas de fecha
        $sheet->getStyle("G4:G{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle("H4:H{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        // Ajuste automático de ancho de columnas
        foreach (range('A', 'U') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }
}