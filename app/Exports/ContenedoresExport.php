<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Listado de consolidados (mismas filas que devuelve ContenedorController::index).
 */
class ContenedoresExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    /** @var array<int, array<string, mixed>> */
    protected $rows;

    /** @var string País destino para el encabezado de CBM (Ecuador para socios, país del consolidado en org 1). */
    protected $destino;

    public function __construct(array $rows, string $destino = 'Perú')
    {
        $this->rows = $rows;
        $this->destino = $destino !== '' ? $destino : 'Perú';
    }

    public static function nombrePais($pais): string
    {
        if (is_object($pais)) {
            return (string) ($pais->No_Pais ?? '');
        }
        if (is_array($pais)) {
            return (string) ($pais['No_Pais'] ?? '');
        }

        return '';
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'Carga',
            'Mes',
            'Año',
            'País',
            'Empresa',
            'F. Cierre',
            'F. Arribo',
            'F. Entrega',
            'Estado',
            'CBM ' . $this->destino,
            'CBM China',
            'CBM IMO',
        ];
    }

    public function map($row): array
    {
        return [
            'CARGA CONSOLIDADA #' . ($row['carga'] ?? ''),
            $row['mes'] ?? '',
            $row['anio'] ?? '',
            self::nombrePais($row['pais'] ?? null),
            $row['empresa'] ?? '',
            $this->fecha($row['f_cierre'] ?? null),
            $this->fecha($row['fecha_arribo'] ?? ($row['f_puerto'] ?? null)),
            $this->fecha($row['f_entrega'] ?? null),
            $row['estado_china'] ?? '',
            $row['cbm_total_peru'] ?? 0,
            $row['cbm_total_china'] ?? 0,
            $row['cbm_total_imo'] ?? 0,
        ];
    }

    private function fecha($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
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
                ])->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563EB'],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);
            },
        ];
    }
}
