<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Datos de facturación (RUC/DNI, razón social, tipo de comprobante) de todos los
 * clientes de un contenedor, para el botón "Descargar" de Factura y Guía — Contabilidad.
 */
class FacturaGuiaClientesFacturacionExport implements FromArray, WithStyles, WithEvents, WithColumnWidths
{
    /** @var Collection */
    protected $data;

    /** @var int */
    protected $lastDataRow = 1;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $headers = ['Cliente', 'RUC / DNI', 'Razón Social', 'Número de celular', 'Tipo de comprobante'];
        $rows = [$headers];

        foreach ($this->data as $item) {
            $row = is_array($item) ? $item : (array) $item;
            $rows[] = [
                $row['nombre'] ?? '',
                $row['documento'] ?? '',
                $row['razon_social'] ?? '',
                $row['telefono'] ?? '',
                $row['tipo_comprobante'] ?? '',
            ];
        }

        $this->lastDataRow = count($rows) - 1;

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 16,
            'C' => 36,
            'D' => 18,
            'E' => 18,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
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
                $highestRow = $this->lastDataRow;
                if ($highestRow < 1) {
                    return;
                }

                $sheet->getStyle('A2:E' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle('A1:E' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle('B2:B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
