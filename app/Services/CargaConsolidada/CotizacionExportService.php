<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Facades\DB;
use App\Traits\FileTrait;

class CotizacionExportService
{
    use FileTrait;

    private const COLOR_HEADER_GREY = 'FFD9D9D9';
    private const COLOR_HEADER_GREEN = 'FF92D050';
    private const COLOR_HEADER_ORANGE = 'FFFFC000';

    protected $cotizacionService;
    public function __construct(CotizacionService $cotizacionService)
    {
        $this->cotizacionService = $cotizacionService;
    }

    /**
     * Construye el spreadsheet de cotizaciones (hoja activa) sin descargar.
     *
     * @param Request $request
     * @param int|string $idContenedor
     * @return Spreadsheet
     */
    public function buildCotizacionesSpreadsheet(Request $request, $idContenedor, $lightweight = false)
    {
        $startedAt = microtime(true);
        $datosExport = $this->obtenerDatosParaExportar($request, $idContenedor, $lightweight);
        Log::info('[SeguimientoDrive] Hoja Cotizaciones: datos obtenidos', [
            'id_contenedor' => (int) $idContenedor,
            'filas' => count($datosExport),
            'ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cotizaciones');

        if ($lightweight) {
            $fillStartedAt = microtime(true);
            $this->configurarEncabezadosSeguimiento($sheet);
            $info = $this->llenarDatosExcelSeguimiento($sheet, $datosExport);
            Log::info('[SeguimientoDrive] Hoja Cotizaciones: celdas escritas', [
                'id_contenedor' => (int) $idContenedor,
                'ms' => (int) round((microtime(true) - $fillStartedAt) * 1000),
            ]);

            $formatStartedAt = microtime(true);
            $this->aplicarFormatoExcelSeguimiento($sheet, $info);
            Log::info('[SeguimientoDrive] Hoja Cotizaciones: formato aplicado', [
                'id_contenedor' => (int) $idContenedor,
                'ms' => (int) round((microtime(true) - $formatStartedAt) * 1000),
                'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $spreadsheet;
        }

        $this->configurarEncabezados($sheet);

        $fillStartedAt = microtime(true);
        $info = $this->llenarDatosExcel($sheet, $datosExport);
        Log::info('[SeguimientoDrive] Hoja Cotizaciones: celdas escritas', [
            'id_contenedor' => (int) $idContenedor,
            'ms' => (int) round((microtime(true) - $fillStartedAt) * 1000),
        ]);

        $formatStartedAt = microtime(true);
        $this->aplicarFormatoExcel($sheet, $info, $lightweight);
        Log::info('[SeguimientoDrive] Hoja Cotizaciones: formato aplicado', [
            'id_contenedor' => (int) $idContenedor,
            'ms' => (int) round((microtime(true) - $formatStartedAt) * 1000),
            'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $spreadsheet;
    }

    /**
     * Devuelve los mismos datos que alimentan la exportación Excel, en formato array.
     * Ruta optimizada para el endpoint público JSON (1 query, URLs CDN sin S3).
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDatosCotizacionJson(Request $request, $idContenedor): array
    {
        return $this->obtenerDatosParaExportarPublico($request, $idContenedor);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerDatosParaExportarPublico(Request $request, $idContenedor): array
    {
        $contenedor = Contenedor::find($idContenedor);
        $carga = $contenedor->carga ?? '';
        $fechaCierre = ($contenedor && $contenedor->f_cierre)
            ? Carbon::parse($contenedor->f_cierre)->format('d/m/Y')
            : '';

        $volumenChinaSub = DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->select('id_cotizacion', DB::raw('SUM(cbm_total_china) as volumen_chinaa'))
            ->groupBy('id_cotizacion');

        $query = DB::table('contenedor_consolidado_cotizacion as C')
            ->leftJoin('reason_delete_cotizacion as rdc', 'rdc.id', '=', 'C.deleted_reason_id')
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'C.id_usuario')
            ->leftJoin('contenedor_consolidado_tipo_cliente as tc', 'tc.id', '=', 'C.id_tipo_cliente')
            ->leftJoinSub($volumenChinaSub, 'vchina', function ($join) {
                $join->on('vchina.id_cotizacion', '=', 'C.id');
            })
            ->where('C.id_contenedor', $idContenedor)
            ->whereNull('C.id_cliente_importacion');

        if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores as P')
                    ->whereColumn('P.id_cotizacion', 'C.id')
                    ->where(function ($q) use ($request) {
                        if ($request->has('estado_coordinacion')) {
                            $q->where('P.estados', $request->estado_coordinacion);
                        }
                        if ($request->has('estado_china')) {
                            $q->orWhere('P.estados_proveedor', $request->estado_china);
                        }
                    });
            });
        }

        if ($request->has('tipo_cliente') && $request->tipo_cliente != 'todos') {
            $query->where('C.id_tipo_cliente', $request->tipo_cliente);
        }

        $allowedSort = ['id', 'fecha', 'nombre', 'volumen', 'updated_at', 'estado_cotizador'];
        $sortField = $request->input('sort_by', 'id');
        if (! in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }
        $sortOrder = strtolower($request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy('C.' . $sortField, $sortOrder);

        $rows = $query->select([
            'C.id',
            'C.fecha',
            'C.fecha_confirmacion',
            'C.deleted_at',
            'C.updated_at',
            'C.nombre',
            'C.documento',
            'C.correo',
            'C.telefono',
            'C.volumen',
            'C.qty_item',
            'C.fob',
            'C.monto',
            'C.impuestos',
            'C.tarifa',
            'C.cotizacion_file_url',
            'C.estado_cotizador',
            'rdc.name as razon_de_baja',
            'U.No_Nombres_Apellidos as asesor',
            'tc.name as tipo_cliente',
            'vchina.volumen_chinaa',
        ])->get();

        $datosExport = [];
        $index = 1;

        foreach ($rows as $cotizacion) {
            $datosExport[] = [
                'n' => $index++,
                'carga' => $carga,
                'fecha_cierre' => $fechaCierre,
                'asesor' => $cotizacion->asesor ?? '',
                'cod' => $this->buildCod($contenedor, $cotizacion),
                'created_at' => $cotizacion->fecha ?? null,
                'fecha_de_confirmacion' => ($cotizacion->fecha && $cotizacion->fecha_confirmacion && $cotizacion->fecha < $cotizacion->fecha_confirmacion)
                    ? $cotizacion->fecha_confirmacion
                    : $cotizacion->fecha,
                'fecha_de_baja' => $cotizacion->deleted_at ?? null,
                'razon_de_baja' => $cotizacion->razon_de_baja ?? '',
                'updated_at' => $cotizacion->updated_at ?? null,
                'nombre_cliente' => $cotizacion->nombre ?? '',
                'dni_ruc' => $cotizacion->documento ?? 'Sin documento',
                'correo' => $cotizacion->correo ?? 'Sin correo',
                'whatsapp' => $cotizacion->telefono ?? '',
                'tipo_cliente' => $cotizacion->tipo_cliente ?? '',
                'volumen' => $cotizacion->volumen ?? '',
                'volumen_china' => $cotizacion->volumen_chinaa ?? '0',
                'qty_item' => $cotizacion->qty_item ?? '',
                'fob' => $cotizacion->fob ?? '',
                'logistica' => $cotizacion->monto ?? '',
                'impuesto' => $cotizacion->impuestos ?? '',
                'tarifa' => $cotizacion->tarifa ?? '',
                'cotizacion' => $this->buildCotizacionCdnUrl($cotizacion->cotizacion_file_url ?? ''),
                'estado' => $cotizacion->estado_cotizador ?? 'PENDIENTE',
            ];
        }

        return $datosExport;
    }

    /**
     * Aplica los mismos filtros que la tabla de prospectos (CotizacionController@index).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     */
    private function applyProspectosTableFilters($query, Request $request)
    {
        $query->where(function ($q) {
            $q->whereDoesntHave('calculadoraImportacion')
                ->orWhereHas('calculadoraImportacion', function ($sub) {
                    $sub->where('estado', '!=', 'PENDIENTE');
                });
        });

        if ($request->filled('idCotizacion')) {
            $query->where('contenedor_consolidado_cotizacion.id', $request->idCotizacion);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contenedor_consolidado_cotizacion.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('contenedor_consolidado_cotizacion.documento', 'LIKE', "%{$search}%");

                if (preg_match('/^[\d\s\-\(\)\.\+]+$/', $search)) {
                    $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $search);

                    if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $matches)) {
                        $telefonoNormalizado = $matches[1];
                    }

                    if (! empty($telefonoNormalizado)) {
                        $q->orWhere(function ($subQuery) use ($telefonoNormalizado, $search) {
                            $subQuery->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contenedor_consolidado_cotizacion.telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"])
                                ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contenedor_consolidado_cotizacion.telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%51{$telefonoNormalizado}%"])
                                ->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
                        });
                    }
                } else {
                    $q->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
                }
            });
        }

        if ($request->filled('estado')) {
            $query->where('contenedor_consolidado_cotizacion.estado', $request->estado);
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('contenedor_consolidado_cotizacion.fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('contenedor_consolidado_cotizacion.fecha', '<=', $request->fecha_fin);
        }

        $query->whereHas('proveedores');

        if ($request->filled('estado_coordinacion') && $request->estado_coordinacion !== 'todos') {
            $query->whereHas('proveedores', function ($sub) use ($request) {
                $sub->where('estados', $request->estado_coordinacion);
            });
        }

        if ($request->filled('estado_china') && $request->estado_china !== 'todos') {
            $query->whereHas('proveedores', function ($sub) use ($request) {
                $sub->where('estados_proveedor', $request->estado_china);
            });
        }

        if ($request->filled('estado_cotizador') && $request->estado_cotizador !== 'todos') {
            $query->where('contenedor_consolidado_cotizacion.estado_cotizador', $request->estado_cotizador);
        }

        if ($request->filled('tipo_cliente') && $request->tipo_cliente !== 'todos') {
            $query->whereHas('tipoCliente', function ($q) use ($request) {
                $q->where('id', $request->tipo_cliente);
            });
        }
    }

    //Exportar a Excel
    public function exportarCotizacion(Request $request, $query = null)
    {
        try{
            $spreadsheet = $this->buildCotizacionesSpreadsheet($request, $query);

            //Genera el archivo Excel
            return $this->generarDescargaExcel($spreadsheet);

        }catch (\Throwable $e) {
            Log::error('Error exportarCotizacion: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar export: ' . $e->getMessage()
            ], 500);
        }
    }

