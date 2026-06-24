<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\PagoConcept;
use App\Services\CargaConsolidada\SeguimientoConsolidadoCorteConfig;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveCellRepository;
use App\Services\CargaConsolidada\SeguimientoConsolidadoRowSyncService;
use App\Support\CargaConsolidada\SeguimientoDriveCellRowKey;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SeguimientoConsolidadoExcelService
{
    private const LOG_PREFIX = '[SeguimientoDrive]';

    private const COL_YIWU = 2;
    private const COL_RECIBIR = 12;
    private const COL_CONTACTAR = 20;
    private const TABLE_WIDTH_YIWU = 9;
    private const TABLE_WIDTH_CONTACTAR = 6;
    private const TABLE_WIDTH_SYNC = 7;

    private const COLOR_YIWU = 'FF92D050';
    private const COLOR_RECIBIR = 'FFFFC000';
    private const COLOR_CONTACTAR = 'FF9BC2E6';
    private const COLOR_RESERVADO = 'FFC6EFCE';
    private const COLOR_CONFIG = 'FFE7E6E6';

    private const HEADERS_YIWU = ['CONS', 'VENDEDOR', 'CLIENTE', 'CODE SUPPLIER', 'CBM', 'TIPO CARGA', 'ESTADO PAGO', 'ULT. ACT.', 'NOTAS'];
    private const HEADERS_RECIBIR = ['CONS', 'VENDEDOR', 'CLIENTE', 'CBM', 'FECHA', 'PROVEEDOR', 'ULT. ACT.'];
    private const HEADERS_CONTACTAR = ['CONS', 'VENDEDOR', 'CLIENTE', 'CBM', 'CODE SUPPLIER', 'NOTE'];

    /** @var CotizacionExportService */
    private $cotizacionExportService;

    /** @var SeguimientoConsolidadoRowSyncService */
    private $rowSyncService;

    public function __construct(
        CotizacionExportService $cotizacionExportService,
        SeguimientoConsolidadoRowSyncService $rowSyncService
    ) {
        $this->cotizacionExportService = $cotizacionExportService;
        $this->rowSyncService = $rowSyncService;
    }

    /**
     * @param int $idContenedor
     * @param Request|null $request
     * @return Spreadsheet
     */
    public function buildSpreadsheet($idContenedor, Request $request = null)
    {
        $this->log('info', 'Construyendo spreadsheet', ['id_contenedor' => (int) $idContenedor]);

        $request = $request ?: new Request();
        $hoja1StartedAt = microtime(true);
        $spreadsheet = $this->cotizacionExportService->buildCotizacionesSpreadsheet($request, $idContenedor, true);
        $this->log('info', 'Hoja Cotizaciones completada', [
            'id_contenedor' => (int) $idContenedor,
            'ms' => (int) round((microtime(true) - $hoja1StartedAt) * 1000),
        ]);

        $hoja2StartedAt = microtime(true);
        $sheet2 = new Worksheet($spreadsheet, 'Seguimiento');
        $spreadsheet->addSheet($sheet2, 1);
        $this->buildSeguimientoSheet($sheet2, $idContenedor);
        $this->log('info', 'Hoja Seguimiento completada', [
            'id_contenedor' => (int) $idContenedor,
            'ms' => (int) round((microtime(true) - $hoja2StartedAt) * 1000),
        ]);

        return $spreadsheet;
    }

    /**
     * @param int $idContenedor
     * @param Request|null $request
     * @return string
     */
    public function writeTempFile($idContenedor, Request $request = null)
    {
        $spreadsheet = $this->buildSpreadsheet($idContenedor, $request);
        $contenedor = Contenedor::find($idContenedor);
        $carga = $contenedor ? (string) $contenedor->carga : (string) $idContenedor;
        $fileName = $this->buildFileName($carga);
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('seg_consolidado_') . '_' . $fileName;

        $saveStartedAt = microtime(true);
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmp);

        $this->log('info', 'Archivo temporal guardado', [
            'id_contenedor' => (int) $idContenedor,
            'file_name' => $fileName,
            'tmp_path' => $tmp,
            'size_bytes' => is_file($tmp) ? filesize($tmp) : null,
            'save_ms' => (int) round((microtime(true) - $saveStartedAt) * 1000),
        ]);

        return $tmp;
    }

    /**
     * @param string $carga
     * @return string
     */
    public function buildFileName($carga)
    {
        $now = Carbon::now('America/Lima');

        return 'cotizaciones_#'
            . $carga
            . '_'
            . $now->format('Y-m-d')
            . '_'
            . $now->format('H_i_s')
            . '.xlsx';
    }

    /**
     * @param Worksheet $sheet
     * @param int $idContenedor
     */
    private function buildSeguimientoSheet(Worksheet $sheet, $idContenedor)
    {
        $contenedor = Contenedor::find($idContenedor);
        $carga = $contenedor ? (string) $contenedor->carga : '';
        $rows = $this->fetchProveedoresSeguimiento($idContenedor, $carga);
        $this->enrichFechasDatosProveedor($idContenedor, $rows);

        $tieneHistoricoContactar = SeguimientoConsolidadoCorteConfig::contenedorTieneHistoricoContactar($idContenedor);
        $periodoAbierto = $tieneHistoricoContactar
            ? SeguimientoConsolidadoCorteConfig::periodoContactarAbierto()
            : SeguimientoConsolidadoCorteConfig::periodoContactarDesdeInicioDia();
        $contactarDesde = $tieneHistoricoContactar
            ? null
            : $periodoAbierto['inicio'];

        $cotizacionesConPago = $this->loadCotizacionesConPagoAsociado($idContenedor);
        $groups = $this->classifyRows($rows, $contactarDesde, $cotizacionesConPago);
        $this->applyManualYiwuNotes($idContenedor, $groups);
        $this->applyManualContactarNotes($idContenedor, $groups);
        $this->rowSyncService->applyUltimaActualizacion($idContenedor, $groups);

        $this->log('info', 'Hoja Seguimiento: datos clasificados', [
            'id_contenedor' => (int) $idContenedor,
            'carga' => $carga,
            'total_proveedores' => count($rows),
            'yiwu' => count($groups['yiwu']),
            'recibir' => count($groups['recibir']),
            'contactar_abierto' => count($groups['contactar']),
            'corte_hora' => $periodoAbierto['hora'],
            'contactar_desde' => $periodoAbierto['inicio']->toDateTimeString(),
            'primera_vinculacion_contactar' => !$tieneHistoricoContactar,
        ]);

        $configRow = 1;
        $titleRow = 3;
        $headerRow = 4;
        $dataStartRow = 5;

        $configLabel = $tieneHistoricoContactar
            ? SeguimientoConsolidadoCorteConfig::excelConfigLabel()
            : SeguimientoConsolidadoCorteConfig::excelConfigLabelPrimeraVez();
        $this->writeConfigSection($sheet, $configRow, $configLabel);

        $this->writeTableTitle($sheet, self::COL_YIWU, $titleRow, 'CARGA EN YIWU', self::COLOR_YIWU, self::TABLE_WIDTH_YIWU);
        $this->writeTableTitle($sheet, self::COL_RECIBIR, $titleRow, 'CARGA POR RECIBIR', self::COLOR_RECIBIR, self::TABLE_WIDTH_SYNC);

        $this->writeTableHeaders($sheet, self::COL_YIWU, $headerRow, self::HEADERS_YIWU, self::COLOR_YIWU);
        $this->writeTableHeaders($sheet, self::COL_RECIBIR, $headerRow, self::HEADERS_RECIBIR, self::COLOR_RECIBIR);

        $yiwuFooterRow = $this->writeYiwuDataSection(
            $sheet,
            self::COL_YIWU,
            $headerRow,
            $dataStartRow,
            $groups['yiwu']
        );

        $recibirFooterRow = $this->writeTableDataSection(
            $sheet,
            self::COL_RECIBIR,
            $headerRow,
            $dataStartRow,
            self::TABLE_WIDTH_SYNC,
            $groups['recibir'],
            [$this, 'writeRecibirRow']
        );

        $this->writeYiwuFooter($sheet, self::COL_YIWU, $yiwuFooterRow, $carga, $groups['yiwu']);
        $this->writeRecibirFooter($sheet, self::COL_RECIBIR, $recibirFooterRow, $carga, $groups['recibir']);

        // CONTACTAR: histórico congelado primero, periodo abierto al final (misma columna T–Y).
        $contactarRow = $titleRow;
        $contactarRow = $this->writeContactarHistorico($sheet, $idContenedor, $contactarRow, $carga, $rows);
        $this->writeContactarAbiertoBlock(
            $sheet,
            $contactarRow,
            $carga,
            $periodoAbierto,
            $groups['contactar']
        );

        $this->applyFixedColumnWidths($sheet);
    }

    /**
     * @param Worksheet $sheet
     */
    private function applyFixedColumnWidths(Worksheet $sheet)
    {
        $widths = [
            2 => 8, 3 => 14, 4 => 28, 5 => 14, 6 => 10, 7 => 10, 8 => 14, 9 => 14, 10 => 18,
            12 => 8, 13 => 14, 14 => 28, 15 => 10, 16 => 12, 17 => 14, 18 => 14,
            20 => 8, 21 => 14, 22 => 28, 23 => 10, 24 => 14, 25 => 12,
        ];

        foreach ($widths as $colIndex => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setWidth($width);
        }
    }

    /**
     * @param Worksheet $sheet
     * @param int $row
     */
    private function writeConfigSection(Worksheet $sheet, $row, $label)
    {
        $range = 'B' . $row . ':Y' . $row;
        $sheet->mergeCells($range);
        $sheet->setCellValue('B' . $row, $label);
        $this->applyFill($sheet, $range, self::COLOR_CONFIG, true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getFont()->setSize(10);
        $this->applyBorders($sheet, $range);
    }

    /**
     * @param int $startCol
     * @param int $startRow
     * @param int $endRow
     * @param int $width
     * @return string
     */
    private function tableRange($startCol, $startRow, $endRow, $width)
    {
        $start = Coordinate::stringFromColumnIndex($startCol);
        $end = Coordinate::stringFromColumnIndex($startCol + $width - 1);

        return $start . $startRow . ':' . $end . $endRow;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param Carbon|null $contactarDesde Inicio del periodo CONTACTAR abierto (null = último corte).
     * @param array<int, true> $cotizacionesConPago id_cotizacion => true si tiene al menos un pago asociado
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function classifyRows(array $rows, Carbon $contactarDesde = null, array $cotizacionesConPago = [])
    {
        $yiwu = [];
        $recibir = [];
        $contactar = [];
        $byCotizacion = [];

        foreach ($rows as $row) {
            $estadoChina = strtoupper(trim((string) ($row['estado_china'] ?? '')));
            $estadoCoord = strtoupper(trim((string) ($row['estado_coordinacion'] ?? '')));
            $idCotizacion = (int) ($row['id_cotizacion'] ?? 0);

            if ($idCotizacion > 0) {
                if (!isset($byCotizacion[$idCotizacion])) {
                    $byCotizacion[$idCotizacion] = [
                        'base' => $row,
                        'proveedores' => [],
                        'cbm_yiwu' => 0.0,
                    ];
                }

                $byCotizacion[$idCotizacion]['proveedores'][] = $row;

                if ($this->isProveedorEnYiwu($estadoChina, $estadoCoord)) {
                    $byCotizacion[$idCotizacion]['cbm_yiwu'] += (float) ($row['cbm_yiwu'] ?? 0);
                }
            }

            if ($this->isProveedorEnYiwu($estadoChina, $estadoCoord)) {
                continue;
            }

            // Contactar abierto: DATOS PROVEEDOR sin fecha, ingresados desde el último corte.
            if ($estadoCoord === 'DATOS PROVEEDOR' && !$this->hasFechaLlegada($row)) {
                if ($this->isContactarPeriodoAbierto($row, $contactarDesde)) {
                    $contactar[] = $row;
                }
                continue;
            }

            // Por recibir: no YIWU y con fecha de llegada (puede convivir otro proveedor en YIWU).
            if ($this->hasFechaLlegada($row)) {
                $recibir[] = $row;
            }
        }

        foreach ($byCotizacion as $group) {
            $tieneProveedorEnYiwu = false;
            $todosEnYiwu = true;

            foreach ($group['proveedores'] as $proveedor) {
                $china = strtoupper(trim((string) ($proveedor['estado_china'] ?? '')));
                $coord = strtoupper(trim((string) ($proveedor['estado_coordinacion'] ?? '')));

                if ($this->isProveedorEnYiwu($china, $coord)) {
                    $tieneProveedorEnYiwu = true;
                } else {
                    $todosEnYiwu = false;
                }
            }

            if (!$tieneProveedorEnYiwu) {
                continue;
            }

            $base = $group['base'];
            $idCotizacion = (int) ($base['id_cotizacion'] ?? 0);
            $tipoCarga = $todosEnYiwu ? 'C' : 'P';
            $estadoPago = $this->resolveEstadoPagoYiwu($idCotizacion, $cotizacionesConPago);

            foreach ($group['proveedores'] as $proveedor) {
                $china = strtoupper(trim((string) ($proveedor['estado_china'] ?? '')));
                $coord = strtoupper(trim((string) ($proveedor['estado_coordinacion'] ?? '')));
                $enYiwu = $this->isProveedorEnYiwu($china, $coord);

                $yiwu[] = [
                    'id_cotizacion' => $idCotizacion,
                    'id_proveedor' => (int) ($proveedor['id_proveedor'] ?? 0),
                    'cons' => $base['cons'],
                    'vendedor' => $base['vendedor'],
                    'cliente' => $base['cliente'],
                    'code_supplier' => $proveedor['code_supplier'] ?? '',
                    'en_yiwu' => $enYiwu,
                    'cbm_yiwu' => $group['cbm_yiwu'],
                    'tipo_carga' => $tipoCarga,
                    'estado_pago' => $estadoPago,
                    'notas' => '',
                ];
            }
        }

        return compact('yiwu', 'recibir', 'contactar');
    }

    /**
     * YIWU: proveedor RECIBIDO (R) o INSPECCIONADO (coord) / INSPECTION (china).
     *
     * @param string $estadoChina
     * @param string $estadoCoord
     * @return bool
     */
    private function isProveedorEnYiwu($estadoChina, $estadoCoord)
    {
        if ($estadoChina === 'R' || $estadoChina === 'INSPECTION') {
            return true;
        }

        return $estadoCoord === 'INSPECCIONADO';
    }

    /**
     * Periodo CONTACTAR abierto: ingreso >= último corte.
     *
     * @param array<string, mixed> $row
     * @param Carbon|null $contactarDesde
     * @return bool
     */
    private function isContactarPeriodoAbierto(array $row, Carbon $contactarDesde = null)
    {
        $inicio = $contactarDesde ?: SeguimientoConsolidadoCorteConfig::ultimoCorteFin();
        $fecha = $this->parseFechaDatosProveedor($row['fecha_datos_proveedor'] ?? null);

        if ($fecha === null) {
            return false;
        }

        return $fecha->gte($inicio);
    }

    /**
     * @param mixed $value
     * @return Carbon|null
     */
    private function parseFechaDatosProveedor($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return SeguimientoConsolidadoDateFormatter::parseUtcToLima($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param int $idContenedor
     * @param array<int, array<string, mixed>> $rows
     */
    private function enrichFechasDatosProveedor($idContenedor, array &$rows)
    {
        $trackingTable = $this->resolveTrackingTable();
        if (!$trackingTable || empty($rows)) {
            return;
        }

        $fechas = DB::table($trackingTable)
            ->join('contenedor_consolidado_cotizacion_proveedores as P', 'P.id', '=', $trackingTable . '.id_proveedor')
            ->where('P.id_contenedor', $idContenedor)
            ->where($trackingTable . '.estado', 'DATOS PROVEEDOR')
            ->groupBy($trackingTable . '.id_proveedor')
            ->selectRaw(
                $trackingTable . '.id_proveedor as id_proveedor, '
                . 'MAX(COALESCE(' . $trackingTable . '.updated_at, ' . $trackingTable . '.created_at)) as fecha_datos_proveedor'
            )
            ->pluck('fecha_datos_proveedor', 'id_proveedor');

        foreach ($rows as $index => $row) {
            $idProveedor = (int) ($row['id_proveedor'] ?? 0);
            if ($idProveedor > 0 && isset($fechas[$idProveedor])) {
                $rows[$index]['fecha_datos_proveedor'] = $fechas[$idProveedor];
            }
        }
    }

    /**
     * Cotizaciones del consolidado con al menos un pago (LOGÍSTICA o IMPUESTOS).
     *
     * @param int $idContenedor
     * @return array<int, true>
     */
    private function loadCotizacionesConPagoAsociado($idContenedor)
    {
        $ids = DB::table('contenedor_consolidado_cotizacion_coordinacion_pagos as p')
            ->join('contenedor_consolidado_cotizacion as c', 'c.id', '=', 'p.id_cotizacion')
            ->where('c.id_contenedor', $idContenedor)
            ->whereIn('p.id_concept', [
                PagoConcept::CONCEPT_PAGO_LOGISTICA,
                PagoConcept::CONCEPT_PAGO_IMPUESTOS,
            ])
            ->distinct()
            ->pluck('p.id_cotizacion');

        $map = [];
        foreach ($ids as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }

    /**
     * ESTADO PAGO en YIWU: RESERVADO si la cotización tiene al menos un pago asociado.
     *
     * @param int $idCotizacion
     * @param array<int, true> $cotizacionesConPago
     * @return string
     */
    private function resolveEstadoPagoYiwu($idCotizacion, array $cotizacionesConPago)
    {
        if ($idCotizacion > 0 && isset($cotizacionesConPago[$idCotizacion])) {
            return 'RESERVADO';
        }

        return 'NO RESERVADO';
    }

    /**
     * @return string|null
     */
    private function resolveTrackingTable()
    {
        if (Schema::hasTable('contenedor_proveedor_estados_tracking')) {
            return 'contenedor_proveedor_estados_tracking';
        }

        if (Schema::hasTable('contenedor_proveedor_estados_tracking_estados')) {
            return 'contenedor_proveedor_estados_tracking_estados';
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param Carbon $inicio
     * @param Carbon $fin
     * @return array<int, array<string, mixed>>
     */
    private function filterContactarPeriodoCerrado(array $rows, Carbon $inicio, Carbon $fin)
    {
        $items = [];

        foreach ($rows as $row) {
            $fecha = $this->parseFechaDatosProveedor($row['fecha_datos_proveedor'] ?? null);
            if ($fecha === null) {
                continue;
            }

            if ($fecha->gte($inicio) && $fecha->lt($fin)) {
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * Por recibir / contactar: tiene fecha de llegada china o Perú.
     *
     * @param array<string, mixed> $row
     * @return bool
     */
    private function hasFechaLlegada(array $row)
    {
        $fechaPeru = trim((string) ($row['fecha_llegada_peru'] ?? ''));
        $fechaChina = trim((string) ($row['fecha_llegada_china'] ?? ''));

        return $fechaPeru !== '' || $fechaChina !== '';
    }

    /**
     * Escribe filas de datos con rango exacto (header + data) por columna de tabla.
     *
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $headerRow
     * @param int $dataStartRow
     * @param int $width
     * @param array<int, array<string, mixed>> $items
     * @param callable $writeRowFn
     * @return int Primera fila libre debajo del bloque de datos (donde va el total).
     */
    private function writeTableDataSection(Worksheet $sheet, $startCol, $headerRow, $dataStartRow, $width, array $items, callable $writeRowFn)
    {
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            $writeRowFn($sheet, $startCol, $dataStartRow + $i, $items[$i], false);
        }

        if ($count > 0) {
            $dataEndRow = $dataStartRow + $count - 1;
            $this->applyBorders($sheet, $this->tableRange($startCol, $headerRow, $dataEndRow, $width));

            return $dataEndRow + 1;
        }

        return $dataStartRow;
    }

    /**
     * Escribe filas YIWU (una por proveedor) y fusiona columnas de cotización como en hoja 1.
     *
     * @param array<int, array<string, mixed>> $items
     * @return int Primera fila libre debajo del bloque de datos.
     */
    private function writeYiwuDataSection(Worksheet $sheet, $startCol, $headerRow, $dataStartRow, array $items)
    {
        $width = self::TABLE_WIDTH_YIWU;
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            $this->writeYiwuRow($sheet, $startCol, $dataStartRow + $i, $items[$i], false);
        }

        if ($count === 0) {
            return $dataStartRow;
        }

        $dataEndRow = $dataStartRow + $count - 1;
        $this->applyBorders($sheet, $this->tableRange($startCol, $headerRow, $dataEndRow, $width));
        $this->mergeYiwuCotizacionColumns($sheet, $startCol, $dataStartRow, $dataEndRow, $items);

        return $dataEndRow + 1;
    }

    /**
     * Fusiona CONS, VENDEDOR, CLIENTE, CBM, TIPO CARGA, ESTADO PAGO y ULT. ACT. por cotización.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function mergeYiwuCotizacionColumns(Worksheet $sheet, $startCol, $dataStartRow, $dataEndRow, array $items)
    {
        $mergeOffsets = [0, 1, 2, 4, 5, 6, 7];
        $groups = [];
        $currentCotizacion = null;
        $groupStart = $dataStartRow;

        foreach ($items as $index => $item) {
            $idCotizacion = (int) ($item['id_cotizacion'] ?? 0);
            $row = $dataStartRow + $index;

            if ($currentCotizacion !== null && $idCotizacion !== $currentCotizacion) {
                $groups[] = ['start' => $groupStart, 'end' => $row - 1];
                $groupStart = $row;
            }

            $currentCotizacion = $idCotizacion;
        }

        if ($currentCotizacion !== null) {
            $groups[] = ['start' => $groupStart, 'end' => $dataEndRow];
        }

        foreach ($groups as $group) {
            if ($group['end'] <= $group['start']) {
                continue;
            }

            foreach ($mergeOffsets as $offset) {
                $col = Coordinate::stringFromColumnIndex($startCol + $offset);
                $range = $col . $group['start'] . ':' . $col . $group['end'];
                $sheet->mergeCells($range);
                $sheet->getStyle($range)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param string $carga
     * @param array<int, array<string, mixed>> $items
     */
    private function writeYiwuFooter(Worksheet $sheet, $startCol, $row, $carga, array $items)
    {
        if (empty($items)) {
            return;
        }

        $totalCbm = 0.0;
        $seenCotizaciones = [];
        foreach ($items as $item) {
            $idCotizacion = (int) ($item['id_cotizacion'] ?? 0);
            if ($idCotizacion > 0 && isset($seenCotizaciones[$idCotizacion])) {
                continue;
            }
            if ($idCotizacion > 0) {
                $seenCotizaciones[$idCotizacion] = true;
            }
            $totalCbm += (float) ($item['cbm_yiwu'] ?? 0);
        }

        $this->writeTableTotalRow(
            $sheet,
            $startCol,
            $row,
            self::TABLE_WIDTH_YIWU,
            self::COLOR_YIWU,
            'TOTAL EN YIWU - CONS #' . $carga,
            4,
            $this->formatNumber($totalCbm)
        );
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param string $carga
     * @param array<int, array<string, mixed>> $items
     */
    private function writeRecibirFooter(Worksheet $sheet, $startCol, $row, $carga, array $items)
    {
        if (empty($items)) {
            return;
        }

        $totalCbm = 0.0;
        foreach ($items as $item) {
            $totalCbm += (float) ($item['cbm_recibir'] ?? 0);
        }

        $this->writeTableTotalRow(
            $sheet,
            $startCol,
            $row,
            self::TABLE_WIDTH_SYNC,
            self::COLOR_RECIBIR,
            'TOTAL POR RECIBIR - CONS #' . $carga,
            3,
            $this->formatNumber($totalCbm)
        );
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param int $width
     * @param string $color
     * @param string $label
     * @param int $valueColOffset
     * @param mixed $value
     */
    private function writeTableTotalRow(Worksheet $sheet, $startCol, $row, $width, $color, $label, $valueColOffset, $value)
    {
        $start = Coordinate::stringFromColumnIndex($startCol);
        $valueCol = Coordinate::stringFromColumnIndex($startCol + $valueColOffset);
        $end = Coordinate::stringFromColumnIndex($startCol + $width - 1);
        $labelRange = $start . $row . ':' . Coordinate::stringFromColumnIndex($startCol + $valueColOffset - 1) . $row;
        $fullRange = $start . $row . ':' . $end . $row;

        $sheet->mergeCells($labelRange);
        $sheet->setCellValue($start . $row, $label);
        $sheet->setCellValue($valueCol . $row, $value);

        $this->applyFill($sheet, $fullRange, $color, true);
        $sheet->getStyle($labelRange)->getFont()->setBold(true);
        $sheet->getStyle($valueCol . $row)->getFont()->setBold(true);
        $this->applyBorders($sheet, $fullRange);
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param string $title
     * @param string $color
     * @param int $width
     */
    private function writeTableTitle(Worksheet $sheet, $startCol, $row, $title, $color, $width)
    {
        $start = Coordinate::stringFromColumnIndex($startCol);
        $end = Coordinate::stringFromColumnIndex($startCol + $width - 1);
        $range = $start . $row . ':' . $end . $row;

        $sheet->mergeCells($range);
        $sheet->setCellValue($start . $row, $title);
        $this->applyFill($sheet, $range, $color, true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->applyBorders($sheet, $range);
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param array<int, string> $headers
     * @param string $color
     */
    private function writeTableHeaders(Worksheet $sheet, $startCol, $row, array $headers, $color)
    {
        $start = Coordinate::stringFromColumnIndex($startCol);
        $end = Coordinate::stringFromColumnIndex($startCol + count($headers) - 1);
        $range = $start . $row . ':' . $end . $row;

        foreach ($headers as $index => $header) {
            $col = Coordinate::stringFromColumnIndex($startCol + $index);
            $sheet->setCellValue($col . $row, $header);
        }

        $this->applyFill($sheet, $range, $color, true);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->applyBorders($sheet, $range);
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param array<string, mixed> $item
     */
    private function writeYiwuRow(Worksheet $sheet, $startCol, $row, array $item, $applyBorder = true)
    {
        $values = [
            $item['cons'],
            $item['vendedor'],
            $item['cliente'],
            $item['code_supplier'] ?? '',
            $this->formatNumber($item['cbm_yiwu']),
            $item['tipo_carga'],
            $item['estado_pago'],
            $item['ultima_actualizacion'] ?? '',
            $item['notas'] ?? '',
        ];

        $this->writeRowValues($sheet, $startCol, $row, $values, $applyBorder);

        if (!empty($item['en_yiwu'])) {
            $codeCol = Coordinate::stringFromColumnIndex($startCol + 3);
            $this->applyFill($sheet, $codeCol . $row, self::COLOR_YIWU, false);
        }

        if (strtoupper(trim((string) $item['estado_pago'])) === 'RESERVADO') {
            $estadoCol = Coordinate::stringFromColumnIndex($startCol + 6);
            $this->applyFill($sheet, $estadoCol . $row, self::COLOR_RESERVADO, true);
        }
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param array<string, mixed> $item
     * @param bool $applyBorder
     */
    private function writeRecibirRow(Worksheet $sheet, $startCol, $row, array $item, $applyBorder = true)
    {
        $values = [
            $item['cons'],
            $item['vendedor'],
            $item['cliente'],
            $this->formatNumber($item['cbm_recibir']),
            $this->formatShortDate($item['fecha_recibir']),
            $item['code_supplier'],
            $item['ultima_actualizacion'] ?? '',
        ];

        $this->writeRowValues($sheet, $startCol, $row, $values, $applyBorder);
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param array<string, mixed> $item
     * @param bool $applyBorder
     */
    private function writeContactarRow(Worksheet $sheet, $startCol, $row, array $item, $applyBorder = true)
    {
        $values = [
            $item['cons'],
            $item['vendedor'],
            $item['cliente'],
            $this->formatNumber($item['cbm_contactar']),
            $item['code_supplier'],
            $item['note'] ?? '',
        ];

        $this->writeRowValues($sheet, $startCol, $row, $values, $applyBorder);
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param string $carga
     * @param array<int, array<string, mixed>> $items
     * @param string|null $footerNote
     * @return int
     */
    private function writeContactarFooter(Worksheet $sheet, $startCol, $row, $carga, array $items, $footerNote = null)
    {
        if (empty($items)) {
            return $row;
        }

        $totalCbm = 0.0;
        foreach ($items as $item) {
            $totalCbm += (float) ($item['cbm_contactar'] ?? 0);
        }

        $this->writeTableTotalRow(
            $sheet,
            $startCol,
            $row,
            self::TABLE_WIDTH_CONTACTAR,
            self::COLOR_CONTACTAR,
            'TOTAL POR CONTACTAR - CONS #' . $carga,
            3,
            $this->formatNumber($totalCbm)
        );

        $start = Coordinate::stringFromColumnIndex($startCol);
        $end = Coordinate::stringFromColumnIndex($startCol + self::TABLE_WIDTH_CONTACTAR - 1);
        $noteRow = $row + 1;
        $noteRange = $start . $noteRow . ':' . $end . $noteRow;
        $sheet->mergeCells($noteRange);
        $sheet->setCellValue($start . $noteRow, $footerNote ?: '*AQUI SOLO SALE PROVEEDORES EN DATOS PROVEEDOR SIN FECHA DE LLEGADA*');
        $sheet->getStyle($noteRange)->getFont()->getColor()->setARGB('FFFF0000');
        $sheet->getStyle($noteRange)->getFont()->setBold(true);
        $sheet->getStyle($noteRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return $noteRow + 2;
    }

    /**
     * Histórico CONTACTAR congelado (arriba en columna T–Y, más antiguo primero).
     *
     * @param Worksheet $sheet
     * @param int $idContenedor
     * @param int $row
     * @param string $carga
     * @param array<int, array<string, mixed>> $rows
     * @return int
     */
    private function writeContactarHistorico(Worksheet $sheet, $idContenedor, $row, $carga, array $rows)
    {
        $bloques = $this->resolveContactarPeriodosCerrados($idContenedor, $carga, $rows);

        if (empty($bloques)) {
            return $row;
        }

        $this->log('info', 'Hoja Seguimiento: escribiendo histórico CONTACTAR congelado', [
            'id_contenedor' => (int) $idContenedor,
            'bloques' => count($bloques),
        ]);

        foreach ($bloques as $bloque) {
            $items = $bloque['items'];
            $this->applyManualContactarNotesToItems($idContenedor, $items);

            $row = $this->writeContactarPeriodBlock(
                $sheet,
                $row,
                $carga,
                $bloque['inicio'],
                $bloque['fin'],
                $items
            );
        }

        return $row;
    }

    /**
     * Periodo CONTACTAR abierto (al final del histórico, misma columna T–Y).
     *
     * @param Worksheet $sheet
     * @param int $row
     * @param string $carga
     * @param array{inicio: Carbon, fin: Carbon, hora: string, timezone: string} $periodoAbierto
     * @param array<int, array<string, mixed>> $items
     * @return int
     */
    private function writeContactarAbiertoBlock(Worksheet $sheet, $row, $carga, array $periodoAbierto, array $items)
    {
        $title = 'CARGA POR CONTACTAR ('
            . $periodoAbierto['inicio']->format('d/m/Y H:i')
            . ' → ahora)';

        $this->writeTableTitle($sheet, self::COL_CONTACTAR, $row, $title, self::COLOR_CONTACTAR, self::TABLE_WIDTH_CONTACTAR);
        $row++;

        $headerRow = $row;
        $this->writeTableHeaders($sheet, self::COL_CONTACTAR, $row, self::HEADERS_CONTACTAR, self::COLOR_CONTACTAR);
        $row++;

        if (empty($items)) {
            $this->applyBorders($sheet, $this->tableRange(self::COL_CONTACTAR, $headerRow, $headerRow, self::TABLE_WIDTH_CONTACTAR));

            return $row;
        }

        $footerRow = $this->writeTableDataSection(
            $sheet,
            self::COL_CONTACTAR,
            $headerRow,
            $row,
            self::TABLE_WIDTH_CONTACTAR,
            $items,
            [$this, 'writeContactarRow']
        );

        return $this->writeContactarFooter(
            $sheet,
            self::COL_CONTACTAR,
            $footerRow,
            $carga,
            $items,
            '*Periodo abierto: DATOS PROVEEDOR sin fecha, ingresados desde el último corte*'
        );
    }
    /**
     * @param int $idContenedor
     * @param string $carga
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{inicio: Carbon, fin: Carbon, items: array<int, array<string, mixed>>}>
     */
    private function resolveContactarPeriodosCerrados($idContenedor, $carga, array $rows)
    {
        if (!SeguimientoConsolidadoCorteConfig::contenedorTieneHistoricoContactar($idContenedor)) {
            return [];
        }

        $settings = SeguimientoConsolidadoCorteConfig::settings();
        $timezone = $settings['timezone'];
        $bloques = [];
        $finRegistrados = [];

        $rowsByProveedor = [];
        foreach ($rows as $item) {
            $rowsByProveedor[(int) $item['id_proveedor']] = $item;
        }

        if (Schema::hasTable('contenedor_seguimiento_corte_periodos')) {
            $periodos = DB::table('contenedor_seguimiento_corte_periodos')
                ->where('id_contenedor', $idContenedor)
                ->orderBy('periodo_fin', 'asc')
                ->get();

            foreach ($periodos as $periodo) {
                $inicio = Carbon::parse($periodo->periodo_inicio, $timezone)->timezone($timezone);
                $fin = Carbon::parse($periodo->periodo_fin, $timezone)->timezone($timezone);
                $finRegistrados[$fin->format('Y-m-d H:i:s')] = true;

                $items = $this->buildContactarItemsFromCorte((int) $periodo->id, $rowsByProveedor, $carga);
                if (empty($items)) {
                    continue;
                }

                $bloques[] = [
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'items' => $items,
                ];
            }
        }

        $cerradoFin = SeguimientoConsolidadoCorteConfig::ultimoPeriodoCerradoFin();
        if ($cerradoFin !== null) {
            $finKey = $cerradoFin->format('Y-m-d H:i:s');
            if (!isset($finRegistrados[$finKey])) {
                $periodo = SeguimientoConsolidadoCorteConfig::periodoCorteJob();
                $items = $this->filterContactarPeriodoCerrado($rows, $periodo['inicio'], $periodo['fin']);
                if (!empty($items)) {
                    $bloques[] = [
                        'inicio' => $periodo['inicio'],
                        'fin' => $periodo['fin'],
                        'items' => $items,
                    ];
                }
            }
        }

        return $bloques;
    }

    /**
     * @param int $idCorte
     * @param array<int, array<string, mixed>> $rowsByProveedor
     * @param string $carga
     * @return array<int, array<string, mixed>>
     */
    private function buildContactarItemsFromCorte($idCorte, array $rowsByProveedor, $carga)
    {
        if (!Schema::hasTable('contenedor_seguimiento_corte_clientes')) {
            return [];
        }

        $clientes = DB::table('contenedor_seguimiento_corte_clientes')
            ->where('id_corte', $idCorte)
            ->orderBy('fecha_cambio', 'asc')
            ->get();

        $items = [];
        foreach ($clientes as $cliente) {
            $base = $rowsByProveedor[(int) $cliente->id_proveedor] ?? null;
            $items[] = $base ?: [
                'cons' => $carga,
                'vendedor' => '',
                'cliente' => $cliente->nombre_cliente,
                'cbm_contactar' => 0,
                'code_supplier' => $cliente->code_supplier,
                'note' => '',
            ];
        }

        return $items;
    }

    /**
     * @param Worksheet $sheet
     * @param int $row
     * @param string $carga
     * @param Carbon $inicio
     * @param Carbon $fin
     * @param array<int, array<string, mixed>> $items
     * @return int
     */
    private function writeContactarPeriodBlock(Worksheet $sheet, $row, $carga, Carbon $inicio, Carbon $fin, array $items)
    {
        $title = 'CARGA POR CONTACTAR ('
            . $inicio->format('d/m/Y H:i')
            . ' → '
            . $fin->format('d/m/Y H:i')
            . ')';

        $this->writeTableTitle($sheet, self::COL_CONTACTAR, $row, $title, self::COLOR_CONTACTAR, self::TABLE_WIDTH_CONTACTAR);
        $row++;

        $headerRow = $row;
        $this->writeTableHeaders($sheet, self::COL_CONTACTAR, $row, self::HEADERS_CONTACTAR, self::COLOR_CONTACTAR);
        $row++;

        $dataStartRow = $row;
        $footerRow = $this->writeTableDataSection(
            $sheet,
            self::COL_CONTACTAR,
            $headerRow,
            $dataStartRow,
            self::TABLE_WIDTH_CONTACTAR,
            $items,
            [$this, 'writeContactarRow']
        );

        return $this->writeContactarFooter(
            $sheet,
            self::COL_CONTACTAR,
            $footerRow,
            $carga,
            $items,
            '*Periodo congelado al corte; los nuevos ingresos van a la tabla de abajo*'
        );
    }

    /**
     * @param int $idContenedor
     * @param string $carga
     * @return array<int, array<string, mixed>>
     */
    private function fetchProveedoresSeguimiento($idContenedor, $carga)
    {
        $rows = DB::table('contenedor_consolidado_cotizacion_proveedores as P')
            ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'P.id_cotizacion')
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'C.id_usuario')
            ->where('P.id_contenedor', $idContenedor)
            ->whereNull('C.deleted_at')
            ->whereNull('C.id_cliente_importacion')
            ->where('C.estado_cotizador', 'CONFIRMADO')
            ->select([
                'C.id as id_cotizacion',
                'P.id as id_proveedor',
                'P.code_supplier',
                'P.estados as estado_coordinacion',
                'P.estados_proveedor as estado_china',
                'P.cbm_total',
                'P.cbm_total_china',
                'P.arrive_date',
                'P.arrive_date_china',
                'C.nombre as cliente',
                'C.estado_cliente as estado_pago',
                'U.No_Nombres_Apellidos as vendedor',
            ])
            ->orderBy('C.id', 'asc')
            ->orderBy('P.id', 'asc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $cbmChina = is_numeric($row->cbm_total_china) ? (float) $row->cbm_total_china : 0.0;
            $cbmPeru = is_numeric($row->cbm_total) ? (float) $row->cbm_total : 0.0;

            $result[] = [
                'id_cotizacion' => (int) $row->id_cotizacion,
                'id_proveedor' => (int) $row->id_proveedor,
                'cons' => $carga,
                'vendedor' => $row->vendedor,
                'cliente' => $row->cliente,
                'code_supplier' => $row->code_supplier,
                'estado_coordinacion' => $row->estado_coordinacion,
                'estado_china' => $row->estado_china,
                'estado_pago' => $row->estado_pago,
                'cbm_yiwu' => $cbmChina,
                'cbm_recibir' => $cbmPeru > 0 ? $cbmPeru : $cbmChina,
                'cbm_contactar' => $cbmPeru > 0 ? $cbmPeru : $cbmChina,
                'fecha_llegada_peru' => $row->arrive_date,
                'fecha_llegada_china' => $row->arrive_date_china,
                'fecha_recibir' => $row->arrive_date ?: $row->arrive_date_china,
                'note' => '',
            ];
        }

        return $result;
    }

    /**
     * @param Worksheet $sheet
     * @param int $startCol
     * @param int $row
     * @param array<int, mixed> $values
     */
    private function writeRowValues(Worksheet $sheet, $startCol, $row, array $values, $applyBorder = true)
    {
        foreach ($values as $index => $value) {
            $col = Coordinate::stringFromColumnIndex($startCol + $index);
            $sheet->setCellValue($col . $row, $value);
        }

        if ($applyBorder) {
            $start = Coordinate::stringFromColumnIndex($startCol);
            $end = Coordinate::stringFromColumnIndex($startCol + count($values) - 1);
            $this->applyBorders($sheet, $start . $row . ':' . $end . $row);
        }
    }

    /**
     * @param Worksheet $sheet
     * @param string $range
     * @param string $color
     * @param bool $bold
     */
    private function applyFill(Worksheet $sheet, $range, $color, $bold = false)
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB($color);

        if ($bold) {
            $sheet->getStyle($range)->getFont()->setBold(true);
        }
    }

    /**
     * @param Worksheet $sheet
     * @param string $range
     */
    private function applyBorders(Worksheet $sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }

    /**
     * @param mixed $value
     * @return string|float
     */
    private function formatNumber($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return round((float) $value, 2);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function formatShortDate($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            return SeguimientoConsolidadoDateFormatter::formatCalendarDate($value);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $groups
     */
    private function applyManualYiwuNotes(int $idContenedor, array &$groups): void
    {
        if (!isset($groups['yiwu']) || !is_array($groups['yiwu'])) {
            return;
        }

        $this->applyManualYiwuNotesToItems($idContenedor, $groups['yiwu']);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function applyManualYiwuNotesToItems(int $idContenedor, array &$items): void
    {
        $manualNotes = app(SeguimientoConsolidadoDriveCellRepository::class)
            ->manualValuesByColumn($idContenedor, 'Seguimiento', 'yiwu_notas');

        if ($manualNotes === []) {
            return;
        }

        foreach ($items as $index => $item) {
            $idProveedor = (int) ($item['id_proveedor'] ?? 0);
            if ($idProveedor <= 0) {
                continue;
            }

            $rowKey = SeguimientoDriveCellRowKey::yiwuProveedor($idProveedor);
            if (isset($manualNotes[$rowKey])) {
                $items[$index]['notas'] = $manualNotes[$rowKey];
            }
        }
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $groups
     */
    private function applyManualContactarNotes(int $idContenedor, array &$groups): void
    {
        if (!isset($groups['contactar']) || !is_array($groups['contactar'])) {
            return;
        }

        $this->applyManualContactarNotesToItems($idContenedor, $groups['contactar']);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function applyManualContactarNotesToItems(int $idContenedor, array &$items): void
    {
        $manualNotes = app(SeguimientoConsolidadoDriveCellRepository::class)
            ->manualValuesByColumn($idContenedor, 'Seguimiento', 'note');

        if ($manualNotes === []) {
            return;
        }

        foreach ($items as $index => $item) {
            $idProveedor = (int) ($item['id_proveedor'] ?? 0);
            if ($idProveedor <= 0) {
                continue;
            }

            $rowKey = SeguimientoDriveCellRowKey::contactarProveedor($idProveedor);
            if (isset($manualNotes[$rowKey])) {
                $items[$index]['note'] = $manualNotes[$rowKey];
            }
        }
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function log($level, $message, array $context = [])
    {
        Log::log($level, self::LOG_PREFIX . ' ' . $message, $context);
    }
}
