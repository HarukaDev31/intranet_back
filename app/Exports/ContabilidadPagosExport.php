<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContabilidadPagosExport implements FromCollection, WithHeadings, WithMapping
{
    const MAX_PAGOS = 4;

    /** @var Collection */
    protected $data;

    /** @var string inicial|final */
    protected $tipo;

    public function __construct(Collection $data, $tipo = 'inicial')
    {
        $this->data = $data;
        $this->tipo = $tipo;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        $headings = ['N°', 'Cliente', 'Importe'];
        for ($i = 1; $i <= self::MAX_PAGOS; $i++) {
            $headings[] = 'Pago ' . $i;
        }
        if ($this->tipo === 'final') {
            $headings[] = 'Pagado';
            $headings[] = 'Diferencia';
        }
        return $headings;
    }

    public function map($row): array
    {
        $row = is_array($row) ? $row : (array) $row;
        $pagos = $row['pagos'] ?? [];
        if (is_string($pagos)) {
            $decoded = json_decode($pagos, true);
            $pagos = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($pagos)) {
            $pagos = [];
        }

        $importe = $this->tipo === 'final'
            ? (float) ($row['total_logistica_impuestos'] ?? 0)
            : (float) ($row['monto'] ?? 0);

        $mapped = [
            $row['index'] ?? '',
            $row['nombre'] ?? '',
            number_format($importe, 2, '.', ''),
        ];

        for ($i = 0; $i < self::MAX_PAGOS; $i++) {
            if (isset($pagos[$i]['monto'])) {
                $mapped[] = number_format((float) $pagos[$i]['monto'], 2, '.', '');
            } else {
                $mapped[] = '';
            }
        }

        if ($this->tipo === 'final') {
            $mapped[] = number_format((float) ($row['total_pagos'] ?? 0), 2, '.', '');
            $mapped[] = number_format((float) ($row['diferencia'] ?? 0), 2, '.', '');
        }

        return $mapped;
    }
}