    //Obtiene los datos filtrados para la exportación
    private function obtenerDatosParaExportar(Request $request, $id, $lightweight = false)
    {
        if ($lightweight) {
            return $this->obtenerFilasSeguimientoHoja1($request, $id);
        }

        // Filtrar por contenedor (mismos criterios que la tabla de prospectos en front)
        $query = Cotizacion::query()
            ->where('contenedor_consolidado_cotizacion.id_contenedor', $id)
            ->leftJoin('reason_delete_cotizacion as rdc', 'rdc.id', '=', 'contenedor_consolidado_cotizacion.deleted_reason_id');

        //usar volumen_china de la suma total de los cbm_total_china de los proovedores con el mismo id_cotizacion de la tabla contenedor_consolidado_cotizacion_proveedores
        $query->selectRaw('contenedor_consolidado_cotizacion.*, rdc.name as razon_de_baja, (SELECT SUM(cbm_total_china) FROM contenedor_consolidado_cotizacion_proveedores WHERE id_cotizacion = contenedor_consolidado_cotizacion.id) as volumen_chinaa');

        $this->applyProspectosTableFilters($query, $request);

        $query->whereNull('id_cliente_importacion');
        
        //obtener datos de la tabla carga_consolidado_contenedor
        $contenedor = Contenedor::find($id);

        //obtener asesores: construimos un mapa cotizacion_id => nombre_asesor
        $asesoresQuery = DB::table('contenedor_consolidado_cotizacion AS main')
            ->select(['main.id as cotizacion_id', 'U.No_Nombres_Apellidos'])
            ->leftJoin('usuario AS U', 'U.ID_Usuario', '=', 'main.id_usuario')
            ->where('main.id_contenedor', $id)
            ->whereNull('id_cliente_importacion');

        if ($request->filled('estado_coordinacion') && $request->estado_coordinacion !== 'todos') {
            $asesoresQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                    ->whereRaw('proveedores.id_cotizacion = main.id')
                    ->where('proveedores.estados', $request->estado_coordinacion);
            });
        }
        if ($request->filled('estado_china') && $request->estado_china !== 'todos') {
            $asesoresQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                    ->whereRaw('proveedores.id_cotizacion = main.id')
                    ->where('proveedores.estados_proveedor', $request->estado_china);
            });
        }
        $asesoresResults = $asesoresQuery->get();
        $asesoresMap = [];
        foreach ($asesoresResults as $a) {
            $asesoresMap[$a->cotizacion_id] = $a->No_Nombres_Apellidos ?? '';
        }

        // Ordenamiento (mismo criterio que la tabla en front)
        $allowedSort = ['id', 'fecha', 'nombre', 'volumen', 'updated_at', 'estado_cotizador'];
        $sortField = $request->input('sort_by', 'id');
        if (! in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }
        $sortOrder = strtolower($request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy('contenedor_consolidado_cotizacion.' . $sortField, $sortOrder);

        // Cargar relaciones necesarias (evita N+1 en tipoCliente)
        $cotizaciones = $query->with('tipoCliente')->get();

        $datosExport = [];
        $index = 1;
        foreach ($cotizaciones as $cotizacion) {
            $datosExport[] = [
                'n' => $index++, 
                'carga' => $contenedor->carga ?? '',
                'fecha_cierre' => $contenedor->f_cierre ? Carbon::parse($contenedor->f_cierre)->format('d/m/Y') : '',
                'asesor' => $asesoresMap[$cotizacion->id] ?? '',
                // COD construido desde helper
                'cod' => $this->buildCod($contenedor, $cotizacion),
                'created_at' => $cotizacion->fecha ?? null,
                'fecha_de_confirmacion' => $cotizacion->fecha < $cotizacion->fecha_confirmacion ? $cotizacion->fecha_confirmacion : $cotizacion->fecha,
                'fecha_de_baja' => $cotizacion->deleted_at ?? null,
                'razon_de_baja' => $cotizacion->razon_de_baja ?? '',
                'updated_at' => $cotizacion->updated_at ?? null,
                'nombre_cliente' => $cotizacion->nombre ?? '',
                'dni_ruc' => $cotizacion->documento ?? 'Sin documento',
                'correo' => $cotizacion->correo ?? 'Sin correo',
                'whatsapp' => $cotizacion->telefono ?? '',
                'tipo_cliente' => $cotizacion->tipoCliente->name ?? '',
                'volumen' => $cotizacion->volumen ?? '',
                'volumen_china' => $cotizacion->volumen_chinaa ?? '0',
                'qty_item' => $cotizacion->qty_item ?? '',
                'fob' => $cotizacion->fob ?? '',
                'logistica' => $cotizacion->monto ?? '',
                'impuesto' => $cotizacion->impuestos ?? '',
                'tarifa' => $cotizacion->tarifa ?? '',
                'cotizacion' => $lightweight
                    ? $this->buildCotizacionCdnUrl($cotizacion->cotizacion_file_url ?? '')
                    : ($this->generateImageUrl($cotizacion->cotizacion_file_url ?? '') ?? ''),
                'estado' => $cotizacion->estado_cotizador ?? 'PENDIENTE',
            ];
        }

        return $datosExport;
    }

    /**
     * Hoja 1 seguimiento Drive: una fila por proveedor, columnas cliente mergeadas.
     *
     * @param Request $request
     * @param int|string $idContenedor
     * @return array<int, array<string, mixed>>
     */
    private function obtenerFilasSeguimientoHoja1(Request $request, $idContenedor)
    {
        $contenedor = Contenedor::find($idContenedor);
        $carga = $contenedor ? (string) $contenedor->carga : '';

        $query = DB::table('contenedor_consolidado_cotizacion as C')
            ->leftJoin('contenedor_consolidado_cotizacion_proveedores as P', function ($join) {
                $join->on('P.id_cotizacion', '=', 'C.id');
            })
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'C.id_usuario')
            ->where('C.id_contenedor', $idContenedor)
            ->whereNull('C.deleted_at')
            ->whereNull('C.id_cliente_importacion');

        if ($request->has('estado_coordinacion') && $request->estado_coordinacion !== 'todos') {
            $query->where('P.estados', $request->estado_coordinacion);
        }

        if ($request->has('estado_china') && $request->estado_china !== 'todos') {
            $query->where('P.estados_proveedor', $request->estado_china);
        }

        if ($request->has('tipo_cliente') && $request->tipo_cliente !== 'todos') {
            $query->where('C.id_tipo_cliente', $request->tipo_cliente);
        }

        $sortField = $request->input('sort_by', 'C.id');
        $sortOrder = $request->input('sort_order', 'asc');
        if ($sortField === 'id') {
            $sortField = 'C.id';
        }
        $query->orderBy($sortField, $sortOrder)->orderBy('P.id', 'asc');

        $rows = $query->select([
            'C.id as id_cotizacion',
            'P.id as id_proveedor',
            'U.No_Nombres_Apellidos as asesor',
            'C.nombre as nombre_cliente',
            'C.telefono as whatsapp',
            'C.estado_cotizador',
            'P.code_supplier',
            'P.cbm_total as volumen',
            'P.cbm_total_china as volumen_china',
            'P.estados_proveedor as estado_china',
        ])->get();

        $result = [];
        $seenCotizaciones = [];

        foreach ($rows as $row) {
            $idCotizacion = (int) $row->id_cotizacion;
            $seenCotizaciones[$idCotizacion] = true;

            $result[] = $this->buildSeguimientoHoja1Row(
                $carga,
                $row,
                $row->id_proveedor !== null ? $row : null
            );
        }

        // Cotizaciones sin proveedor
        $sinProveedor = DB::table('contenedor_consolidado_cotizacion as C')
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'C.id_usuario')
            ->where('C.id_contenedor', $idContenedor)
            ->whereNull('C.deleted_at')
            ->whereNull('C.id_cliente_importacion')
            ->whereNotIn('C.id', array_keys($seenCotizaciones))
            ->select([
                'C.id as id_cotizacion',
                'U.No_Nombres_Apellidos as asesor',
                'C.nombre as nombre_cliente',
                'C.telefono as whatsapp',
                'C.estado_cotizador',
            ])
            ->orderBy('C.id', 'asc')
            ->get();

        foreach ($sinProveedor as $row) {
            $result[] = $this->buildSeguimientoHoja1Row($carga, $row, null);
        }

        return $result;
    }

    /**
     * @param string $carga
     * @param object $cotizacionRow
     * @param object|null $proveedorRow
     * @return array<string, mixed>
     */
    private function buildSeguimientoHoja1Row($carga, $cotizacionRow, $proveedorRow)
    {
        return [
            'id_cotizacion' => (int) $cotizacionRow->id_cotizacion,
            'carga' => $carga,
            'asesor' => $cotizacionRow->asesor ?? '',
            'nombre_cliente' => $cotizacionRow->nombre_cliente ?? '',
            'whatsapp' => $cotizacionRow->whatsapp ?? '',
            'code_supplier' => $proveedorRow->code_supplier ?? '',
            'volumen' => $proveedorRow !== null && is_numeric($proveedorRow->volumen)
                ? round((float) $proveedorRow->volumen, 2)
                : '',
            'volumen_china' => $proveedorRow !== null && is_numeric($proveedorRow->volumen_china)
                ? round((float) $proveedorRow->volumen_china, 2)
                : '',
            'estado' => $cotizacionRow->estado_cotizador ?? 'PENDIENTE',
            'estado_china' => $proveedorRow !== null ? ($proveedorRow->estado_china ?? '') : '',
            'notas' => '',
        ];
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     */
    private function configurarEncabezadosSeguimiento($sheet)
    {
        $headers = [
            'B1' => ['Carga', self::COLOR_HEADER_GREY],
            'C1' => ['Asesor', self::COLOR_HEADER_GREY],
            'D1' => ['Nombre Cliente', self::COLOR_HEADER_GREY],
            'E1' => ['Whatsapp', self::COLOR_HEADER_GREY],
            'F1' => ['Code supplier', self::COLOR_HEADER_GREEN],
            'G1' => ['Volumen', self::COLOR_HEADER_GREEN],
            'H1' => ['Volumen China', self::COLOR_HEADER_GREY],
            'I1' => ['Estado', self::COLOR_HEADER_ORANGE],
            'J1' => ['Estado China', self::COLOR_HEADER_GREEN],
            'K1' => ['Notas', self::COLOR_HEADER_GREEN],
        ];

        foreach ($headers as $cell => [$label, $color]) {
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($color);
        }
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param array<int, array<string, mixed>> $datosExport
     * @return array<string, mixed>
     */
    private function llenarDatosExcelSeguimiento($sheet, array $datosExport)
    {
        $row = 2;
        $mergeGroups = [];
        $currentCotizacion = null;
        $groupStartRow = 2;

        foreach ($datosExport as $data) {
            $idCotizacion = (int) ($data['id_cotizacion'] ?? 0);

            if ($currentCotizacion !== null && $idCotizacion !== $currentCotizacion) {
                $mergeGroups[] = [
                    'start' => $groupStartRow,
                    'end' => $row - 1,
                ];
                $groupStartRow = $row;
            }

            $currentCotizacion = $idCotizacion;

            $sheet->setCellValue('B' . $row, $data['carga'] ?? '');
            $sheet->setCellValue('C' . $row, $data['asesor'] ?? '');
            $sheet->setCellValue('D' . $row, $data['nombre_cliente'] ?? '');
            $sheet->setCellValue('E' . $row, $data['whatsapp'] ?? '');
            $sheet->setCellValue('F' . $row, $data['code_supplier'] ?? '');
            $sheet->setCellValue('G' . $row, $data['volumen'] ?? '');
            $sheet->setCellValue('H' . $row, $data['volumen_china'] ?? '');
            $sheet->setCellValue('I' . $row, $data['estado'] ?? '');
            $sheet->setCellValue('J' . $row, $data['estado_china'] ?? '');
            $sheet->setCellValue('K' . $row, $data['notas'] ?? '');

            $row++;
        }

        if ($currentCotizacion !== null) {
            $mergeGroups[] = [
                'start' => $groupStartRow,
                'end' => $row - 1,
            ];
        }

        foreach ($mergeGroups as $group) {
            if ($group['end'] <= $group['start']) {
                continue;
            }

            foreach (['B', 'C', 'D', 'E', 'I'] as $col) {
                $range = $col . $group['start'] . ':' . $col . $group['end'];
                $sheet->mergeCells($range);
                $sheet->getStyle($range)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        return [
            'lastRow' => max(1, $row - 1),
            'totalRows' => count($datosExport),
        ];
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param array<string, mixed> $info
     */
    private function aplicarFormatoExcelSeguimiento($sheet, array $info)
    {
        $lastRow = (int) ($info['lastRow'] ?? 1);
        if ($lastRow < 2) {
            return;
        }

        $columnWidths = [
            'B' => 8,
            'C' => 14,
            'D' => 28,
            'E' => 14,
            'F' => 14,
            'G' => 10,
            'H' => 12,
            'I' => 14,
            'J' => 14,
            'K' => 18,
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $dataRange = 'B1:K' . $lastRow;
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('B2:K' . $lastRow)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('B2:E' . $lastRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I2:I' . $lastRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    //Genera el archivo Excel y lo prepara para descarga
    private function generarDescargaExcel($spreadsheet)
    {
        $fileName = 'Reporte_cotizaciones_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);
    }

    //Configura los encabezados del Excel
    private function configurarEncabezados($sheet)
    {
        $headers = [
            'B2' => 'Reporte de Cotizaciones',
            'B3' => 'N',
            'C3' => 'Carga',
            'D3' => 'F. Cierre',
            'E3' => 'Asesor',
            'F3' => 'COD',
            'G3' => 'Fecha',
            'H3' => 'Fecha de Confirmación',
            'I3' => 'Fecha de Baja',
            'J3' => 'Razón de Baja',
            'K3' => 'Fecha Modificación',
            'L3' => 'Nombre Cliente',
            'M3' => 'DNI/RUC',
            'N3' => 'Correo',
            'O3' => 'Whatsapp',
            'P3' => 'Tipo Cliente',
            'Q3' => 'Volumen',
            'R3' => 'Volumen China',
            'S3' => 'Qty Item',
            'T3' => 'FOB',
            'U3' => 'Logistica',
            'V3' => 'Impuesto',
            'W3' => 'Tarifa',
            'X3' => 'Cotización',
            'Y3' => 'Estado',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Estilos para el título
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(16);        $sheet->getStyle('B2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Estilos para los encabezados de columna
        $sheet->getStyle('B3:Y3')->getFont()->setBold(true);
        $sheet->getStyle('B3:Y3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('B3:Y3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B3:Y3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }
    //Llena los datos en el Excel
    private function llenarDatosExcel($sheet, $datosExport)
    {
        $row = 4; // Inicia en la fila 4 después de los encabezados
        $n = 1; // Contador para la columna N

        foreach ($datosExport as $data) {
            $sheet->setCellValue('B' . $row, $data['n'] ?? '');
            $sheet->setCellValue('C' . $row, $data['carga'] ?? '');
            // Fecha cierre formateada como d/m/Y usando helper seguro
            $sheet->setCellValue('D' . $row, $this->safeFormatDate($data['fecha_cierre'] ?? null));
            $sheet->setCellValue('E' . $row, $data['asesor'] ?? '');
            $sheet->setCellValue('F' . $row, $data['cod'] ?? '');

            // Usar helper seguro para crear las fechas en formato d/m/Y
            $sheet->setCellValue('G' . $row, $this->safeFormatDate($data['created_at'] ?? null));
            $sheet->setCellValue('H' . $row, $this->safeFormatDate($data['fecha_de_confirmacion'] ?? null));
            $sheet->setCellValue('I' . $row, $this->safeFormatDate($data['fecha_de_baja'] ?? null));
            $sheet->setCellValue('J' . $row, $data['razon_de_baja'] ?? '');
            $sheet->setCellValue('K' . $row, $this->safeFormatDate($data['updated_at'] ?? null));

            $sheet->setCellValue('L' . $row, $data['nombre_cliente'] ?? '');
            $sheet->setCellValue('M' . $row, $data['dni_ruc'] ?? '');
            $sheet->setCellValue('N' . $row, $data['correo'] ?? '');
            $sheet->setCellValue('O' . $row, $data['whatsapp'] ?? '');
            $sheet->setCellValue('P' . $row, $data['tipo_cliente'] ?? '');
            $sheet->setCellValue('Q' . $row, $data['volumen'] ?? '');
            $sheet->setCellValue('R' . $row, $data['volumen_china'] ?? '');
            $sheet->setCellValue('S' . $row, $data['qty_item'] ?? '');
            $sheet->setCellValue('T' . $row, $data['fob'] ?? '');
            $sheet->setCellValue('U' . $row, $data['logistica'] ?? '');
            $sheet->setCellValue('V' . $row, $data['impuesto'] ?? '');
            $sheet->setCellValue('W' . $row, $data['tarifa'] ?? '');
            $sheet->setCellValue('X' . $row, $data['cotizacion'] ?? '');
            $sheet->setCellValue('Y' . $row, $data['estado'] ?? '');

            $row++;
            $n++;
        }
        return [
            'lastRow' => $row - 1, 
            'totalRows' => count($datosExport)
        ];
    }

    /**
     * Parse a date value safely and return formatted d/m/Y or empty string.
     * Accepts DateTime, Carbon, timestamps, or strings in common formats (Y-m-d, d/m/Y, etc.).
     */
    private function safeFormatDate($value)
    {
        if (empty($value)) {
            return '';
        }

        // If it's already a Carbon/DateTime instance
        if ($value instanceof \DateTime) {
            return Carbon::instance($value)->format('d/m/Y');
        }

        // If numeric timestamp
        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp($value)->format('d/m/Y');
            } catch (\Exception $e) {
                return '';
            }
        }

        // Try known formats, fallback to Carbon::parse inside try/catch
        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
        foreach ($formats as $f) {
            $dt = \DateTime::createFromFormat($f, $value);
            if ($dt && $dt->format($f) === $value) {
                return Carbon::instance($dt)->format('d/m/Y');
            }
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * URL de cotización para Excel en Drive: solo concatena CDN (sin S3 ni generateImageUrl).
     *
     * @param mixed $ruta
     * @return string
     */
    private function buildCotizacionCdnUrl($ruta)
    {
        if ($ruta === null || trim((string) $ruta) === '') {
            return '';
        }

        $ruta = trim((string) $ruta);

        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }

        $path = str_replace('\\', '/', $ruta);
        $path = preg_replace('#^public/#i', '', $path);
        $path = ltrim($path, '/');

        if ($path === '') {
            return '';
        }

        return $this->buildCdnPublicUrl($path);
    }

    /**
     * Construye el COD: carga + fecha (dmy con año de 2 dígitos) + primeras 3 letras del nombre en mayúsculas
     */
    private function buildCod($contenedor, $cotizacion)
    {
        try {
            $carga = $contenedor->carga ?? '';
            $fechaPart = '';
            if (!empty($cotizacion->fecha)) {
                try {
                    $fechaPart = Carbon::parse($cotizacion->fecha)->format('dmy');
                } catch (\Exception $e) {
                    $fechaPart = date('dmy', strtotime($cotizacion->fecha ?? 'now'));
                }
            }
            $nombrePart = strtoupper(substr($cotizacion->nombre ?? '', 0, 3));
            return trim($carga . $fechaPart . $nombrePart);
        } catch (\Exception $e) {
            return $cotizacion->cod ?? '';
        }
    }

    /**
     * Aplica formato y estilos al Excel
     */
    private function aplicarFormatoExcel($sheet, $info, $lightweight = false)
    {
        $lastRow = $info['lastRow'];
        $totalRows = $info['totalRows'];

        //Unir celdas para el título
        $sheet->mergeCells("B2:Y2");



        //Configurar ancho de columnas
        $columnWidths = [
            'B' => 20,
            'C' => 15,
            'D' => 15,
            'E' => 20,
            'F' => 10,
            'G' => 15,
            'H' => 18,
            'I' => 15,
            'J' => 24,
            'K' => 20,
            'L' => 30,
            'M' => 20,
            'N' => 25,
            'O' => 15,
            'P' => 15,
            'Q' => 10,
            'R' => 15,
            'S' => 10,
            'T' => 15,
            'U' => 15,
            'V' => 10,
            'W' => 10,
            'X' => 20,
            'Y' => 20,
        ];

        //Aplicar los anchos de columna
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Bordes para todo el rango de datos
        $sheet->getStyle("B3:Y{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Alineación para todo el rango de datos
        $sheet->getStyle("B3:Y{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B3:Y{$lastRow}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B3:Y{$lastRow}")->getAlignment()->setWrapText(true);

        // Formato de fecha para las columnas de fecha
        $sheet->getStyle("G4:K{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        // Ajuste automático de ancho (muy costoso en PhpSpreadsheet; omitir en sync Drive)
        if (!$lightweight) {
            foreach (range('B', 'Y') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
        }
    }
}