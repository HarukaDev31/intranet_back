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

class ContabilidadPagosExport implements FromArray, WithStyles, WithEvents, WithColumnWidths
{
    /** @var Collection */
    protected $data;

    /** @var string inicial|final */
    protected $tipo;

    /** @var array<int, array{start: int, end: int}> */
    protected $mergeRanges = [];

    public function __construct(Collection $data, $tipo = 'inicial')
    {
        $this->data = $data;
        $this->tipo = $tipo;
    }

    public function array(): array
    {
        $headers = $this->tipo === 'final'
            ? ['N°', 'Cliente', 'Teléfono', 'Importe', 'Pagos', 'Pagado', 'Diferencia']
            : ['N°', 'Cliente', 'Teléfono', 'Importe', 'Pagos'];

        $rows = [$headers];
        $currentRow = 2;
        $this->mergeRanges = [];

        foreach ($this->data as $item) {
            $row = is_array($item) ? $item : (array) $item;
            $pagos = $this->normalizePagos($row['pagos'] ?? []);

            $importe = $this->tipo === 'final'
                ? (float) ($row['total_logistica_impuestos'] ?? 0)
                : (float) ($row['monto'] ?? 0);

            $pagado = (float) ($row['total_pagos'] ?? 0);
            $diferencia = $this->tipo === 'final'
                ? (float) ($row['diferencia'] ?? ($importe - $pagado))
                : 0;

            $base = [
                $row['index'] ?? '',
                $row['nombre'] ?? '',
                $row['telefono'] ?? '',
                $this->formatUsd($importe),
            ];

            $startRow = $currentRow;

            if (count($pagos) === 0) {
                $line = array_merge($base, ['']);
                if ($this->tipo === 'final') {
                    $line[] = $this->formatUsd($pagado);
                    $line[] = $this->formatUsd($diferencia);
                }
                $rows[] = $line;
                $currentRow++;
            } else {
                foreach ($pagos as $pago) {
                    $line = array_merge($base, [$this->formatPagoCell($pago)]);
                    if ($this->tipo === 'final') {
                        $line[] = $this->formatUsd($pagado);
                        $line[] = $this->formatUsd($diferencia);
                    }
                    $rows[] = $line;
                    $currentRow++;
                }
            }

            $endRow = $currentRow - 1;
            if ($endRow > $startRow) {
                $this->mergeRanges[] = ['start' => $startRow, 'end' => $endRow];
            }
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 6,
            'B' => 32,
            'C' => 16,
            'D' => 16,
            'E' => 22,
        ];
        if ($this->tipo === 'final') {
            $widths['F'] = 16;
            $widths['G'] = 16;
        }
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->tipo === 'final' ? 'G' : 'E';
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
            'A2:' . $lastCol . '10000' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
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
                $lastCol = $this->tipo === 'final' ? 'G' : 'E';

                $sheet->getStyle('A1:' . $lastCol . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $mergeCols = $this->tipo === 'final'
                    ? ['A', 'B', 'C', 'D', 'F', 'G']
                    : ['A', 'B', 'C', 'D'];

                foreach ($this->mergeRanges as $range) {
                    $start = $range['start'];
                    $end = $range['end'];
                    foreach ($mergeCols as $col) {
                        $sheet->mergeCells($col . $start . ':' . $col . $end);
                        $sheet->getStyle($col . $start . ':' . $col . $end)
                            ->getAlignment()
                            ->setVertical(Alignment::VERTICAL_CENTER);
                    }
                }

                $sheet->getStyle('D2:D' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                if ($this->tipo === 'final') {
                    $sheet->getStyle('F2:G' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    private function normalizePagos($pagos)
    {
        if (is_string($pagos)) {
            $decoded = json_decode($pagos, true);
            $pagos = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($pagos)) {
            return [];
        }
        return array_values($pagos);
    }

    private function formatPagoCell($pago)
    {
        if (!is_array($pago)) {
            return '';
        }
        $monto = $this->formatUsd($pago['monto'] ?? 0);
        $status = isset($pago['status']) ? trim((string) $pago['status']) : '';
        if ($status !== '') {
            return $monto . ' (' . $status . ')';
        }
        return $monto;
    }

    private function formatUsd($amount)
    {
        return '$' . number_format((float) $amount, 2, '.', ',');
    }
}
