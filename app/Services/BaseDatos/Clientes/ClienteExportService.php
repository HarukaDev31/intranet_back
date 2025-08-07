<?php

namespace App\Services\BaseDatos\Clientes;

use App\Models\BaseDatos\Clientes\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class ClienteExportService
{
    protected $clienteService;

    public function __construct(ClienteService $clienteService)
    {
        $this->clienteService = $clienteService;
    }

    /**
     * Exportar clientes a Excel
     */
    public function exportarClientes(Request $request)
    {
        try {
            // Obtener datos filtrados
            $datosExport = $this->obtenerDatosParaExportar($request);
            
            // Crear el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Configurar encabezados principales
            $this->configurarEncabezadosPrincipales($sheet);
            
            // Llenar datos y obtener información de dimensiones
            $infoDimensiones = $this->llenarDatosExcel($sheet, $datosExport);
            
            // Configurar encabezados de servicios dinámicamente
            $this->configurarEncabezadosServicios($sheet, $infoDimensiones['maxServiceCount']);
            
            // Aplicar formato y estilos
            $this->aplicarFormatoExcel($sheet, $infoDimensiones);
            
            // Generar y descargar archivo
            return $this->generarDescargaExcel($spreadsheet);
            
        } catch (\Exception $e) {
            Log::error('Error al exportar clientes: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene los datos filtrados para la exportación
     */
    private function obtenerDatosParaExportar(Request $request)
    {
        $query = Cliente::query();

        // Aplicar filtros
        if ($request->has('search') && !empty($request->search)) {
            $query->buscar($request->search);
        }

        if ($request->has('servicio') && !empty($request->servicio) && $request->servicio != 'todos') {
            $query->porServicio($request->servicio);
        }

        if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
            $fechaInicio = Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
            $query->where('fecha', '>=', $fechaInicio);
        }

        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $fechaFin = Carbon::createFromFormat('d/m/Y', $request->fecha_fin)->endOfDay();
            $query->where('fecha', '<=', $fechaFin);
        }

        $filtroCategoria = $request->has('categoria') && !empty($request->categoria) && $request->categoria != 'todos'
            ? $request->categoria : null;

        $query->orderBy('created_at', 'desc');

        // Obtener datos según filtro de categoría
        if ($filtroCategoria) {
            $todosLosClientes = $query->get();
            $todosLosIds = $todosLosClientes->pluck('id')->toArray();
            $serviciosPorCliente = $this->obtenerServiciosEnLote($todosLosIds);

            $clientesFiltrados = [];
            foreach ($todosLosClientes as $cliente) {
                $servicios = $serviciosPorCliente[$cliente->id] ?? [];
                $categoria = $this->determinarCategoriaCliente($servicios);

                if ($categoria === $filtroCategoria) {
                    $clientesFiltrados[] = [
                        'cliente' => $cliente,
                        'servicios' => $servicios,
                        'categoria' => $categoria
                    ];
                }
            }
            return $clientesFiltrados;
        } else {
            $clientes = $query->get();
            $clienteIds = $clientes->pluck('id')->toArray();
            $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

            $datosExport = [];
            foreach ($clientes as $cliente) {
                $servicios = $serviciosPorCliente[$cliente->id] ?? [];
                $categoria = $this->determinarCategoriaCliente($servicios);

                $datosExport[] = [
                    'cliente' => $cliente,
                    'servicios' => $servicios,
                    'categoria' => $categoria
                ];
            }
            return $datosExport;
        }
    }

    /**
     * Configura los encabezados principales del Excel
     */
    private function configurarEncabezadosPrincipales($sheet)
    {
        $headers = [
            'B2' => 'INFORMACION PRINCIPAL',
            'B3' => 'N',
            'C3' => 'NOMBRE',
            'D3' => 'DNI',
            'E3' => 'RUC',
            'F3' => 'EMPRESA',
            'G3' => 'CORREO',
            'H3' => 'WHATSAPP',
            'I3' => 'FECHA REGISTRO',
            'J3' => 'SERVICIO',
            'K3' => 'CATEGORIA',
            'L2' => 'HISTORIAL DE COMPRA',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
    }

    /**
     * Llena los datos en el Excel y retorna información de dimensiones
     */
    private function llenarDatosExcel($sheet, $datosExport)
    {
        $row = 4;
        $maxServiceCount = 0;

        foreach ($datosExport as $item) {
            $cliente = $item['cliente'];
            $servicios = $item['servicios'];
            $categoria = $item['categoria'];
            $primerServicio = !empty($servicios) ? $servicios[0] : null;

            // Llenar información principal del cliente
            $sheet->setCellValue('B' . $row, $cliente->id);
            $sheet->setCellValue('C' . $row, $cliente->nombre);
            $sheet->setCellValue('D' . $row, $cliente->documento);
            $sheet->setCellValue('E' . $row, $cliente->ruc);
            $sheet->setCellValue('F' . $row, $cliente->empresa);
            $sheet->setCellValue('G' . $row, $cliente->correo);
            $sheet->setCellValue('H' . $row, $cliente->telefono);
            $sheet->setCellValue('I' . $row, $cliente->fecha ? $cliente->fecha->format('d/m/Y') : '');
            $sheet->setCellValue('J' . $row, $primerServicio ? $primerServicio['servicio'] : '');
            $sheet->setCellValue('K' . $row, $categoria);
            
            // Llenar servicios
            $currentColumn = 'L';
            if (count($servicios) > $maxServiceCount) {
                $maxServiceCount = count($servicios);
            }
            
            foreach ($servicios as $servicio) {
                $sheet->setCellValue($currentColumn . $row, $servicio['servicio']);
                $sheet->setCellValue($this->getColumnLetter($currentColumn, 1) . $row, $servicio['monto'] ?? 0);
                $sheet->setCellValue($this->getColumnLetter($currentColumn, 2) . $row, $servicio['fecha']);
                $currentColumn = $this->getColumnLetter($currentColumn, 3);
            }

            $row++;
        }
        
        return [
            'maxServiceCount' => $maxServiceCount,
            'lastRow' => $row - 1,
            'maxColumn' => $this->getColumnLetter('L', ($maxServiceCount * 3) - 1)
        ];
    }

    /**
     * Configura los encabezados de servicios dinámicamente
     */
    private function configurarEncabezadosServicios($sheet, $maxServiceCount)
    {
        $startColumn = 'L';
        for ($i = 0; $i < $maxServiceCount; $i++) {
            $sheet->setCellValue($startColumn . 3, 'SERVICIO ' . ($i + 1));
            $startColumn = $this->getColumnLetter($startColumn, 1);
            $sheet->setCellValue($startColumn . 3, 'MONTO ' . ($i + 1));
            $startColumn = $this->getColumnLetter($startColumn, 1);
            $sheet->setCellValue($startColumn . 3, 'FECHA ' . ($i + 1));
            $startColumn = $this->getColumnLetter($startColumn, 1);
        }
    }

    /**
     * Aplica formato y estilos al Excel
     */
    private function aplicarFormatoExcel($sheet, $infoDimensiones)
    {
        $maxColumn = $infoDimensiones['maxColumn'];
        $lastRow = $infoDimensiones['lastRow'];
        
        // Unir celdas de encabezados
        $sheet->mergeCells('B2:K2');
        $sheet->mergeCells('L2:' . $maxColumn . '2');
        
        // Configurar ancho de columnas específico para mejor legibilidad
        $columnWidths = [
            'B' => 8,   // N (ID)
            'C' => 25,  // NOMBRE
            'D' => 15,  // DNI
            'E' => 15,  // RUC
            'F' => 30,  // EMPRESA
            'G' => 30,  // CORREO
            'H' => 20,  // WHATSAPP
            'I' => 15,  // FECHA REGISTRO
            'J' => 15,  // SERVICIO
            'K' => 15,  // CATEGORIA
        ];
        
        // Aplicar anchos específicos a las columnas principales
        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        
        // Para las columnas de servicios, usar autoSize pero con un mínimo
        $startServiceColumn = 'L';
        for ($i = 0; $i < $infoDimensiones['maxServiceCount']; $i++) {
            $serviceCol = $this->getColumnLetter($startServiceColumn, $i * 3);
            $montoCol = $this->getColumnLetter($startServiceColumn, $i * 3 + 1);
            $fechaCol = $this->getColumnLetter($startServiceColumn, $i * 3 + 2);
            
            $sheet->getColumnDimension($serviceCol)->setWidth(20);  // SERVICIO
            $sheet->getColumnDimension($montoCol)->setWidth(15);    // MONTO
            $sheet->getColumnDimension($fechaCol)->setWidth(15);    // FECHA
        }
        
        // Configurar formato de texto para la columna H (WhatsApp)
        $sheet->getStyle('H4:H' . $lastRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        
        // Configurar formato de texto para columnas de servicios
        $serviceRange = 'L4:' . $maxColumn . $lastRow;
        $sheet->getStyle($serviceRange)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        
        // Aplicar bordes a toda la tabla
        $range = 'B3:' . $maxColumn . $lastRow;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Aplicar bordes a los encabezados principales
        $sheet->getStyle('B2:' . $maxColumn . '3')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Centrar encabezados
        $sheet->getStyle('B2:' . $maxColumn . '3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B2:' . $maxColumn . '3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        
        // Aplicar color de fondo a encabezados
        $sheet->getStyle('B2:' . $maxColumn . '3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E6E6E6');
            
        // Configurar wrap text para columnas con texto largo
        $sheet->getStyle('C4:C' . $lastRow)->getAlignment()->setWrapText(true); // NOMBRE
        $sheet->getStyle('F4:F' . $lastRow)->getAlignment()->setWrapText(true); // EMPRESA
        $sheet->getStyle('G4:G' . $lastRow)->getAlignment()->setWrapText(true); // CORREO
        $sheet->getStyle('H4:H' . $lastRow)->getAlignment()->setWrapText(true); // WHATSAPP
    }

    /**
     * Genera y retorna la descarga del archivo Excel
     */
    private function generarDescargaExcel($spreadsheet)
    {
        $filename = 'Reporte_Clientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * Obtiene la letra de columna de Excel a partir de un índice numérico.
     */
    private function getColumnLetter($column = 'A', $i = 1)
    {
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) + $i;
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
    }

    /**
     * Obtener servicios en lote para múltiples clientes
     */
    private function obtenerServiciosEnLote($clienteIds)
    {
        if (empty($clienteIds)) {
            return [];
        }

        $serviciosPorCliente = [];

        // Obtener servicios de pedido_curso
        $pedidosCurso = \DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2)
            ->whereIn('pc.id_cliente', $clienteIds)
            ->select(
                'pc.id_cliente',
                'e.Fe_Registro as fecha',
                \DB::raw("'Curso' as servicio"),
                \DB::raw('NULL as monto')
            )
            ->get();

        // Obtener servicios de contenedor_consolidado_cotizacion
        $cotizaciones = \DB::table('contenedor_consolidado_cotizacion')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereIn('id_cliente', $clienteIds)
            ->select(
                'id_cliente',
                'fecha',
                \DB::raw("'Consolidado' as servicio"),
                'monto'
            )
            ->get();

        // Combinar y organizar por cliente
        foreach ($pedidosCurso as $pedido) {
            $serviciosPorCliente[$pedido->id_cliente][] = [
                'servicio' => $pedido->servicio,
                'fecha' => $pedido->fecha,
                'monto' => $pedido->monto
            ];
        }

        foreach ($cotizaciones as $cotizacion) {
            $serviciosPorCliente[$cotizacion->id_cliente][] = [
                'servicio' => $cotizacion->servicio,
                'fecha' => $cotizacion->fecha,
                'monto' => $cotizacion->monto
            ];
        }

        // Ordenar servicios por fecha para cada cliente
        foreach ($serviciosPorCliente as $clienteId => &$servicios) {
            usort($servicios, function ($a, $b) {
                return strtotime($a['fecha']) - strtotime($b['fecha']);
            });
        }

        return $serviciosPorCliente;
    }

    /**
     * Determinar categoría del cliente basada en sus servicios
     */
    private function determinarCategoriaCliente($servicios)
    {
        $totalServicios = count($servicios);
        
        if ($totalServicios === 0) {
            return 'Inactivo';
        }
        
        if ($totalServicios === 1) {
            return 'Cliente';
        }
        
        // Obtener la fecha del último servicio
        $ultimoServicio = end($servicios);
        $fechaUltimoServicio = Carbon::parse($ultimoServicio['fecha']);
        $hoy = Carbon::now();
        $mesesDesdeUltimaCompra = $fechaUltimoServicio->diffInMonths($hoy);
        
        // Si la última compra fue hace más de 6 meses, es Inactivo
        if ($mesesDesdeUltimaCompra > 6) {
            return 'Inactivo';
        }
        
        // Para clientes con múltiples servicios
        if ($totalServicios >= 2) {
            // Calcular frecuencia promedio de compras
            $primerServicio = $servicios[0];
            $fechaPrimerServicio = Carbon::parse($primerServicio['fecha']);
            $mesesEntrePrimeraYUltima = $fechaPrimerServicio->diffInMonths($fechaUltimoServicio);
            $frecuenciaPromedio = $mesesEntrePrimeraYUltima / ($totalServicios - 1);
            
            // Si compra cada 2 meses o menos Y la última compra fue hace ≤ 2 meses
            if ($frecuenciaPromedio <= 2 && $mesesDesdeUltimaCompra <= 2) {
                return 'Premium';
            }
            // Si tiene múltiples compras Y la última fue hace ≤ 6 meses
            else if ($mesesDesdeUltimaCompra <= 6) {
                return 'Recurrente';
            }
        }
        
        return 'Inactivo';
    }
} 