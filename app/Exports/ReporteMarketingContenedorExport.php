<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteMarketingContenedorExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $logisticaLabel;

    /**
     * @param  Collection  $data
     * @param  string  $logisticaLabel  Encabezado de la columna de logística (preliminar o final)
     */
    public function __construct(Collection $data, $logisticaLabel)
    {
        $this->data = $data;
        $this->logisticaLabel = $logisticaLabel;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'N°',
            'Cliente',
            'Tipo de cliente',
            'Lima / Provincia',
            'Departamento',
            'Provincia',
            $this->logisticaLabel,
            'Origen',
        ];
    }

    public function map($row): array
    {
        return [
            $row['numero'],
            $row['cliente'],
            $row['tipo_cliente'],
            $row['lima_provincia'],
            $row['departamento'],
            $row['provincia'],
            $row['logistica'],
            $row['origen'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 36,
            'C' => 16,
            'D' => 16,
            'E' => 18,
            'F' => 18,
            'G' => 20,
            'H' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                if ($highestRow > 1) {
                    $sheet->getStyle('G2:G' . $highestRow)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                }

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:' . $highestColumn . $highestRow);
            },
        ];
    }
}
