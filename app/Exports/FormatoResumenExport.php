<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FormatoResumenExport implements FromArray, WithEvents
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $usedRange = 'A1:' . $highestColumn . $highestRow;

                // General borders
                $sheet->getStyle($usedRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // Header style (first row)
                $sheet->getStyle('A1:H1')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

                // Column widths to match the image layout
                $sheet->getColumnDimension('A')->setWidth(6);   // N°
                $sheet->getColumnDimension('B')->setWidth(40);  // CLIENTE
                $sheet->getColumnDimension('C')->setWidth(18);  // CELULAR
                $sheet->getColumnDimension('D')->setWidth(14);  // CBM TOTAL
                $sheet->getColumnDimension('E')->setWidth(14);  // TOTAL CAJAS
                $sheet->getColumnDimension('F')->setWidth(30);  // OBSERVACION
                $sheet->getColumnDimension('G')->setWidth(14);  // # DE GUIA
                $sheet->getColumnDimension('H')->setWidth(18);  // FIRMA

                // Align columns
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C2:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D2:D' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G2:G' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Set default row height and enable wrap for cliente and observation
                for ($r = 1; $r <= (int)$highestRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }
                $sheet->getStyle('B2:B' . $highestRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('F2:F' . $highestRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

                // Make header bold and slightly larger
                $sheet->getStyle('A1:H1')->getFont()->setSize(13)->setBold(true);
            }
        ];
    }
}
