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
 * Tabla de progreso del calendario (mismas filas y columnas que ve el jefe del grupo).
 */
class CalendarProgresoExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    private const ESTADOS = [
        'PENDIENTE' => 'Pendiente',
        'PROGRESO' => 'En progreso',
        'COMPLETADO' => 'Completado',
    ];

    private const PRIORIDADES = [
        0 => 'Bajo',
        1 => 'Medio',
        2 => 'Alto',
    ];

    /** @var array<int, array<string, mixed>> */
    protected $events;

    /** @var bool */
    protected $usaConsolidado;

    public function __construct(array $events, bool $usaConsolidado)
    {
        $this->events = $events;
        $this->usaConsolidado = $usaConsolidado;
    }

    public function collection()
    {
        return collect($this->events);
    }

    public function headings(): array
    {
        return array_merge(
            ['Actividad'],
            $this->usaConsolidado ? ['# Consolidado'] : [],
            ['Estado', 'Prioridad', 'Fecha Inicio', 'Fecha Fin', 'Duración', 'Responsables']
        );
    }

    public function map($row): array
    {
        $responsables = collect($row['charges'] ?? [])
            ->map(function ($charge) {
                return $charge['user']['nombre'] ?? null;
            })
            ->filter()
            ->implode(', ');

        $consolidado = $this->usaConsolidado ? [$row['contenedor']['nombre'] ?? '-'] : [];

        return array_merge(
            [$row['name'] ?? ''],
            $consolidado,
            [
                self::ESTADOS[$row['status'] ?? ''] ?? ($row['status'] ?? ''),
                self::PRIORIDADES[(int) ($row['priority'] ?? 0)] ?? '',
                $this->fecha($row['start_date'] ?? null),
                $this->fecha($row['end_date'] ?? null),
                $this->diasHabiles($row['start_date'] ?? null, $row['end_date'] ?? null) . ' días',
                $responsables,
            ]
        );
    }

    private function fecha($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /** Días hábiles (lun–vie) entre dos fechas, inclusive; mínimo 1 (igual que la tabla). */
    private function diasHabiles($start, $end): int
    {
        if (!$start || !$end) {
            return 1;
        }
        try {
            $from = Carbon::parse($start)->startOfDay();
            $to = Carbon::parse($end)->startOfDay();
        } catch (\Throwable $e) {
            return 1;
        }
        if ($from->gt($to)) {
            return 1;
        }

        $count = 0;
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            if (!$cursor->isWeekend()) {
                $count++;
            }
        }

        return $count ?: 1;
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
