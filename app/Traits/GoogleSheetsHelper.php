<?php

namespace App\Traits;

use Google\Service\Sheets as GoogleSheets;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;

trait GoogleSheetsHelper
{
    protected $googleClient;
    protected $googleService;
    protected $spreadsheetId;
    protected $sheetName;

    /**
     * Inicializar el cliente de Google Sheets
     */
    protected function initializeGoogleSheets($spreadsheetId = null, $sheetName = null)
    {
        try {
            $this->googleClient = new GoogleClient();
            $this->googleClient->setAuthConfig(config('google.service.file'));
            $this->googleClient->addScope([
                GoogleSheets::SPREADSHEETS,
                GoogleSheets::DRIVE
            ]);
            
            $this->googleService = new GoogleSheets($this->googleClient);
            $this->spreadsheetId = $spreadsheetId ?? config('google.post_spreadsheet_id');
            $this->sheetName = $sheetName ?? config('google.post_sheet_id');
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error inicializando Google Sheets: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los rangos mergeados en un rango específico de columnas
     * 
     * @param string $range Rango en notación A1 (ej: "A1:Z100")
     * @param string $column Columna específica a filtrar (ej: "B")
     * @return array Array de rangos mergeados con información detallada
     */
    public function getMergedRangesInRange($range = null, $column = null)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            $spreadsheet = $this->googleService->spreadsheets->get($this->spreadsheetId);
            $mergedRanges = [];

            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() == $this->sheetName) {
                    $merges = $sheet->getMerges();
                    
                    if ($merges) {
                        foreach ($merges as $merge) {
                            $mergeInfo = [
                                'start_row' => $merge->getStartRowIndex(),
                                'end_row' => $merge->getEndRowIndex(),
                                'start_column' => $merge->getStartColumnIndex(),
                                'end_column' => $merge->getEndColumnIndex(),
                                'range' => $this->convertToA1Notation($merge),
                                'start_cell' => $this->convertToA1Notation($merge, 'start'),
                                'end_cell' => $this->convertToA1Notation($merge, 'end'),
                                'row_count' => $merge->getEndRowIndex() - $merge->getStartRowIndex(),
                                'column_count' => $merge->getEndColumnIndex() - $merge->getStartColumnIndex()
                            ];

                            // Filtrar por columna específica si se especifica
                            if ($column) {
                                $columnIndex = $this->letterToColumnIndex($column);
                                if ($mergeInfo['start_column'] <= $columnIndex && $mergeInfo['end_column'] > $columnIndex) {
                                    $mergedRanges[] = $mergeInfo;
                                }
                            } else {
                                // Si se especifica un rango, filtrar solo los que están dentro
                                if ($range) {
                                    if ($this->isMergeInRange($mergeInfo, $range)) {
                                        $mergedRanges[] = $mergeInfo;
                                    }
                                } else {
                                    $mergedRanges[] = $mergeInfo;
                                }
                            }
                        }
                    }
                    break;
                }
            }

