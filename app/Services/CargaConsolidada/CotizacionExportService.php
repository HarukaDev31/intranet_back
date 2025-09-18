<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Facades\DB;

class CotizacionExportService
{
    protected $cotizacionService;
    public function __construct(CotizacionService $cotizacionService)
    {
        $this->cotizacionService = $cotizacionService;
    }

    //Exportar a Excel
    public function exportarCotizacion(Request $request, $query = null)
    {
        try{
            // Obtener datos filtrados
            $datosExport = $this->obtenerDatosParaExportar($request, $query);

            //Crea el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();


            //Configura los encabezados
            $this->configurarEncabezados($sheet);

            //Llena los datos
            $info = $this->llenarDatosExcel($sheet, $datosExport);

            //Aplica formato y estilos
            $this->aplicarFormatoExcel($sheet, $info);

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
    private function obtenerDatosParaExportar(Request $request, $id)
    {

        // Filtrar por contenedor
        $query = Cotizacion::where('id_contenedor', $id);

        //usar volumen_china de la suma total de los cbm_total_china de los proovedores con el mismo id_cotizacion de la tabla contenedor_consolidado_cotizacion_proveedores
    $query->selectRaw('*, (SELECT SUM(cbm_total_china) FROM contenedor_consolidado_cotizacion_proveedores WHERE id_cotizacion = contenedor_consolidado_cotizacion.id) as volumen_chinaa');

        ////if request has estado_coordinacion or estado_china  then query with  proveedores  and just get cotizaciones with at least one proveedor with the state
        if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
                $query->whereHas('proveedores', function ($query) use ($request) {
                    $query->where('estados', $request->estado_coordinacion)
                        ->orWhere('estados_proveedor', $request->estado_china);
                });
        }

        $query->whereNull('id_cliente_importacion');
        
        //obtener datos de la tabla carga_consolidado_contenedor
        $contenedor = Contenedor::find($id);

        //obtener asesores: construimos un mapa cotizacion_id => nombre_asesor
        $asesoresQuery = DB::table('contenedor_consolidado_cotizacion AS main')
            ->select(['main.id as cotizacion_id', 'U.No_Nombres_Apellidos'])
            ->leftJoin('usuario AS U', 'U.ID_Usuario', '=', 'main.id_usuario')
            ->where('main.id_contenedor', $id)
            ->whereNull('id_cliente_importacion');

        if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
            $asesoresQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                    ->whereRaw('proveedores.id_cotizacion = main.id')
                    ->where(function ($q) use ($request) {
                        if ($request->has('estado_coordinacion') && $request->estado_coordinacion != 'todos') {
                            $q->where('proveedores.estados', $request->estado_coordinacion);
                        }
                        if ($request->has('estado_china') && $request->estado_china != 'todos') {
                            $q->where('proveedores.estados_proveedor', $request->estado_china);
                        }
                    });
            });
        }
        $asesoresResults = $asesoresQuery->get();
        $asesoresMap = [];
        foreach ($asesoresResults as $a) {
            $asesoresMap[$a->cotizacion_id] = $a->No_Nombres_Apellidos ?? '';
        }
        //obtener tipo cliente: se obtiene de la relacion a la tabla contenedor_consolidado_tipo_cliente a travez del id_tipo_cliente en la tabla contenedor_consolidado_cotizacion
        if ($request->has('tipo_cliente') && $request->tipo_cliente != 'todos') {
            $query->whereHas('tipoCliente', function ($q) use ($request) {
                $q->where('id', $request->tipo_cliente);
            });
        }

        // Ordenamiento
        $sortField = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Cargar relaciones necesarias
        $cotizaciones = $query->get();

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
                'estado' => $cotizacion->estado_cotizador ?? 'PENDIENTE',
                'cotizacion' => $cotizacion->cotizacion_file_url ?? ''
            ];
        }

        return $datosExport;
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
            'H3' => 'Fecha Modificación',
            'I3' => 'Nombre Cliente',
            'J3' => 'DNI/RUC',
            'K3' => 'Correo',
            'L3' => 'Whatsapp',
            'M3' => 'Tipo Cliente',
            'N3' => 'Volumen',
            'O3' => 'Volumen China',
            'P3' => 'Qty Item',
            'Q3' => 'FOB',
            'R3' => 'Logistica',
            'S3' => 'Impuesto',
            'T3' => 'Tarifa',
            'U3' => 'Estado',
            'V3' => 'Cotización',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Estilos para el título
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(16);        $sheet->getStyle('B2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Estilos para los encabezados de columna
        $sheet->getStyle('B3:V3')->getFont()->setBold(true);
        $sheet->getStyle('B3:V3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('B3:V3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B3:V3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
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
            $sheet->setCellValue('H' . $row, $this->safeFormatDate($data['updated_at'] ?? null));

            $sheet->setCellValue('I' . $row, $data['nombre_cliente'] ?? '');
            $sheet->setCellValue('J' . $row, $data['dni_ruc'] ?? '');
            $sheet->setCellValue('K' . $row, $data['correo'] ?? '');
            $sheet->setCellValue('L' . $row, $data['whatsapp'] ?? '');
            $sheet->setCellValue('M' . $row, $data['tipo_cliente'] ?? '');
            $sheet->setCellValue('N' . $row, $data['volumen'] ?? '');
            $sheet->setCellValue('O' . $row, $data['volumen_china'] ?? '');
            $sheet->setCellValue('P' . $row, $data['qty_item'] ?? '');
            $sheet->setCellValue('Q' . $row, $data['fob'] ?? '');
            $sheet->setCellValue('R' . $row, $data['logistica'] ?? '');
            $sheet->setCellValue('S' . $row, $data['impuesto'] ?? '');
            $sheet->setCellValue('T' . $row, $data['tarifa'] ?? '');
            $sheet->setCellValue('U' . $row, $data['estado'] ?? '');
            $sheet->setCellValue('V' . $row, $data['cotizacion'] ?? '');

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
    private function aplicarFormatoExcel($sheet, $info)
    {
        $lastRow = $info['lastRow'];
        $totalRows = $info['totalRows'];

        //Unir celdas para el título
        $sheet->mergeCells("B2:V2");



        //Configurar ancho de columnas
        $columnWidths = [
            'B' => 20,
            'C' => 15,
            'D' => 15,
            'E' => 20,
            'F' => 10,
            'G' => 15,
            'H' => 20,
            'I' => 30,
            'J' => 20,
            'K' => 25,
            'L' => 15,
            'M' => 15,
            'N' => 10,
            'O' => 15,
            'P' => 10,
            'Q' => 15,
            'R' => 15,
            'S' => 10,
            'T' => 10,
            'U' => 18,
            'V' => 25,
        ];

        //Aplicar los anchos de columna
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Bordes para todo el rango de datos
        $sheet->getStyle("B3:V{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Alineación para todo el rango de datos
        $sheet->getStyle("B3:V{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B3:V{$lastRow}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B3:V{$lastRow}")->getAlignment()->setWrapText(true);

        // Formato de fecha para las columnas de fecha
        $sheet->getStyle("G4:G{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle("H4:H{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        // Ajuste automático de ancho de columnas
        foreach (range('B', 'V') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }
}