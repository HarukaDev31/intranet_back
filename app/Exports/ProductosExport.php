<?php

namespace App\Exports;

use App\Models\BaseDatos\ProductoImportadoExcel;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Facades\DB;
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
    protected $rowIndex = 0;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = ProductoImportadoExcel::query()
            ->leftJoin(
                'carga_consolidada_contenedor',
                'productos_importados_excel.idContenedor',
                '=',
                'carga_consolidada_contenedor.id'
            )
            ->select(
                'productos_importados_excel.nombre_comercial',
                'productos_importados_excel.rubro',
                'productos_importados_excel.tipo_producto',
                'productos_importados_excel.unidad_comercial',
                'productos_importados_excel.subpartida',
                DB::raw("CONCAT(TRIM(carga_consolidada_contenedor.carga), IF(carga_consolidada_contenedor.f_cierre IS NOT NULL, CONCAT('-', YEAR(carga_consolidada_contenedor.f_cierre)), '')) as carga_contenedor"),
                DB::raw('YEAR(carga_consolidada_contenedor.f_cierre) as anio')
            );

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('productos_importados_excel.nombre_comercial', 'like', '%' . $search . '%')
                    ->orWhere('productos_importados_excel.subpartida', 'like', '%' . $search . '%');
            });
        }

        if (!empty($this->filters['rubro']) && $this->filters['rubro'] !== 'todos') {
            $query->where('productos_importados_excel.rubro', $this->filters['rubro']);
        }

        if (!empty($this->filters['tipoProducto']) && $this->filters['tipoProducto'] !== 'todos') {
            $query->where('productos_importados_excel.tipo_producto', $this->filters['tipoProducto']);
        }

        if (!empty($this->filters['campana']) && $this->filters['campana'] !== 'todos') {
            $query->where('carga_consolidada_contenedor.id', $this->filters['campana'])
                ->where('carga_consolidada_contenedor.estado_documentacion', Contenedor::CONTEDOR_CERRADO);
        }

        return $query
            ->orderByRaw('CAST(carga_consolidada_contenedor.carga AS UNSIGNED) DESC')
            ->orderByRaw('YEAR(carga_consolidada_contenedor.f_cierre) DESC')
            ->get();
    }

    public function headings(): array
    {
        return [
            'N°',
            'Nombre comercial',
            'Rubro',
            'T. Producto',
            'Unidad Com.',
            'Subpartida',
            'Campaña',
            'Año',
        ];
    }

    public function map($producto): array
    {
        $this->rowIndex++;

        return [
            $this->rowIndex,
            $producto->nombre_comercial,
            $producto->rubro,
            $producto->tipo_producto,
            $producto->unidad_comercial,
            $producto->subpartida,
            $producto->carga_contenedor,
            $producto->anio,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA'],
                ],
            ],
        ];
    }
}