            Log::info('Rangos mergeados encontrados: ' . count($mergedRanges));
            return $mergedRanges;

        } catch (\Exception $e) {
            Log::error('Error obteniendo rangos mergeados: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener todos los rangos mergeados de una columna específica
     * 
     * @param string $column Columna específica (ej: "B")
     * @param string $range Rango opcional para limitar la búsqueda (ej: "B1:B1000")
     * @return array Array de rangos mergeados de la columna especificada
     */
    public function getMergedRangesInColumn($column, $range = null)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            $spreadsheet = $this->googleService->spreadsheets->get($this->spreadsheetId);
            $mergedRanges = [];
            $columnIndex = $this->letterToColumnIndex($column);

            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() == $this->sheetName) {
                    $merges = $sheet->getMerges();
                    
                    if ($merges) {
                        foreach ($merges as $merge) {
                            // Verificar si el merge incluye la columna especificada
                            if ($merge->getStartColumnIndex() <= $columnIndex && $merge->getEndColumnIndex() > $columnIndex) {
                                $mergeInfo = [
                                    'start_row' => $merge->getStartRowIndex(),
                                    'end_row' => $merge->getEndRowIndex(),
                                    'start_column' => $merge->getStartColumnIndex(),
                                    'end_column' => $merge->getEndColumnIndex(),
                                    'range' => $this->convertToA1Notation($merge),
                                    'start_cell' => $this->convertToA1Notation($merge, 'start'),
                                    'end_cell' => $this->convertToA1Notation($merge, 'end'),
                                    'row_count' => $merge->getEndRowIndex() - $merge->getStartRowIndex(),
                                    'column_count' => $merge->getEndColumnIndex() - $merge->getStartColumnIndex(),
                                    'column' => $column
                                ];

                                // Si se especifica un rango, verificar si está dentro
                                if ($range) {
                                    if ($this->isMergeInRange($mergeInfo, $range)) {
                                        $mergedRanges[] = $mergeInfo;
                                    }
                                } else {
                                    $mergedRanges[] = $mergeInfo;
                                }
                            }
                        }
                    }
                    break;
                }
            }

            Log::info("Rangos mergeados encontrados en columna {$column}: " . count($mergedRanges));
            return $mergedRanges;

        } catch (\Exception $e) {
            Log::error("Error obteniendo rangos mergeados de columna {$column}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Insertar un valor en una celda específica
     * 
     * @param string $cell Celda en notación A1 (ej: "A1")
     * @param mixed $value Valor a insertar
     * @return bool True si fue exitoso
     */
    public function insertValueInCell($cell, $value)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            $range = $this->sheetName . '!' . $cell;
            
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => [[$value]]
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $this->googleService->spreadsheets_values->update(
                $this->spreadsheetId,
                $range,
                $body,
                $params
            );

            Log::info("Valor insertado en {$cell}: {$value}");
            return true;

        } catch (\Exception $e) {
            Log::error("Error insertando valor en {$cell}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mergear celdas desde una celda inicial hasta una celda final
     * 
     * @param string $startCell Celda inicial (ej: "A1")
     * @param string $endCell Celda final (ej: "C3")
     * @return bool True si fue exitoso
     */
    public function mergeCells($startCell, $endCell)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            // Convertir notación A1 a índices
            $startCoords = $this->a1ToCoordinates($startCell);
            $endCoords = $this->a1ToCoordinates($endCell);

            // Crear el rango de merge
            $mergeRange = new \Google\Service\Sheets\GridRange([
                'sheetId' => $this->getSheetId(),
                'startRowIndex' => $startCoords['row'],
                'endRowIndex' => $endCoords['row'] + 1,
                'startColumnIndex' => $startCoords['column'],
                'endColumnIndex' => $endCoords['column'] + 1
            ]);

            $request = new \Google\Service\Sheets\Request([
                'mergeCells' => new \Google\Service\Sheets\MergeCellsRequest([
                    'range' => $mergeRange,
                    'mergeType' => 'MERGE_ALL'
                ])
            ]);

            $batchRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [$request]
            ]);

            $result = $this->googleService->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);

            Log::info("Celdas mergeadas desde {$startCell} hasta {$endCell}");
            return true;

        } catch (\Exception $e) {
            Log::error("Error mergeando celdas {$startCell}-{$endCell}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener valores de un rango específico
     * 
     * @param string $range Rango en notación A1 (ej: "A1:C10")
     * @return array Array de valores
     */
    public function getRangeValues($range)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            $fullRange = $this->sheetName . '!' . $range;
            $response = $this->googleService->spreadsheets_values->get($this->spreadsheetId, $fullRange);
            
            return $response->getValues() ?? [];

        } catch (\Exception $e) {
            Log::error("Error obteniendo valores del rango {$range}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Insertar múltiples valores en un rango
     * 
     * @param string $range Rango en notación A1 (ej: "A1:C3")
     * @param array $values Array bidimensional de valores
     * @return bool True si fue exitoso
     */
    public function insertRangeValues($range, $values)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            $fullRange = $this->sheetName . '!' . $range;
            
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $this->googleService->spreadsheets_values->update(
                $this->spreadsheetId,
                $fullRange,
                $body,
                $params
            );

            Log::info("Valores insertados en rango {$range}");
            return true;

        } catch (\Exception $e) {
            Log::error("Error insertando valores en rango {$range}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convertir índices de Google Sheets a notación A1
     */
    private function convertToA1Notation($merge, $type = 'full')
    {
        $startRow = $merge->getStartRowIndex() + 1; // Google usa índice base 0
        $endRow = $merge->getEndRowIndex();
        $startCol = $this->columnIndexToLetter($merge->getStartColumnIndex());
        $endCol = $this->columnIndexToLetter($merge->getEndColumnIndex() - 1);

        if ($type === 'start') {
            return $startCol . $startRow;
        } elseif ($type === 'end') {
            return $endCol . $endRow;
        }

        if ($startRow == $endRow && $startCol == $endCol) {
            return $startCol . $startRow;
        }

        return $startCol . $startRow . ':' . $endCol . $endRow;
    }

    /**
     * Convertir índice de columna a letra
     */
    private function columnIndexToLetter($index)
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intval($index / 26) - 1;
        }
        return $letter;
    }

    /**
     * Convertir notación A1 a coordenadas
     */
    private function a1ToCoordinates($a1)
    {
        preg_match('/([A-Z]+)(\d+)/', $a1, $matches);
        $column = $this->letterToColumnIndex($matches[1]);
        $row = intval($matches[2]) - 1; // Convertir a índice base 0
        
        return [
            'column' => $column,
            'row' => $row
        ];
    }

    /**
     * Convertir letra de columna a índice
     */
    private function letterToColumnIndex($letter)
    {
        $index = 0;
        $length = strlen($letter);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - 64);
        }
        
        return $index - 1; // Convertir a índice base 0
    }

    /**
     * Verificar si un merge está dentro de un rango específico
     */
    private function isMergeInRange($mergeInfo, $range)
    {
        // Implementar lógica para verificar si el merge está dentro del rango
        // Por simplicidad, retornamos true por ahora
        return true;
    }

    /**
     * Obtener el ID de la hoja actual
     */
    private function getSheetId()
    {
        $spreadsheet = $this->googleService->spreadsheets->get($this->spreadsheetId);
        
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() == $this->sheetName) {
                return $sheet->getProperties()->getSheetId();
            }
        }
        
        throw new \Exception("No se encontró la hoja: {$this->sheetName}");
    }
}
