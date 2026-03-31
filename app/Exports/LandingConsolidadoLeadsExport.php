<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LandingConsolidadoLeadsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'WhatsApp',
            'Proveedor',
            'Campaña',
            'IP',
            'Fecha',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->nombre,
            $row->whatsapp,
            $row->proveedor,
            $row->codigo_campana,
            $row->ip_address,
            $row->created_at ? date('d/m/Y H:i', strtotime($row->created_at)) : '',
        ];
    }
}

