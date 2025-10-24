<?php

namespace App\Http\Controllers\Commons\Google;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Revolution\Google\Sheets\Facades\Sheets;
use Illuminate\Support\Facades\Log;
use Google\Service\Sheets as GoogleSheets;
use Google\Client as GoogleClient;
use App\Traits\GoogleSheetsHelper;
class SheetController extends Controller
{
    use GoogleSheetsHelper;
    public function index()
    {
        //
    }
    // retrieve the data from google sheet
    public function getGoogleSheetValues()
    {
        try {
            // Primero probemos con un rango más simple
            $values = Sheets::spreadsheet(config('google.post_spreadsheet_id'))
                ->sheet(config('google.post_sheet_id'))
                ->range('A1:Z1000')
                ->all();
            
            Log::info('Values: ' . json_encode($values));
            return response()->json([
                'success' => true,
                'data' => $values,
                'count' => count($values)
            ]);
        } catch (\Exception $e) {
            Log::error('Google Sheets Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Test simple connection
    public function testConnection()
    {
        try {
            // Probar solo obtener información básica del spreadsheet
            $spreadsheet = Sheets::spreadsheet(config('google.post_spreadsheet_id'));
            Log::info('Connection successful');
            return response()->json([
                'success' => true,
                'message' => 'Connection to Google Sheets successful'
            ]);
        } catch (\Exception $e) {
            Log::error('Connection Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Obtener rangos mergeados usando el trait
    public function getMergedRanges(Request $request)
    {
        try {
            $range = $request->get('range'); // Opcional: filtrar por rango específico
            $mergedRanges = $this->getMergedRangesInRange($range);
            
            return response()->json([
                'success' => true,
                'data' => $mergedRanges,
                'count' => count($mergedRanges)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Merged Ranges Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Obtener rangos mergeados de una columna específica
    public function getMergedRangesInColumn(Request $request)
    {
        try {
            $column = $request->get('column');
            $range = $request->get('range'); // Opcional: limitar búsqueda
            
            if (!$column) {
                return response()->json([
                    'success' => false,
                    'error' => 'Se requiere el parámetro column'
                ], 400);
            }
            
            $mergedRanges = $this->getMergedRangesInColumn($column, $range);
            
            return response()->json([
                'success' => true,
                'data' => $mergedRanges,
                'count' => count($mergedRanges),
                'column' => $column
            ]);
            
        } catch (\Exception $e) {
            Log::error('Merged Ranges Column Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Insertar valor en celda específica
    public function insertValue(Request $request)
    {
        try {
            $cell = $request->get('cell');
            $value = $request->get('value');
            
            if (!$cell || $value === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'Se requieren los parámetros cell y value'
                ], 400);
            }
            
            $this->insertValueInCell($cell, $value);
            
            return response()->json([
                'success' => true,
                'message' => "Valor '{$value}' insertado en celda {$cell}"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Insert Value Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Mergear celdas
    public function mergeCells(Request $request)
    {
        try {
            $startCell = $request->get('start_cell');
            $endCell = $request->get('end_cell');
            
            if (!$startCell || !$endCell) {
                return response()->json([
                    'success' => false,
                    'error' => 'Se requieren los parámetros start_cell y end_cell'
                ], 400);
            }
            
            $this->mergeCells($startCell, $endCell);
            
            return response()->json([
                'success' => true,
                'message' => "Celdas mergeadas desde {$startCell} hasta {$endCell}"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Merge Cells Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Obtener valores de un rango
    public function getRangeValues(Request $request)
    {
        try {
            $range = $request->get('range', 'A1:Z1000');
            $values = $this->getRangeValues($range);
            
            return response()->json([
                'success' => true,
                'data' => $values,
                'count' => count($values)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get Range Values Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // append new row to google sheet
    public function appendValuesToGoggleSheet()
    {
        $append = [
            'title' => 'Test Title',
            'description' => 'This is dummy title'
        ];
        $appendSheet =        Sheets::spreadsheet(config('google.post_spreadsheet_id'))
            ->sheet(config('google.post_sheet_id'))
            ->append([$append]);
    }
}
