<?php

namespace App\Exports;

use App\Models\BaseDatos\ProductoImportadoExcel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductosExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = ProductoImportadoExcel::with('contenedor');

        // Aplicar filtros si están presentes
        if (isset($this->filters['tipoProducto']) && $this->filters['tipoProducto'] && $this->filters['tipoProducto'] !== '') {
            $query->where('tipo_producto', $this->filters['tipoProducto']);
        }
        
        if (isset($this->filters['campana']) && $this->filters['campana'] && $this->filters['campana'] !== '') {
            $query->whereHas('contenedor', function($q) {
                $q->where('carga', $this->filters['campana']);
            });
        }
        
        if (isset($this->filters['rubro']) && $this->filters['rubro'] && $this->filters['rubro'] !== '') {
            $query->where('rubro', $this->filters['rubro']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Código',
            'Descripción',
            'Rubro',
            'Tipo de Producto',
            'Campaña',
            'Precio',
            'Moneda',
            'Stock',
            'Fecha de Creación',
            'Fecha de Actualización'
        ];
    }

    public function map($producto): array
    {
        return [
            $producto->id,
            $producto->codigo,
            $producto->descripcion,
            $producto->rubro,
            $producto->tipo_producto,
            $producto->contenedor ? $producto->contenedor->carga : null,
            $producto->precio,
            $producto->moneda,
            $producto->stock,
            $producto->created_at ? $producto->created_at->format('d/m/Y H:i:s') : null,
            $producto->updated_at ? $producto->updated_at->format('d/m/Y H:i:s') : null,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA']
                ]
            ],
        ];
    }
} 