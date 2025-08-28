<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CotizacionInicialExport implements FromArray, WithMultipleSheets, WithStyles
{
    protected $data;
    protected $templatePath;
    
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->templatePath = public_path('assets/templates/PLANTILLA_COTIZACION_INICIAL.xlsx');
    }

    public function array(): array
    {
        return $this->data;
    }

    public function sheets(): array
    {
        return [
            new CotizacionInicialExport($this->data),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']], 'font' => ['color' => ['rgb' => 'FFFFFF']]],
            3 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]],
            12 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function(BeforeExport $event) {
                // Cargar el template existente
                $spreadsheet = IOFactory::load($this->templatePath);
                
                // Reemplazar el spreadsheet del evento con nuestro template
                $event->writer->setSpreadsheet($spreadsheet);
                
                // Obtener la hoja de cálculos
                $sheetCalculos = $spreadsheet->getSheet(1);
                
                // Definir las filas
                $rowNProveedor = 4;
                $rowNCaja = 5;
                $rowPeso = 6;
                $rowMedida = 7;
                $rowVolProveedor = 8;
                $rowHeaderNProveedor = 10;
                $rowProducto = 11;
                $rowValorUnitario = 15;
                $rowValoracion = 16;
                $rowCantidad = 17;
                $rowValorFob = 18;
                
                // Procesar datos
                $columnIndex = 3;
                $indexProveedor = 1;
                
                foreach ($this->data['proveedores'] as $proveedor) {
                    foreach ($proveedor['productos'] as $productoIndex => $producto) {
                        $productoColumn = $columnIndex + $productoIndex;
                        
                        // Escribir datos del proveedor
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowNProveedor)->setValue($indexProveedor);
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowNCaja)->setValue($proveedor['qtyCaja']);
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowPeso)->setValue($proveedor['peso']);
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowVolProveedor)->setValue($proveedor['cbm']);
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowHeaderNProveedor)->setValue($indexProveedor);
                        
                        // Escribir datos del producto
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowProducto)->setValue($producto['nombre']);
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowValorUnitario)->setValue($producto['precio']);
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowCantidad)->setValue($producto['cantidad']);
                        
                        $valoracion = isset($producto['valoracion']) ? $producto['valoracion'] : 1;
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowValoracion)->setValue($valoracion);
                        
                        $valorFob = $producto['precio'] * $producto['cantidad'];
                        $sheetCalculos->getCellByColumnAndRow($productoColumn, $rowValorFob)->setValue($valorFob);
                    }
                    
                    $columnIndex += count($proveedor['productos']);
                    $indexProveedor++;
                }
            },
        ];
    }
}
