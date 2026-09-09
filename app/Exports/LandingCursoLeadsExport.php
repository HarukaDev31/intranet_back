<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LandingCursoLeadsExport implements FromCollection, WithHeadings, WithMapping
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
            'Email',
            'Experiencia importando',
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
            $row->email,
            $row->experiencia_importando,
            $row->codigo_campana,
            $this->mapFormSource($row->form_source ?? null),
            $row->ip_address,
            $row->created_at ? date('d/m/Y H:i', strtotime($row->created_at)) : '',
        ];
    }

    private function mapFormSource(?string $value): string
    {
        if ($value === 'landing_curso_v2') {
            return 'META CURSO';
        }
        if ($value === 'probusiness_pe') {
            return 'WEB';
        }

        return $value ?: '';
    }
}

