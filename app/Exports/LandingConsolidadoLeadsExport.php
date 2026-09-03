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
            'Origen formulario',
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
            $this->mapFormSource($row->form_source ?? null),
            $row->ip_address,
            $row->created_at ? date('d/m/Y H:i', strtotime($row->created_at)) : '',
        ];
    }

    private function mapFormSource(?string $value): string
    {
        if ($value === 'landing_consolidado_v2') {
            return 'META CONSOLIDADO';
        }
        if ($value === 'probusiness_pe') {
            return 'WEB';
        }

        return $value ?: '';
    }
}

