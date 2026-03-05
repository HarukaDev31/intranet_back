<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CalculadoraImportacionExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $calculos;

    public function __construct($calculos)
    {
        $this->calculos = $calculos;
    }

    public function collection()
    {
        return collect($this->calculos);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Cliente',
            'DNI',
            'WhatsApp',
            'Código',
            'Vol (CBM)',
            'Items',
            'FOB',
            'Logística',
            'Impuesto',
            'Tarifa',
            'Descuento',
            'Campaña',
            'Cotizador',
            'Vendedor',
            'Estado',
        ];
    }

    public function map($row): array
    {
        $totales = $row->totales ?? (object) ['total_cbm' => 0, 'total_productos' => 0];
        return [
            $row->id,
            $row->created_at ? Carbon::parse($row->created_at)->format('d/m/Y') : '',
            $row->nombre_cliente ?? '',
            $row->dni_cliente ?? '',
            $row->whatsapp_cliente ?? '',
            $row->cod_cotizacion ?? '',
            $totales->total_cbm ?? 0,
            $totales->total_productos ?? 0,
            $row->total_fob ?? 0,
            $row->logistica ?? 0,
            $row->total_impuestos ?? 0,
            $row->tarifa ?? 0,
            $row->tarifa_descuento ?? 0,
            $row->carga_contenedor ?? '',
            $row->nombre_creador ?? '',
            $row->nombre_vendedor ?? '',
            $row->estado ?? '',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $usedRange = 'A1:' . $highestColumn . $highestRow;

                $sheet->getStyle($usedRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563EB'],
                    ],
                ]);

                $sheet->getStyle($usedRange)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getRowDimension(1)->setRowHeight(22);

                $widths = [8, 12, 22, 12, 14, 14, 10, 8, 12, 12, 12, 12, 12, 18, 18, 18, 12];
                foreach ($widths as $i => $w) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}
