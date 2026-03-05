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

class ViaticosExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $viaticos;

    public function __construct($viaticos)
    {
        $this->viaticos = $viaticos;
    }

    public function collection()
    {
        return $this->viaticos;
    }

    public function headings(): array
    {
        return [
            'Codigo',
            'Asunto',
            'Fecha Reintegro',
            'Fecha Devolucion',
            'Area Solicitante',
            'Solicitante',
            'Monto',
            'Estado',
        ];
    }

    public function map($v): array
    {
        $nombreUsuario = optional($v->usuario)->No_Nombres_Apellidos ?? 'N/A';

        return [
            $v->codigo_confirmado ?? '',
            $v->subject ?? '',
            $v->reimbursement_date ? Carbon::parse($v->reimbursement_date)->format('d/m/Y') : '',
            $v->return_date ? Carbon::parse($v->return_date)->format('d/m/Y') : '',
            $v->requesting_area ?? '',
            $nombreUsuario,
            number_format((float) $v->total_amount, 2, '.', ','),
            $v->status ?? '',
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
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E8E8E8'],
                    ],
                ]);

                $sheet->getStyle($usedRange)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(40);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(25);
                $sheet->getColumnDimension('F')->setWidth(35);
                $sheet->getColumnDimension('G')->setWidth(14);
                $sheet->getColumnDimension('H')->setWidth(14);
            },
        ];
    }
}
