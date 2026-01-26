<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RotuladoParedExport implements FromArray, WithEvents
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

                $sheet->getStyle($usedRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // Make big printable boxes: wider name column and large row heights
                try {
                    // Adjust columns: A may be present as an empty column in some viewers, but set first 3 columns
                    $sheet->getColumnDimension('A')->setWidth(70);
                    $sheet->getColumnDimension('B')->setWidth(30); // Cliente (very wide)
                    $sheet->getColumnDimension('C')->setWidth(30); // CBM

                    // Set large font sizes per column
                    $sheet->getStyle('A1:A' . $highestRow)->getFont()->setSize(42)->setBold(true);
                    $sheet->getStyle('B1:B' . $highestRow)->getFont()->setSize(36);
                    $sheet->getStyle('C1:C' . $highestRow)->getFont()->setSize(36);

                    // Set row heights dynamically per-row based on the text in column A (name)
                    $nameFontSize = 48;
                    $lineHeightFactor = 1.15; // multiplier for font size to compute line height
                    // get column A width and estimate chars per line
                    $colWidth = $sheet->getColumnDimension('A')->getWidth();
                    $charsPerLine = max(20, (int)round($colWidth * 1.1));

                    for ($row = 1; $row <= $highestRow; $row++) {
                        // read the cell value for the name column (A)
                        try {
                            $cellValue = (string)$sheet->getCell('A' . $row)->getValue();
                        } catch (\Exception $e) {
                            $cellValue = '';
                        }
                        $cellTrim = trim($cellValue);
                        if ($cellTrim === '') {
                            $lines = 1;
                        } else {
                            // Wrap by estimated chars per line preserving words when possible
                            $wrapped = wordwrap($cellTrim, $charsPerLine, "\n", true);
                            $lines = substr_count($wrapped, "\n") + 1;
                            // If cell already contains explicit newlines, ensure we count them
                            $explicitLines = substr_count($cellTrim, "\n") + 1;
                            $lines = max($lines, $explicitLines);
                        }
                        // compute height: lines * fontSize * factor + small padding
                        $rowHeight = (int)ceil($lines * $nameFontSize * $lineHeightFactor + 8);
                        // enforce minimum 160 and reasonable maximum
                        $rowHeight = max(160, min($rowHeight, 800));
                        $sheet->getRowDimension($row)->setRowHeight($rowHeight);
                    }

                    // Center texts and enable wrapping
                    $sheet->getStyle('A1:A' . $highestRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $sheet->getStyle('B1:C' . $highestRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                    // Set page orientation to landscape for better printing
                    try {
                        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                    } catch (\Throwable $e) {
                        // ignore if page setup is not available in this environment
                    }
                } catch (\Exception $e) {
                    // Ignore if column dimension adjustments fail on some drivers
                }
            }
        ];
    }
}
