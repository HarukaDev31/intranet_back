<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class ClientesEntregaExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Nombre de cliente',
            'Dni',
            'WhatsApp',
            'T. cliente',
            'T. entrega',
            'Nombre de la provincia',
            'Origen',
        ];
    }

    public function map($row): array
    {
        $typeForm = isset($row->type_form) ? (int) $row->type_form : null;
        $tEntrega = $typeForm === 0 ? 'Provincia' : ($typeForm === 1 ? 'Lima' : '');

        return [
            $row->nombre ?? '',
            $row->documento ?? '',
            $row->telefono ?? '',
            $row->name ?? '',
            $tEntrega,
            $row->nombre_provincia ?? '',
            $row->origen ?? '',
        ];
    }
}
