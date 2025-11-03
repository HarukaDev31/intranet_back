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

    /**
     * Crear una nueva hoja en el spreadsheet
     * 
     * @param string $sheetName Nombre de la nueva hoja
     * @param string $spreadsheetId ID del spreadsheet (opcional, usa el configurado por defecto si no se especifica)
     * @return int|false ID de la hoja creada o false si falla
     */
    public function createSheet($sheetName, $spreadsheetId = null)
    {
        try {
            // Inicializar con el spreadsheetId especificado o usar el configurado
            $targetSpreadsheetId = $spreadsheetId ?? config('google.post_spreadsheet_id');
            
            if (!$this->initializeGoogleSheets($targetSpreadsheetId)) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            // Verificar si la hoja ya existe
            $spreadsheet = $this->googleService->spreadsheets->get($targetSpreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() == $sheetName) {
                    Log::info("La hoja '{$sheetName}' ya existe en el spreadsheet");
                    return $sheet->getProperties()->getSheetId();
                }
            }

            // Crear la nueva hoja
            $addSheetRequest = new \Google\Service\Sheets\AddSheetRequest([
                'properties' => [
                    'title' => $sheetName
                ]
            ]);

            $request = new \Google\Service\Sheets\Request([
                'addSheet' => $addSheetRequest
            ]);

            $batchRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [$request]
            ]);

            $result = $this->googleService->spreadsheets->batchUpdate($targetSpreadsheetId, $batchRequest);
            
            $newSheetId = $result->getReplies()[0]->getAddSheet()->getProperties()->getSheetId();
            
            Log::info("Hoja '{$sheetName}' creada exitosamente con ID: {$newSheetId}");
            return $newSheetId;

        } catch (\Exception $e) {
            Log::error("Error creando hoja '{$sheetName}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Poblar hoja con estructura de clientes y proveedores
     * 
     * @param string $sheetName Nombre de la hoja
     * @param string $spreadsheetId ID del spreadsheet
     * @param array $cotizaciones Array de cotizaciones con proveedores
     * @return bool True si fue exitoso
     */
    public function populateConsolidadoSheet($sheetName, $spreadsheetId, $cotizaciones, $numeroCarga = '')
    {
        try {
            if (!$this->initializeGoogleSheets($spreadsheetId, $sheetName)) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            // Obtener el ID de la hoja
            $sheetId = $this->getSheetIdByName($sheetName, $spreadsheetId);
            if (!$sheetId) {
                throw new \Exception("No se encontró la hoja: {$sheetName}");
            }

            // Fila 1: CONSOLIDADO en B1:C1 (merged), número de carga en D1
            $row1 = ['', 'CONSOLIDADO', '', $numeroCarga, '', ''];
            // Fila 2: vacía
            $row2 = ['', '', '', '', '', ''];
            // Fila 3: Headers - CLIENTE en columna B
            $row3 = ['', 'CLIENTE', 'PROVEEDOR', 'INVOICE', 'PL', 'EXCEL CONF.'];

            // Preparar datos desde fila 4
            $rows = [];
            $clienteIndex = 1; // Contador de clientes (índice único por cliente)
            $clientRanges = []; // Para guardar información de cada cliente para calcular merges después

            foreach ($cotizaciones as $cotizacion) {
                $proveedores = $cotizacion['proveedores'] ?? [];
                $nombreCliente = trim($cotizacion['nombre'] ?? '');
                $cotizacionId = $cotizacion['id'] ?? 'N/A';
                $numProveedores = count($proveedores);

                if ($numProveedores == 0) {
                    Log::warning("Cotización {$cotizacionId} ({$nombreCliente}) no tiene proveedores, saltando");
                    continue; // Saltar si no tiene proveedores
                }

                // Log para debugging
                $codes = array_column($proveedores, 'code_supplier');
                Log::info("Procesando cotización {$cotizacionId} ({$nombreCliente}) con índice {$clienteIndex} y {$numProveedores} proveedores", [
                    'codes' => $codes
                ]);

                // Guardar el índice donde empiezan las filas de este cliente (en el array $rows)
                $startRowIndex = count($rows); // Índice en el array $rows (0-indexed)
                
                // Crear una fila por cada proveedor
                // IMPORTANTE: Poner el índice y nombre en TODAS las filas del mismo cliente
                // para evitar que se pierdan datos cuando se aplica el merge
                for ($i = 0; $i < $numProveedores; $i++) {
                    $proveedor = $proveedores[$i];
                    $codeSupplier = $proveedor['code_supplier'] ?? '';
                    $proveedorId = $proveedor['id'] ?? 'N/A';

                    // Validar que el proveedor tenga code_supplier
                    if (empty($codeSupplier)) {
                        Log::warning("Proveedor {$proveedorId} de cotización {$cotizacionId} no tiene code_supplier");
                    }

                    // Poner el índice del cliente y nombre en TODAS las filas del mismo cliente
                    // Google Sheets al hacer merge mostrará el valor de la primera celda
                    $row = [
                        $clienteIndex, // Índice del cliente (en todas las filas del mismo cliente)
                        $nombreCliente, // Nombre del cliente (en todas las filas del mismo cliente)
                        $codeSupplier, // code_supplier del proveedor
                        'PENDIENTE', // INVOICE default
                        'PENDIENTE', // PL default
                        'PENDIENTE'  // EXCEL CONF. default
                    ];

                    $rows[] = $row;
                    
                    // Log detallado para cada proveedor
                    Log::debug("Agregando fila para cliente índice {$clienteIndex} ({$nombreCliente}), proveedor: {$codeSupplier}", [
                        'cotizacion_id' => $cotizacionId,
                        'proveedor_id' => $proveedorId,
                        'fila_en_array' => count($rows)
                    ]);
                }

                // Guardar información del cliente para calcular merges después
                // Esto nos permite calcular los rangos correctos después de que todos los datos estén en $rows
                $clientRanges[] = [
                    'startRowIndex' => $startRowIndex, // Índice en el array $rows donde empieza este cliente
                    'numProveedores' => $numProveedores,
                    'clienteIndex' => $clienteIndex,
                    'nombreCliente' => $nombreCliente
                ];
                
                $clienteIndex++; // Incrementar índice del cliente
            }

            // Ahora calcular los rangos de merge DESPUÉS de tener todos los datos
            // Las filas en Google Sheets empiezan desde la fila 4 (índice 3 en 0-indexed)
            // porque tenemos 3 filas antes: row1 (fila 1), row2 (fila 2), row3 (fila 3)
            $mergeRanges = [];
            foreach ($clientRanges as $clientRange) {
                // Convertir el índice del array $rows a índice de fila en Google Sheets (0-indexed)
                // startRowIndex es el índice en $rows, pero en Google Sheets necesitamos sumar 3 (las 3 filas iniciales)
                $googleSheetStartRow = 3 + $clientRange['startRowIndex']; // Fila 4 = índice 3, etc.
                $googleSheetEndRow = $googleSheetStartRow + $clientRange['numProveedores']; // Exclusivo
                
                // Merge columna A (índice del cliente)
                $mergeRanges[] = [
                    'startRow' => $googleSheetStartRow,
                    'endRow' => $googleSheetEndRow,
                    'column' => 0, // Columna A (0-indexed)
                    'cliente' => $clientRange['nombreCliente'],
                    'indice' => $clientRange['clienteIndex']
                ];
                
                // Merge columna B (nombre del cliente)
                $mergeRanges[] = [
                    'startRow' => $googleSheetStartRow,
                    'endRow' => $googleSheetEndRow,
                    'column' => 1, // Columna B (0-indexed)
                    'cliente' => $clientRange['nombreCliente'],
                    'indice' => $clientRange['clienteIndex']
                ];
                
                Log::debug("Merge configurado para cliente {$clientRange['clienteIndex']} ({$clientRange['nombreCliente']}): filas Google Sheets {$googleSheetStartRow}-{$googleSheetEndRow} (0-indexed, endRow exclusivo)", [
                    'fila_inicio' => $googleSheetStartRow + 1, // 1-indexed para log
                    'fila_fin' => $googleSheetEndRow, // 1-indexed para log
                    'num_proveedores' => $clientRange['numProveedores']
                ]);
            }

            // Combinar todas las filas: row1, row2, row3, y datos
            $allData = [$row1, $row2, $row3];
            $allData = array_merge($allData, $rows);

            // Insertar todos los datos empezando desde A1
            $range = $sheetName . '!A1';
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => $allData
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $this->googleService->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $body,
                $params
            );

            // Aplicar formato y validaciones
            $totalRows = count($rows) + 3; // 3 filas iniciales + datos
            $totalColumns = 6; // A=CLIENTE, B=nombre mergeado, C=PROVEEDOR, D=INVOICE, E=PL, F=EXCEL CONF.
            $this->applyConsolidadoSheetFormat($sheetId, $spreadsheetId, $totalRows, $totalColumns, $mergeRanges);

            Log::info("Hoja '{$sheetName}' poblada exitosamente con " . count($cotizaciones) . " clientes y " . count($rows) . " filas de datos");
            return true;

        } catch (\Exception $e) {
            Log::error("Error poblando hoja '{$sheetName}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Aplicar formato y validaciones a la hoja consolidado
     * 
     * @param int $sheetId ID de la hoja
     * @param string $spreadsheetId ID del spreadsheet
     * @param int $totalRows Número total de filas con datos
     * @param int $totalColumns Número total de columnas
     * @param array $mergeRanges Array con rangos para merge vertical de columna B
     */
    private function applyConsolidadoSheetFormat($sheetId, $spreadsheetId, $totalRows, $totalColumns, $mergeRanges = [])
    {
        try {
            $requests = [];

            // 1. Mergear "CONSOLIDADO" en B1:C1
            $requests[] = new \Google\Service\Sheets\Request([
                'mergeCells' => new \Google\Service\Sheets\MergeCellsRequest([
                    'range' => new \Google\Service\Sheets\GridRange([
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 1, // Columna B
                        'endColumnIndex' => 3    // Hasta columna C (0-indexed: B=1, C=2)
                    ]),
                    'mergeType' => 'MERGE_ALL'
                ])
            ]);

            // 2. Mergear verticalmente la columna B (nombre del cliente) para cada cliente con múltiples proveedores
            foreach ($mergeRanges as $mergeRange) {
                $requests[] = new \Google\Service\Sheets\Request([
                    'mergeCells' => new \Google\Service\Sheets\MergeCellsRequest([
                        'range' => new \Google\Service\Sheets\GridRange([
                            'sheetId' => $sheetId,
                            'startRowIndex' => $mergeRange['startRow'],
                            'endRowIndex' => $mergeRange['endRow'], // Ya es exclusivo
                            'startColumnIndex' => $mergeRange['column'],
                            'endColumnIndex' => $mergeRange['column'] + 1
                        ]),
                        'mergeType' => 'MERGE_ALL'
                    ])
                ]);
            }

            // 3. Crear validaciones con dropdown para columnas D, E, F (INVOICE, PL, EXCEL CONF.)
            // Columnas: D=3, E=4, F=5 (0-indexed)
            $statusColumns = [3, 4, 5]; // D, E, F
            $statusOptions = ['PENDIENTE', 'RECIBIDO', 'OBSERVADO', 'REVISADO'];
            
            foreach ($statusColumns as $colIndex) {
                $requests[] = new \Google\Service\Sheets\Request([
                    'setDataValidation' => new \Google\Service\Sheets\SetDataValidationRequest([
                        'range' => new \Google\Service\Sheets\GridRange([
                            'sheetId' => $sheetId,
                            'startRowIndex' => 3, // Empezar desde la fila 4 (después de headers en fila 3)
                            'endRowIndex' => $totalRows,
                            'startColumnIndex' => $colIndex,
                            'endColumnIndex' => $colIndex + 1
                        ]),
                        'rule' => new \Google\Service\Sheets\DataValidationRule([
                            'condition' => new \Google\Service\Sheets\BooleanCondition([
                                'type' => 'ONE_OF_LIST',
                                'values' => array_map(function($option) {
                                    return new \Google\Service\Sheets\ConditionValue([
                                        'userEnteredValue' => $option
                                    ]);
                                }, $statusOptions)
                            ]),
                            'showCustomUi' => true,
                            'strict' => true
                        ])
                    ])
                ]);
            }

            // 4. Formatear encabezados en fila 3 (negrita, centrado, fondo gris)
            $requests[] = new \Google\Service\Sheets\Request([
                'repeatCell' => new \Google\Service\Sheets\RepeatCellRequest([
                    'range' => new \Google\Service\Sheets\GridRange([
                        'sheetId' => $sheetId,
                        'startRowIndex' => 2, // Fila 3 (0-indexed)
                        'endRowIndex' => 3,   // Hasta fila 3
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $totalColumns
                    ]),
                    'cell' => new \Google\Service\Sheets\CellData([
                        'userEnteredFormat' => new \Google\Service\Sheets\CellFormat([
                            'backgroundColor' => new \Google\Service\Sheets\Color([
                                'red' => 0.9,
                                'green' => 0.9,
                                'blue' => 0.9
                            ]),
                            'textFormat' => new \Google\Service\Sheets\TextFormat([
                                'bold' => true
                            ]),
                            'horizontalAlignment' => 'CENTER'
                        ])
                    ]),
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment)'
                ])
            ]);

            // 5. Formatear fila 1 (CONSOLIDADO y número de carga)
            $requests[] = new \Google\Service\Sheets\Request([
                'repeatCell' => new \Google\Service\Sheets\RepeatCellRequest([
                    'range' => new \Google\Service\Sheets\GridRange([
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0, // Fila 1
                        'endRowIndex' => 1,   // Hasta fila 1
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $totalColumns
                    ]),
                    'cell' => new \Google\Service\Sheets\CellData([
                        'userEnteredFormat' => new \Google\Service\Sheets\CellFormat([
                            'textFormat' => new \Google\Service\Sheets\TextFormat([
                                'bold' => true
                            ]),
                            'horizontalAlignment' => 'CENTER'
                        ])
                    ]),
                    'fields' => 'userEnteredFormat(textFormat,horizontalAlignment)'
                ])
            ]);

            // 6. Aplicar bordes desde B1 hasta D1
            $requests[] = new \Google\Service\Sheets\Request([
                'updateBorders' => new \Google\Service\Sheets\UpdateBordersRequest([
                    'range' => new \Google\Service\Sheets\GridRange([
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0, // Fila 1
                        'endRowIndex' => 1,    // Hasta fila 1
                        'startColumnIndex' => 1, // Columna B
                        'endColumnIndex' => 4     // Hasta columna D (0-indexed: B=1, C=2, D=3)
                    ]),
                    'top' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'bottom' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'left' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'right' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ])
                ])
            ]);

            // 7. Aumentar ancho de la columna B (nombres de clientes)
            $requests[] = new \Google\Service\Sheets\Request([
                'updateDimensionProperties' => new \Google\Service\Sheets\UpdateDimensionPropertiesRequest([
                    'range' => new \Google\Service\Sheets\DimensionRange([
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 1, // Columna B (0-indexed)
                        'endIndex' => 2    // Hasta columna B
                    ]),
                    'properties' => new \Google\Service\Sheets\DimensionProperties([
                        'pixelSize' => 300 // Ancho de 300 píxeles
                    ]),
                    'fields' => 'pixelSize'
                ])
            ]);

            // 8. Aplicar bordes a todas las celdas con datos (desde fila 3)
            $requests[] = new \Google\Service\Sheets\Request([
                'updateBorders' => new \Google\Service\Sheets\UpdateBordersRequest([
                    'range' => new \Google\Service\Sheets\GridRange([
                        'sheetId' => $sheetId,
                        'startRowIndex' => 2, // Desde fila 3 (headers)
                        'endRowIndex' => $totalRows,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $totalColumns
                    ]),
                    'top' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'bottom' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'left' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'right' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'innerHorizontal' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ]),
                    'innerVertical' => new \Google\Service\Sheets\Border([
                        'style' => 'SOLID',
                        'width' => 1
                    ])
                ])
            ]);

            // 9. Agregar formato condicional para colorear los estados en columnas D, E, F
            // Columnas: D=3, E=4, F=5 (0-indexed)
            $statusColumns = [3, 4, 5]; // D, E, F
            $statusColors = [
                'PENDIENTE' => ['red' => 0.96, 'green' => 0.26, 'blue' => 0.21],      // Rojo
                'RECIBIDO' => ['red' => 0.26, 'green' => 0.52, 'blue' => 0.96],      // Azul
                'REVISADO' => ['red' => 0.19, 'green' => 0.71, 'blue' => 0.31],     // Verde
                'OBSERVADO' => ['red' => 0.62, 'green' => 0.62, 'blue' => 0.62]      // Gris
            ];

            foreach ($statusColumns as $colIndex) {
                foreach ($statusColors as $status => $color) {
                    $requests[] = new \Google\Service\Sheets\Request([
                        'addConditionalFormatRule' => new \Google\Service\Sheets\AddConditionalFormatRuleRequest([
                            'rule' => new \Google\Service\Sheets\ConditionalFormatRule([
                                'ranges' => [
                                    new \Google\Service\Sheets\GridRange([
                                        'sheetId' => $sheetId,
                                        'startRowIndex' => 3, // Desde fila 4 (después de headers)
                                        'endRowIndex' => $totalRows,
                                        'startColumnIndex' => $colIndex,
                                        'endColumnIndex' => $colIndex + 1
                                    ])
                                ],
                                'booleanRule' => new \Google\Service\Sheets\BooleanRule([
                                    'condition' => new \Google\Service\Sheets\BooleanCondition([
                                        'type' => 'TEXT_EQ',
                                        'values' => [
                                            new \Google\Service\Sheets\ConditionValue([
                                                'userEnteredValue' => $status
                                            ])
                                        ]
                                    ]),
                                    'format' => new \Google\Service\Sheets\CellFormat([
                                        'backgroundColor' => new \Google\Service\Sheets\Color($color)
                                    ])
                                ])
                            ])
                            // No especificamos 'index' para que se agregue al final de las reglas existentes
                        ])
                    ]);
                }
            }

            // Ejecutar todas las solicitudes
            if (!empty($requests)) {
                $batchRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                    'requests' => $requests
                ]);

                $this->googleService->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
            }

            Log::info("Formato aplicado a hoja con {$totalRows} filas y " . count($mergeRanges) . " rangos mergeados");

        } catch (\Exception $e) {
            Log::error("Error aplicando formato: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener el ID de una hoja por su nombre
     * 
     * @param string $sheetName Nombre de la hoja
     * @param string $spreadsheetId ID del spreadsheet
     * @return int|false ID de la hoja o false si no se encuentra
     */
    private function getSheetIdByName($sheetName, $spreadsheetId)
    {
        try {
            $spreadsheet = $this->googleService->spreadsheets->get($spreadsheetId);
            
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() == $sheetName) {
                    return $sheet->getProperties()->getSheetId();
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("Error obteniendo ID de hoja '{$sheetName}': " . $e->getMessage());
            return false;
        }
    }
}
