<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Models\Cliente;
use App\Models\CargaConsolidada\Cotizacion as Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Contenedor;

class GeneralExportService
{
    public function exportarClientes(Request $request, $idContenedor = null)
    {
        try{
            if ($idContenedor === null) {
                $idContenedor = $request->input('id_contenedor');
            }
            if (!$idContenedor) {
                throw new \Exception('ID de contenedor no proporcionado');
            }

            // Obtener datos filtrados
            $datosExport = $this->obtenerDatosParaExportar($request, $idContenedor);
            
            // Crear el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            // Configurar encabezados
            $this->configurarEncabezados($sheet);
            
            // Llenar datos y obtener información de dimensiones
            $infoDimensiones = $this->llenarDatosExcel($sheet, $datosExport);
            
            // Aplicar formato y estilos
            $this->aplicarFormatoExcel($sheet, $infoDimensiones);
            
            // Generar y descargar archivo
            return $this->generarDescargaExcel($spreadsheet);
        } catch (\Exception $e) {
            Log::error('Error al exportar clientes: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo generar el archivo.'], 500);
        }
    }
    /**
     * Obtiene los datos filtrados para la exportación
     */
    private function obtenerDatosParaExportar(Request $request, $idContenedor)
    {
        // Contenedor
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            throw new \Exception('Contenedor no encontrado');
        }

        // Consulta base sobre cotizaciones confirmadas y con estado_cliente
        $query = Cotizacion::query()
            ->select([
                'contenedor_consolidado_cotizacion.id',
                'contenedor_consolidado_cotizacion.nombre',
                'contenedor_consolidado_cotizacion.documento',
                'contenedor_consolidado_cotizacion.correo',
                'contenedor_consolidado_cotizacion.telefono',
                'contenedor_consolidado_cotizacion.volumen_china',
                'contenedor_consolidado_cotizacion.volumen',
                'contenedor_consolidado_cotizacion.fob',
                'contenedor_consolidado_cotizacion.monto as logistica',
                'contenedor_consolidado_cotizacion.impuestos',
                'contenedor_consolidado_cotizacion.tarifa',
                'contenedor_consolidado_cotizacion.fecha',
                'contenedor_consolidado_tipo_cliente.name as tipo_cliente'
            ])
            ->leftJoin('contenedor_consolidado_tipo_cliente', 'contenedor_consolidado_tipo_cliente.id', '=', 'contenedor_consolidado_cotizacion.id_tipo_cliente')
            ->where('contenedor_consolidado_cotizacion.id_contenedor', $idContenedor)
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereNotNull('estado_cliente');

        //obtener asesores: construimos un mapa cotizacion_id => nombre_asesor
        $asesoresQuery = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select('CC.id', DB::raw('COALESCE(U.No_Nombres_Apellidos, "") as asesor'))
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'CC.id_usuario')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->whereNull('CC.id_cliente_importacion');
        if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
            $asesoresQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                    ->whereRaw('proveedores.id_cotizacion = CC.id')
                    ->when($request->has('estado_coordinacion') && $request->estado_coordinacion != 'todos', function ($q) use ($request) {
                        $q->where('proveedores.estados', $request->estado_coordinacion);
                    })
                    ->when($request->has('estado_china') && $request->estado_china != 'todos', function ($q) use ($request) {
                        $q->where('proveedores.estados_proveedor', $request->estado_china);
                    });
            });
        }
        $asesoresResults = $asesoresQuery->get();
        $asesoresMap = [];
        foreach ($asesoresResults as $a) {
            $asesoresMap[$a->id] = $a->asesor;
        }

        // Filtros opcionales
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('contenedor_consolidado_cotizacion.nombre', 'LIKE', "%{$search}%")
                  ->orWhere('contenedor_consolidado_cotizacion.documento', 'LIKE', "%{$search}%")
                  ->orWhere('contenedor_consolidado_cotizacion.correo', 'LIKE', "%{$search}%")
                  ->orWhere('contenedor_consolidado_cotizacion.telefono', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento seguro
        $allowedSorts = [
            'nombre' => 'contenedor_consolidado_cotizacion.nombre',
            'documento' => 'contenedor_consolidado_cotizacion.documento',
            'correo' => 'contenedor_consolidado_cotizacion.correo',
            'telefono' => 'contenedor_consolidado_cotizacion.telefono',
            'volumen' => 'contenedor_consolidado_cotizacion.volumen',
            'fob' => 'contenedor_consolidado_cotizacion.fob',
            'logistica' => 'contenedor_consolidado_cotizacion.monto',
            'impuestos' => 'contenedor_consolidado_cotizacion.impuestos',
            'tarifa' => 'contenedor_consolidado_cotizacion.tarifa',
            'fecha' => 'contenedor_consolidado_cotizacion.fecha',
            'tipo_cliente' => 'contenedor_consolidado_tipo_cliente.name',
        ];
        $sortField = $request->input('sort_by', 'fecha');
        $sortOrder = strtolower($request->input('sort_order', 'asc'));
        $sortColumn = $allowedSorts[$sortField] ?? $allowedSorts['fecha'];
        $query->orderBy($sortColumn, $sortOrder);

        $rows = $query->get();

        $datosExport = [];
        $index = 1;
        foreach ($rows as $row) {
            $datosExport[] = [
                'n' => $index++,
                'carga' => $contenedor->carga ?? '',
                'fecha_cierre' => $this->safeFormatDate($contenedor->f_cierre ?? null),
                'asesor' => $asesoresMap[$row->id] ?? '',
                'COD' => $this->buildCod($contenedor, $row),
                'fecha' => $this->safeFormatDate($row->fecha ?? null),
                'cliente' => $row->nombre ?? '',
                'documento' => $row->documento ?? '',
                'correo' => $row->correo ?? '',
                'telefono' => $row->telefono ?? '',
                'tipo_cliente' => $row->tipo_cliente ?? '',
                'volumen' => $row->volumen,
                'volumen_china' => $row->volumen_china ?? 0,
                'fob' => $row->fob ?? 0,
                'logistica' => $row->logistica ?? 0,
                'impuesto' => $row->impuestos ?? 0,
                'tarifa' => $row->tarifa ?? 0,
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
            'B2' => 'Reporte de Clientes',
            'B3' => 'N°',
            'C3' => 'Carga',
            'D3' => 'F. Cierre',
            'E3' => 'Asesor',
            'F3' => 'COD',
            'G3' => 'Fecha',
            'H3' => 'Cliente',
            'I3' => 'Documento',
            'J3' => 'Correo',
            'K3' => 'Teléfono',
            'L3' => 'Tipo Cliente',
            'M3' => 'Volumen',
            'N3' => 'Volumen China',
            'O3' => 'FOB',
            'P3' => 'Logística',
            'Q3' => 'Impuesto',
            'R3' => 'Tarifa'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Estilos para el título
        $sheet->mergeCells('B2:R2');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('B2')->getAlignment()->setHorizontal('center');
        
                // Estilos para los encabezados de columna
        $sheet->getStyle('B3:R3')->getFont()->setBold(true);
        $sheet->getStyle('B3:U3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('B3:U3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B3:U3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }

    //Llena los datos en el Excel y obtiene información de dimensiones
    private function llenarDatosExcel($sheet, $datosExport)
    {
        $row = 4; // Comenzar desde la fila 4
        $maxServiceCount = 0;

        foreach ($datosExport as $data) {
            $col = 'B';
            foreach ($data as $key => $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        return [
            'totalRows' => $row - 1,
            'maxServiceCount' => $maxServiceCount
        ];
    }

    /**
     * Parse a date value safely and return formatted d/m/Y or empty string.
     * Accepts DateTime, Carbon, timestamps, or strings in common formats (Y-m-d, d/m/Y, etc.).
     */    private function safeFormatDate($date)
    {
        if (!$date) {
            return '';
        }

        try {
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->format('d/m/Y');
            } elseif (is_numeric($date)) {
                return Carbon::createFromTimestamp($date)->format('d/m/Y');
            } elseif (is_string($date)) {
                // Try common formats
                $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
                foreach ($formats as $format) {
                    $parsed = Carbon::createFromFormat($format, $date);
                    if ($parsed && $parsed->format($format) === $date) {
                        return $parsed->format('d/m/Y');
                    }
                }
                // Fallback to Carbon's parser
                return Carbon::parse($date)->format('d/m/Y');
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$date}. Error: " . $e->getMessage());
        }

        return '';
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
     */    private function aplicarFormatoExcel($sheet, $infoDimensiones)
    {
        $totalRows = $infoDimensiones['totalRows'];
        $maxServiceCount = $infoDimensiones['maxServiceCount'];

        // Ajustar ancho de columnas
        foreach (range('B', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Bordes para todo el rango de datos
        $sheet->getStyle("B3:R{$totalRows}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Alineación vertical para todas las celdas con datos
        $sheet->getStyle("B4:R{$totalRows}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    }
}