<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Console\Commands\ImportPagosExcel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ImportController extends Controller
{
    /**
     * Mostrar formulario de importación
     */
    public function showImportForm()
    {
        return view('import.form');
    }

    /**
     * Procesar archivo Excel subido
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            ]);

            $file = $request->file('excel_file');
            $fileName = 'import_' . time() . '_' . $file->getClientOriginalName();
            
            // Guardar archivo temporalmente
            $path = $file->storeAs('temp', $fileName);
            $fullPath = storage_path('app/' . $path);

            // Ejecutar comando de importación
            $output = $this->runImportCommand($fullPath);

            // Limpiar archivo temporal
            Storage::delete($path);

            return response()->json([
                'success' => true,
                'message' => 'Importación completada exitosamente',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en importación Excel: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error durante la importación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ejecutar comando de importación
     */
    private function runImportCommand($filePath)
    {
        $command = new ImportPagosExcel();
        $input = new ArrayInput(['file' => $filePath]);
        $output = new BufferedOutput();

        $command->run($input, $output);

        return $output->fetch();
    }

    /**
     * Obtener plantilla de Excel
     */
    public function downloadTemplate()
    {
        $templatePath = storage_path('app/templates/plantilla_importacion_pagos.xlsx');
        
        if (!file_exists($templatePath)) {
            $this->createTemplate($templatePath);
        }

        return response()->download($templatePath, 'plantilla_importacion_pagos.xlsx');
    }

    /**
     * Crear plantilla de Excel
     */
    private function createTemplate($path)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $headers = [
            'A1' => 'ID',
            'B1' => 'NOMBRE',
            'C1' => 'TELÉFONO',
            'D1' => 'TIPO',
            'E1' => 'FECHA',
            'F1' => 'CONTENEDOR',
            'G1' => 'DOCUMENTO',
            'H1' => 'NOMBRE COMPLETO'
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Estilo para encabezados
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:H1')->getFill()->getStartColor()->setRGB('CCCCCC');

        // Ejemplos de datos
        $examples = [
            ['1', 'JESUS QUESQUEN CONDORI', '981 466 498', 'CONSOLIDADO', '1/01/2024', 'CONSOLIDADO #1', '10452681418', 'JESUS ANTONIO QUESQUEN CONDORI'],
            ['2', 'MARIA GONZALEZ', '999 888 777', 'CURSO', '15/01/2024', '', '12345678', 'MARIA ELENA GONZALEZ LOPEZ'],
        ];

        $row = 3;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // Autoajustar columnas
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Validación de datos
        $this->addDataValidation($sheet);

        // Guardar plantilla
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * Agregar validación de datos a la plantilla
     */
    private function addDataValidation($sheet)
    {
        // Validación para tipo (CONSOLIDADO o CURSO)
        $validation = $sheet->getDataValidation('D3:D1000');
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setFormula1('"CONSOLIDADO,CURSO"');
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setPromptTitle('Seleccionar tipo');
        $validation->setPrompt('Debe seleccionar CONSOLIDADO o CURSO');
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Error de entrada');
        $validation->setErrorMessage('Debe seleccionar CONSOLIDADO o CURSO');
    }

    /**
     * Obtener estadísticas de importación
     */
    public function getImportStats()
    {
        try {
            $stats = [
                'consolidados' => \App\Models\CargaConsolidada\Cotizacion::count(),
                'cursos' => \App\Models\PedidoCurso::count(),
                'contenedores' => \App\Models\CargaConsolidada\Contenedor::count(),
                'entidades' => \App\Models\Entidad::count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }
} 