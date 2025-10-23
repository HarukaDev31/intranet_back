<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class EmbarqueExport implements FromArray, WithStyles, WithEvents
{
    protected $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $headers = [
            'ID Cotización',
            'Nombre Cliente',
            'Teléfono',
            'Estado Cotizador',
            'Fecha Confirmación',
            'Usuario',
            'ID Proveedor',
            'Supplier',
            'Code Supplier',
            'Qty Box',
            'Peso',
            'CBM Total',
            'CBM Total China',
            'Qty Box China',
            'Estados Proveedor',
            'Estados',
            'Supplier Phone',
            'Estado China',
            'Arrive Date China',
            'Send Rotulado Status',
            'Products'
        ];

        $rows = [$headers];

        foreach ($this->data as $cotizacion) {
            $proveedores = $cotizacion['proveedores'] ?? [];
            
            foreach ($proveedores as $index => $proveedor) {
                $row = [
                    $cotizacion['id'],
                    $cotizacion['nombre'],
                    $cotizacion['telefono'],
                    $cotizacion['estado_cotizador'],
                    $cotizacion['fecha_confirmacion'],
                    $cotizacion['No_Nombres_Apellidos'],
                    $proveedor['id'],
                    $proveedor['supplier'],
                    $proveedor['code_supplier'],
                    $proveedor['qty_box'],
                    $proveedor['peso'],
                    $proveedor['cbm_total'],
                    $proveedor['cbm_total_china'],
                    $proveedor['qty_box_china'],
                    $proveedor['estados_proveedor'],
                    $proveedor['estados'],
                    $proveedor['supplier_phone'],
                    $proveedor['estado_china'],
                    $proveedor['arrive_date_china'],
                    $proveedor['send_rotulado_status'],
                    $proveedor['products']
                ];
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Aplicar bordes a todas las celdas con datos
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // Mergear filas para cotizaciones con múltiples proveedores
                $this->mergeCotizacionRows($sheet);
            },
        ];
    }

    private function mergeCotizacionRows($sheet)
    {
        $currentCotizacionId = null;
        $startRow = 2; // Empezar desde la fila 2 (después del header)
        $endRow = 2;
        
        $highestRow = $sheet->getHighestRow();
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $cotizacionId = $sheet->getCell('A' . $row)->getValue();
            
            if ($currentCotizacionId === null) {
                $currentCotizacionId = $cotizacionId;
                $startRow = $row;
            } elseif ($currentCotizacionId != $cotizacionId) {
                // Mergear las filas de la cotización anterior
                if ($endRow > $startRow) {
                    $this->mergeCellsForCotizacion($sheet, $startRow, $endRow);
                }
                
                // Iniciar nueva cotización
                $currentCotizacionId = $cotizacionId;
                $startRow = $row;
            }
            
            $endRow = $row;
        }
        
        // Mergear la última cotización
        if ($endRow > $startRow) {
            $this->mergeCellsForCotizacion($sheet, $startRow, $endRow);
        }
    }

    private function mergeCellsForCotizacion($sheet, $startRow, $endRow)
    {
        // Columnas que deben mergearse (datos de la cotización que se repiten)
        $columnsToMerge = ['A', 'B', 'C', 'D', 'E', 'F']; // ID, Nombre, Teléfono, Estado, Fecha, Usuario
        
        foreach ($columnsToMerge as $column) {
            if ($endRow > $startRow) {
                $sheet->mergeCells($column . $startRow . ':' . $column . $endRow);
                
                // Centrar verticalmente el contenido mergeado
                $sheet->getStyle($column . $startRow . ':' . $column . $endRow)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }
        }
    }
}
