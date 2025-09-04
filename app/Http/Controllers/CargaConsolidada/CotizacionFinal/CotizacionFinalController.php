<?php

namespace App\Http\Controllers\CargaConsolidada\CotizacionFinal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Facades\DB;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CotizacionFinalController extends Controller
{
    use WhatsappTrait;
    private $table_cliente = 'entidad';
    private $table_usuario = 'usuario';
    private $table = "carga_consolidada_contenedor";
    private $STATUS_CONTACTED = "C";
    private $STATUS_RECIVED = "R";
    private $STATUS_NOT_SELECTED = "NS";
    private $STATUS_INSPECTION = "INSPECTION";
    private $STATUS_LOADED = "LOADED";
    private $STATUS_NO_LOADED = "NO LOADED";
    private $STATUS_ROTULADO = "ROTULADO";
    private $STATUS_DATOS_PROVEEDOR = "DATOS PROVEEDOR";
    private $STATUS_COBRANDO = "COBRANDO";
    private $STATUS_INSPECCIONADO = "INSPECCIONADO";
    private $STATUS_RESERVADO = "RESERVADO";
    private $STATUS_NO_RESERVADO = "NO RESERVADO";
    private $STATUS_EMBARCADO = "EMBARCADO";
    private $STATUS_NO_EMBARCADO = "NO EMBARCADO";
    private $table_pais = "pais";
    private $table_contenedor_steps = "contenedor_consolidado_order_steps";
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_crons = "contenedor_consolidado_cotizacion_crons";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor_documentacion_files = "contenedor_consolidado_documentacion_files";
    private $table_contenedor_documentacion_folders = "contenedor_consolidado_documentacion_folders";
    private $table_contenedor_tipo_cliente = "contenedor_consolidado_tipo_cliente";
    private $table_contenedor_cotizacion_documentacion = "contenedor_consolidado_cotizacion_documentacion";
    private $table_contenedor_almacen_documentacion = "contenedor_consolidado_almacen_documentacion";
    private $table_contenedor_almacen_inspection = "contenedor_consolidado_almacen_inspection";
    private $table_conteneodr_proveedor_estados_tracking = "contenedor_proveedor_estados_tracking";
    private $roleCotizador = "Cotizador";
    private $roleCoordinacion = "Coordinación";
    private $roleContenedorAlmacen = "ContenedorAlmacen";
    private $roleCatalogoChina = "CatalogoChina";
    private $rolesChina = ["CatalogoChina", "ContenedorAlmacen"];
    private $roleDocumentacion = "Documentacion";
    private $aNewContainer = "new-container";
    private $aNewConfirmado = "new-confirmado";
    private $aNewCotizacion = "new-cotizacion";
    private $cambioEstadoProveedor = "cambio-estado-proveedor";
    private $table_contenedor_cotizacion_final = "contenedor_consolidado_cotizacion_final";
    /**
     * Obtiene las cotizaciones finales de un contenedor específico
     */
    public function getContenedorCotizacionesFinales(Request $request, $idContenedor)
    {
        try {
            // Construir la consulta usando Eloquent con campos formateados directamente
            $query = Cotizacion::with('tipoCliente')
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    'contenedor_consolidado_cotizacion.id as id_cotizacion',
                    'contenedor_consolidado_tipo_cliente.name',
                    DB::raw('UPPER(contenedor_consolidado_cotizacion.nombre) as nombre_upper'),
                    DB::raw('UPPER(LEFT(TRIM(contenedor_consolidado_tipo_cliente.name), 1)) || LOWER(SUBSTRING(TRIM(contenedor_consolidado_tipo_cliente.name), 2)) as tipo_cliente_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.volumen_final, 2) as volumen_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.fob_final, 2) as fob_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.logistica_final, 2) as logistica_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.impuestos_final, 2) as impuestos_final_formateado'),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.tarifa_final, 2) as tarifa_final_formateado')
                ])
                ->join('contenedor_consolidado_tipo_cliente', 'contenedor_consolidado_cotizacion.id_tipo_cliente', '=', 'contenedor_consolidado_tipo_cliente.id')
                ->where('id_contenedor', $idContenedor)
                ->whereNotNull('estado_cliente')
                ->where('estado_cotizador', 'CONFIRMADO');

            // Aplicar filtros adicionales si se proporcionan
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('documento', 'LIKE', "%{$search}%")
                        ->orWhere('correo', 'LIKE', "%{$search}%");
                });
            }

            // Filtrar por estado de cotización final si se proporciona
            if ($request->has('estado_cotizacion_final') && !empty($request->estado_cotizacion_final)) {
                $query->where('estado_cotizacion_final', $request->estado_cotizacion_final);
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'fecha');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            // Transformar los datos para incluir las columnas específicas
            $transformedData = [];
            $index = 1;

            foreach ($data->items() as $row) {
                $subdata = [
                    'index' => $index,
                    'nombre' => $this->cleanUtf8String($row->nombre_upper ?? $row->nombre),
                    'documento' => $this->cleanUtf8String($row->documento),
                    'correo' => $this->cleanUtf8String($row->correo),
                    'telefono' => $this->cleanUtf8String($row->telefono),
                    'tipo_cliente' => $this->cleanUtf8String($row->name),
                    'volumen_final' => $row->volumen_final_formateado ?? $row->volumen_final,
                    'fob_final' => $row->fob_final_formateado ?? $row->fob_final,
                    'logistica_final' => $row->logistica_final_formateado ?? $row->logistica_final,
                    'impuestos_final' => $row->impuestos_final_formateado ?? $row->impuestos_final,
                    'tarifa_final' => $row->tarifa_final_formateado ?? $row->tarifa_final,
                    'estado_cotizacion_final' => $this->cleanUtf8String($row->estado_cotizacion_final),
                    'id_cotizacion' => $row->id_cotizacion
                ];

                $transformedData[] = $subdata;
                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem()
                ],

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones finales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las cotizaciones finales con documentación y pagos para un contenedor
     */
    public function getCotizacionFinalDocumentacionPagos(Request $request, $idContenedor)
    {
        try {
            // Construir la consulta usando Eloquent con campos formateados directamente
            $query = Cotizacion::with('tipoCliente')
                ->select([
                    'contenedor_consolidado_cotizacion.*',
                    'contenedor_consolidado_cotizacion.id as id_cotizacion',
                    'TC.name',
                    /**
                     * 'id_pago', cccp2.id,
                        'monto', cccp2.monto,
                        'concepto', ccp2.name,
                        'status', cccp2.status,
                        'payment_date', cccp2.payment_date,
                        'banco', cccp2.banco,
                        'voucher_url', cccp2.voucher_url
                     */
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id_pago', cccp.id,
                                'concepto', ccp.name,
                                'status', cccp.status,
                                'payment_date', cccp.payment_date,
                                'banco', cccp.banco,
                                'monto', cccp.monto,
                                'voucher_url', cccp.voucher_url
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS pagos"),
                    DB::raw("(
                        SELECT IFNULL(SUM(cccp.monto), 0) 
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS total_pagos"),
                    DB::raw("(
                        SELECT COUNT(*) 
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = contenedor_consolidado_cotizacion.id
                        AND (ccp.name = 'LOGISTICA' OR ccp.name = 'IMPUESTOS')
                    ) AS pagos_count"),
                    DB::raw('FORMAT(contenedor_consolidado_cotizacion.logistica_final + contenedor_consolidado_cotizacion.impuestos_final, 2) as total_logistica_impuestos')
                ])
                ->leftJoin('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'contenedor_consolidado_cotizacion.id_tipo_cliente')
                ->where('contenedor_consolidado_cotizacion.id_contenedor', $idContenedor)
                ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente');

            // Aplicar filtros adicionales si se proporcionan
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('contenedor_consolidado_cotizacion.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('contenedor_consolidado_cotizacion.documento', 'LIKE', "%{$search}%")
                        ->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
                });
            }

            // Ordenamiento
     

            // Paginación
            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            // Transformar los datos para incluir las columnas específicas
            $transformedData = [];
            $index = 1;

            foreach ($data->items() as $row) {
                $subdata = [
                    'index' => $index,
                    'nombre' => $this->cleanUtf8String($row->nombre),
                    'documento' => $this->cleanUtf8String($row->documento),
                    'telefono' => $this->cleanUtf8String($row->telefono),
                    'tipo_cliente' => $this->cleanUtf8String($row->name),
                    'total_logistica_impuestos' => $row->total_logistica_impuestos,
                    'total_pagos' => $row->total_pagos == 0 ? "0.00" : $row->total_pagos,
                    'pagos_count' => $row->pagos_count,
                    'id_cotizacion' => $row->id_cotizacion,
                    'pagos' => $row->pagos
                ];

                $transformedData[] = $subdata;
                $index++;
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem()
                ],

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones con documentación y pagos: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Obtiene y procesa la boleta para envío
     */
    private function getBoletaForSend($idCotizacion)
    {
        try {
            // Obtener URL de cotización final
            $cotizacion = DB::table('contenedor_consolidado_cotizacion')
                ->select('cotizacion_final_url')
                ->where('id', $idCotizacion)
                ->first();

            if (!$cotizacion || !$cotizacion->cotizacion_final_url) {
                Log::error('Cotización final no encontrada o sin URL', ['id_cotizacion' => $idCotizacion]);
                return false;
            }

            // Procesar URL y obtener contenido
            $fileUrl = str_replace(' ', '%20', $cotizacion->cotizacion_final_url);

            // Verificar si es una URL o ruta local
            if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                $fileContent = file_get_contents($fileUrl);
            } else {
                // Si es ruta local, intentar diferentes rutas
                $possiblePaths = [
                    storage_path('app/public/' . $fileUrl),
                    storage_path($fileUrl),
                    public_path($fileUrl)
                ];

                $fileContent = false;
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        $fileContent = file_get_contents($path);
                        break;
                    }
                }
            }

            if ($fileContent === false) {
                throw new \Exception("No se pudo leer el archivo Excel.");
            }

            // Crear archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'cotizacion_') . '.xlsx';
            if (file_put_contents($tempFile, $fileContent) === false) {
                throw new \Exception("No se pudo crear el archivo temporal.");
            }

            // Cargar Excel usando PhpSpreadsheet
            $spreadsheet = IOFactory::load($tempFile);

            // Generar boleta
            return $this->generateBoletaForSend($spreadsheet);
        } catch (\Exception $e) {
            Log::error('Error en getBoletaForSend: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } finally {
            // Limpiar archivo temporal si existe
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Descarga la plantilla general con datos procesados
     */
    public function downloadPlantillaGeneral($idContenedor)
    {
        try {
            // Obtener URL de factura general
            $contenedor = DB::table($this->table)
                ->select('factura_general_url')
                ->where('id', $idContenedor)
                ->first();

            if (!$contenedor || !$contenedor->factura_general_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la factura general'
                ], 404);
            }

            // Obtener datos del sistema
            $dataSystem = DB::table($this->table_contenedor_cotizacion . ' as CC')
                ->join($this->table_contenedor_tipo_cliente . ' as TC', 'CC.id_tipo_cliente', '=', 'TC.id')
                ->select(
                    'CC.nombre',
                    'CC.volumen',
                    'CC.documento',
                    'CC.volumen_doc',
                    'CC.valor_doc',
                    'CC.valor_cot',
                    'CC.volumen_china',
                    'TC.name',
                    'CC.vol_selected',
                    'CC.peso',
                    'CC.telefono'
                )
                ->where('CC.id_contenedor', $idContenedor)
                ->whereNotNull('CC.estado_cliente')
                ->get();

            // Obtener y validar ruta del archivo
            $facturaGeneralUrl = $this->getLocalPath($contenedor->factura_general_url);
            $plantillaPath = public_path('assets/templates/PLANTILLA_GENERAL.xlsx');

            if (!file_exists($plantillaPath)) {
                throw new \Exception('No se encontró la plantilla general');
            }

            // Cargar archivos Excel
            $objPHPExcel = IOFactory::load($facturaGeneralUrl);
            $sheet = $objPHPExcel->getActiveSheet();

            $newExcel = IOFactory::load($plantillaPath);
            $newSheet = $newExcel->getActiveSheet();

            // Configurar encabezados
            $headers = [
                'A' => 'CLIENTE',
                'B' => 'TIPO',
                'C' => 'DNI',
                'D' => 'TELEFONO',
                'E' => 'ITEM',
                'F' => 'PRODUCTO',
                'N' => 'CANTIDAD',
                'O' => 'PRECIO UNITARIO',
                'P' => 'ANTIDUMPING',
                'Q' => 'VALORACION',
                'R' => 'AD VALOREM',
                'S' => 'PERCEPCION',
                'T' => 'PESO',
                'U' => 'VOLUMEN SISTEMA'
            ];

            foreach ($headers as $column => $value) {
                $newSheet->setCellValue($column . '1', $value);
            }

            // Procesar datos
            $startRow = 26;
            $highestRow = $sheet->getHighestRow();
            $newRow = 2;
            $continue = true;

            while ($continue && $startRow <= $highestRow) {
                $itemNo = $sheet->getCell('B' . $startRow)->getValue();

                // Verificar si llegamos al final
                if (trim($itemNo) == "TOTAL FOB PRICE") {
                    break;
                }

                // Obtener rangos combinados para el cliente actual
                $mergeRanges = $sheet->getMergeCells();
                $clientMergeRange = null;

                foreach ($mergeRanges as $range) {
                    if (strpos($range, 'D') !== false) {
                        list($startCell, $endCell) = explode(':', $range);
                        $rangeStart = (int)preg_replace('/[^0-9]/', '', $startCell);
                        $rangeEnd = (int)preg_replace('/[^0-9]/', '', $endCell);

                        if ($startRow >= $rangeStart && $startRow <= $rangeEnd) {
                            $clientMergeRange = [
                                'start' => $rangeStart,
                                'end' => $rangeEnd
                            ];
                            break;
                        }
                    }
                }

                if (!$clientMergeRange) {
                    $startRow++;
                    continue;
                }

                // Obtener información del cliente
                $clientName = $sheet->getCell('D' . $clientMergeRange['start'])->getValue();
                $clientType = $sheet->getCell('C' . $clientMergeRange['start'])->getValue();

                $mergeStartRow = $newRow;

                // Procesar cada fila del rango
                for ($currentRow = $clientMergeRange['start']; $currentRow <= $clientMergeRange['end']; $currentRow++) {
                    // Insertar nueva fila
                    if ($newRow > 2) {
                        $newSheet->insertNewRowBefore($newRow);
                    }

                    // Combinar celdas de descripción
                    $newSheet->mergeCells('F' . $newRow . ':M' . $newRow);

                    // Copiar datos
                    $rowData = [
                        'A' => $clientName,
                        'B' => $clientType,
                        'E' => $sheet->getCell('B' . $currentRow)->getValue(),
                        'F' => $sheet->getCell('E' . $currentRow)->getValue(),
                        'N' => $sheet->getCell('M' . $currentRow)->getValue(),
                        'O' => $sheet->getCell('O' . $currentRow)->getValue(),
                        'P' => $sheet->getCell('S' . $currentRow)->getValue(),
                        'Q' => 0,
                        'R' => $sheet->getCell('R' . $currentRow)->getValue(),
                        'S' => 0.035,
                        'U' => $sheet->getCell('T' . $currentRow)->getValue()
                    ];

                    foreach ($rowData as $column => $value) {
                        $newSheet->setCellValue($column . $newRow, $value);
                    }

                    // Buscar datos del sistema para el cliente
                    foreach ($dataSystem as $data) {
                        if ($this->isNameMatch(trim($clientName), trim($data->nombre))) {
                            $newSheet->setCellValue('C' . $newRow, $data->documento);
                            $newSheet->setCellValue('D' . $newRow, $data->telefono);
                            $newSheet->setCellValue('T' . $newRow, $data->peso);
                            break;
                        }
                    }

                    // Aplicar estilos
                    $this->applyRowStyles($newSheet, $newRow);

                    $newRow++;
                }

                // Combinar celdas para el cliente
                if ($mergeStartRow < ($newRow - 1)) {
                    $this->applyClientMerges($newSheet, $mergeStartRow, $newRow - 1);
                }

                $startRow = $clientMergeRange['end'] + 1;
            }

            // Aplicar estilos finales
            $this->applyFinalStyles($newSheet, $newRow);

            // Generar archivo temporal para descarga
            $writer = new Xlsx($newExcel);
            $tempFile = storage_path('app/temp/plantilla_general_' . $idContenedor . '.xlsx');

            if (!file_exists(dirname($tempFile))) {
                mkdir(dirname($tempFile), 0755, true);
            }

            $writer->save($tempFile);

            return response()->download($tempFile, 'plantilla_general_' . $idContenedor . '.xlsx')
                ->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Error en downloadPlantillaGeneral: ' . $e->getMessage(), [
                'id_contenedor' => $idContenedor,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla general: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aplica estilos a una fila específica
     */
    private function applyRowStyles($sheet, $row)
    {
        // Estilo base para la fila
        $rowStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ];

        // Aplicar estilo base a toda la fila
        $sheet->getStyle('A' . $row . ':U' . $row)->applyFromArray($rowStyle);

        // Formato de moneda
        $currencyStyle = [
            'numberFormat' => [
                'formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE
            ]
        ];

        $currencyColumns = ['O', 'P', 'Q', 'R'];
        foreach ($currencyColumns as $column) {
            $sheet->getStyle($column . $row)->applyFromArray($currencyStyle);
        }

        // Formato de porcentaje
        $percentageStyle = [
            'numberFormat' => [
                'formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00
            ]
        ];
        $sheet->getStyle('S' . $row)->applyFromArray($percentageStyle);

        // Ajustar texto y ajuste automático
        $sheet->getStyle('A' . $row . ':U' . $row)
            ->getAlignment()
            ->setWrapText(true)
            ->setShrinkToFit(true);
    }

    /**
     * Aplica combinaciones de celdas para un cliente
     */
    private function applyClientMerges($sheet, $startRow, $endRow)
    {
        $columnsToMerge = ['A', 'B', 'C', 'D', 'T', 'U'];

        foreach ($columnsToMerge as $column) {
            $sheet->mergeCells($column . $startRow . ':' . $column . $endRow);
            $sheet->getStyle($column . $startRow)
                ->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Aplica estilos finales a la hoja
     */
    private function applyFinalStyles($sheet, $lastRow)
    {
        // Estilos de encabezado
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9D9D9']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
                'shrinkToFit' => true
            ]
        ];

        // Aplicar estilos al encabezado
        $sheet->getStyle('A1:U1')->applyFromArray($headerStyle);
        $sheet->mergeCells('F1:M1');

        // Establecer ancho de columnas
        $columnWidths = [
            'A' => 30, // CLIENTE
            'B' => 15, // TIPO
            'C' => 15, // DNI
            'D' => 15, // TELEFONO
            'E' => 10, // ITEM
            'F' => 40, // PRODUCTO (merged)
            'N' => 12, // CANTIDAD
            'O' => 15, // PRECIO UNITARIO
            'P' => 15, // ANTIDUMPING
            'Q' => 15, // VALORACION
            'R' => 15, // AD VALOREM
            'S' => 15, // PERCEPCION
            'T' => 12, // PESO
            'U' => 15  // VOLUMEN SISTEMA
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Aplicar bordes a toda la tabla
        $tableStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ],
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
        $sheet->getStyle('A1:U' . ($lastRow))->applyFromArray($tableStyle);

        // Formato de porcentaje para columna R
        $sheet->getStyle('R2:R' . ($lastRow - 1))
            ->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

        // Fórmula de suma para columna P con estilo
        $sheet->setCellValue('P' . $lastRow, '=SUM(P2:P' . ($lastRow - 1) . ')');
        $totalRowStyle = [
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2']
            ]
        ];
        $sheet->getStyle('P' . $lastRow)->applyFromArray($totalRowStyle);

        // Ajustar altura de filas
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getRowDimension(1)->setRowHeight(25); // Encabezado más alto
    }

    /**
     * Verifica si dos nombres coinciden
     */
    private function isNameMatch($fullName, $partialName)
    {
        // Verificación inicial
        if (empty($fullName) || empty($partialName)) {
            return false;
        }

        $fullName = $this->normalizeString($fullName);
        $partialName = $this->normalizeString($partialName);

        // Verificar que normalizeString no devolvió cadenas vacías
        if (empty($fullName) || empty($partialName)) {
            return false;
        }

        // Comparación exacta primero
        if ($fullName === $partialName) {
            return true;
        }

        // Verificar si el nombre parcial está contenido en el completo
        // Verificar que $partialName no esté vacío antes de usar strpos
        if (!empty($partialName) && strpos($fullName, $partialName) !== false) {
            return true;
        }

        // Comparar palabra por palabra
        $fullWords = array_filter(explode(' ', $fullName)); // array_filter elimina elementos vacíos
        $partialWords = array_filter(explode(' ', $partialName)); // array_filter elimina elementos vacíos

        // Verificar que tenemos palabras para comparar
        if (empty($fullWords) || empty($partialWords)) {
            return false;
        }

        $matchCount = 0;

        foreach ($partialWords as $partialWord) {
            // Verificar que la palabra parcial no esté vacía
            if (empty($partialWord)) {
                continue;
            }

            foreach ($fullWords as $fullWord) {
                // Verificar que la palabra completa no esté vacía
                if (empty($fullWord)) {
                    continue;
                }

                if (strpos($fullWord, $partialWord) !== false) {
                    $matchCount++;
                    break;
                }
            }
        }

        // Si coinciden al menos 70% de las palabras del nombre parcial
        return $matchCount >= ceil(count($partialWords) * 0.7);
    }
    private function normalizeString($string)
    {
        $string = strtolower(trim($string));
        $accents = [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ā' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'ē' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'ī' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'ō' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'ū' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'Á' => 'a',
            'À' => 'a',
            'Ä' => 'a',
            'Â' => 'a',
            'Ā' => 'a',
            'Ã' => 'a',
            'É' => 'e',
            'È' => 'e',
            'Ë' => 'e',
            'Ê' => 'e',
            'Ē' => 'e',
            'Í' => 'i',
            'Ì' => 'i',
            'Ï' => 'i',
            'Î' => 'i',
            'Ī' => 'i',
            'Ó' => 'o',
            'Ò' => 'o',
            'Ö' => 'o',
            'Ô' => 'o',
            'Ō' => 'o',
            'Õ' => 'o',
            'Ú' => 'u',
            'Ù' => 'u',
            'Ü' => 'u',
            'Û' => 'u',
            'Ū' => 'u',
            'Ñ' => 'n',
            'Ç' => 'c'
        ];

        return strtr($string, $accents);
    }
    public function updateEstadoCotizacionFinal(Request $request)
    {
        try {
            $cotizacion = Cotizacion::find($request->idCotizacion);
            $cotizacion->estado_cotizacion_final = $request->estado;
            $cotizacion->save();
            if ($request->estado == 'COTIZADO') {
                //get phone from cotizacion table where id=idCotizacionFinal
                $cotizacion = DB::table($this->table_contenedor_cotizacion . ' as CC')
                    ->select([
                        'CC.telefono',
                        'CC.id_contenedor',
                        'CC.impuestos_final',
                        'CC.volumen_final',
                        'CC.monto_final',
                        'CC.tarifa_final',
                        'CC.nombre',
                        'CC.logistica_final',
                        DB::raw('(
                            SELECT IFNULL(SUM(cccp.monto), 0)
                            FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                            JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                            WHERE cccp.id_cotizacion = CC.id
                            AND (ccp.name = "LOGISTICA" OR ccp.name = "IMPUESTOS")
                        ) as total_pagos')
                    ])
                    ->where('CC.id', $request->idCotizacion)
                    ->first();

                if (!$cotizacion) {
                    throw new \Exception('Cotización no encontrada');
                }

                $telefono = preg_replace('/\s+/', '', $cotizacion->telefono);
                $phoneNumberId = $telefono ? $telefono . '@c.us' : '';
                $totalPagos = $cotizacion->total_pagos;

                $volumen = $cotizacion->volumen_final;
                $nombre = $cotizacion->nombre;
                $logisticaFinal = $cotizacion->logistica_final;
                $impuestosFinal = $cotizacion->impuestos_final;
                $total = $logisticaFinal + $impuestosFinal;
                $totalAPagar = $total - $totalPagos;
                $idContenedor = $cotizacion->id_contenedor;
                $contenedor = Contenedor::select('fecha_arribo', 'carga')
                    ->select('fecha_arribo', 'carga')
                    ->where('id', $idContenedor)
                    ->first();

                if (!$contenedor) {
                    throw new \Exception('Contenedor no encontrado');
                }

                $carga = $contenedor->carga;
                $fechaArribo = $contenedor->fecha_arribo;
                $message = "📦 *Consolidado #" . $carga . "*\n" .
                    "Hola " . $nombre . " 😁 un gusto saludarte! \n" .
                    "A continuación te envio la cotización final de tu importación📋📦.\n" .
                    "🙋‍♂️PAGO PENDIENTE: \n" .
                    "☑️Costo CBM: $" . number_format($logisticaFinal, 2) . "\n" .
                    "☑️Impuestos: $" . number_format($impuestosFinal, 2) . "\n" .
                    "☑️Total: $" . number_format($total, 2) . "\n" .
                    "Pronto le aviso nuevos avances, que tengan buen dia \n" .
                    "Último día de pago: " . date('d/m/Y', strtotime($fechaArribo)) . "\n";
                $this->sendMessage($message);
                $pathCotizacionFinalPDF = $this->getBoletaForSend($request->idCotizacion);
                $this->sendMedia($pathCotizacionFinalPDF, null, null, null, 3);
                $message = "Resumen de Pago\n" .
                    "✅Cotización final: $" . number_format($total, 2) . "\n" .
                    "✅Adelanto: $" . number_format($totalPagos, 2) . "\n" .
                    "✅ Pendiente de pago: $" . number_format($totalAPagar, 2) . "\n";
                $this->sendMessage($message, null, 5);
                $pagosUrl = public_path('assets/images/pagos-full.jpg');
                $this->sendMedia($pagosUrl, 'jpg', null, null, 10);
            }
            return response()->json([
                'success' => true,
                'message' => 'Estado de cotización final actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado de cotización final: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Obtiene la ruta local de un archivo, ya sea desde URL o ruta local
     * Si es una URL, descarga el archivo temporalmente
     */
    private function getLocalPath($fileUrl)
    {
        try {
            // Si es una URL externa
            if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                // Crear directorio temporal si no existe
                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                // Generar nombre de archivo temporal
                $tempFile = $tempDir . '/' . time() . '_' . basename($fileUrl);
                
                // Descargar archivo
                $fileContent = file_get_contents($fileUrl);
                if ($fileContent === false) {
                    throw new \Exception("No se pudo descargar el archivo: " . $fileUrl);
                }
                
                // Guardar archivo temporal
                if (file_put_contents($tempFile, $fileContent) === false) {
                    throw new \Exception("No se pudo guardar el archivo temporal");
                }
                
                return $tempFile;
            }

            // Si es una ruta local, probar diferentes ubicaciones
            $possiblePaths = [
                storage_path('app/public/' . $fileUrl),
                storage_path($fileUrl),
                public_path($fileUrl),
                storage_path('app/' . $fileUrl),
                base_path($fileUrl),
                $fileUrl // ruta tal cual
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            throw new \Exception("No se encontró el archivo en ninguna ubicación: " . $fileUrl);

        } catch (\Exception $e) {
            Log::error('Error en getLocalPath: ' . $e->getMessage(), [
                'fileUrl' => $fileUrl,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function uploadFacturaComercial(Request $request)
    {
        try {
            $idContenedor = $request->idContenedor;
            $file = $request->file;
            $path = $file->storeAs('cargaconsolidada/cotizacionfinal/' . $idContenedor, 'factura_general.xlsx');
            $contenedor = Contenedor::find($idContenedor);
            $contenedor->factura_general_url = $path;
            $contenedor->save();
            return response()->json([
                'success' => true,
                'message' => 'Factura general actualizada correctamente',
                'path' => $path
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar factura general: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar factura general: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpia y valida caracteres UTF-8
     */
    private function cleanUtf8String($string)
    {
        if (empty($string)) {
            return '';
        }

        // Convertir a string si no lo es
        $string = (string) $string;

        // Verificar si la cadena es UTF-8 válida
        if (!mb_check_encoding($string, 'UTF-8')) {
            // Intentar convertir desde diferentes encodings
            $encodings = ['ISO-8859-1', 'ISO-8859-15', 'Windows-1252', 'CP1252'];
            
            foreach ($encodings as $encoding) {
                if (mb_check_encoding($string, $encoding)) {
                    $string = mb_convert_encoding($string, 'UTF-8', $encoding);
                    break;
                }
            }
        }

        // Limpiar caracteres inválidos
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Remover caracteres de control excepto tab, newline, carriage return
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        
        // Normalizar espacios
        $string = preg_replace('/\s+/', ' ', $string);
        
        return trim($string);
    }
}
