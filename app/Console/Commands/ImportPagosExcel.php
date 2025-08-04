<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\PedidoCurso;
use App\Models\Entidad;
use App\Models\Pais;
use App\Models\Moneda;
use App\Models\Campana;
use Carbon\Carbon;

class ImportPagosExcel extends Command
{
    protected $signature = 'import:pagos-excel {file : Ruta del archivo Excel}';
    protected $description = 'Importa datos de pagos desde Excel (consolidados y cursos)';

    private $empresaId;
    private $stats = [
        'consolidados' => ['creados' => 0, 'actualizados' => 0, 'errores' => 0],
        'cursos' => ['creados' => 0, 'actualizados' => 0, 'errores' => 0],
        'contenedores' => ['creados' => 0, 'errores' => 0]
    ];

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("El archivo no existe: {$filePath}");
            return 1;
        }

        $this->empresaId = auth()->user()->ID_Empresa ?? 1; // Default empresa ID
        
        $this->info("Iniciando importación desde: {$filePath}");
        
        try {
            // Leer el Excel usando PhpSpreadsheet directamente
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Obtener datos desde la fila 3
            $highestRow = $worksheet->getHighestRow();
            $this->info("Procesando {$highestRow} filas de datos...");
            
            DB::beginTransaction();
            
            for ($row = 3; $row <= $highestRow; $row++) {
                $this->processRow($worksheet, $row);
                
                if ($row % 100 == 0) {
                    $this->info("Procesadas {$row} filas...");
                }
            }
            
            DB::commit();
            
            $this->displayResults();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error durante la importación: " . $e->getMessage());
            Log::error("ImportPagosExcel error: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    private function processRow($worksheet, $row)
    {
        try {
            // Leer datos de la fila
            $data = $this->readRowData($worksheet, $row);
            
            if (empty($data['nombre']) || empty($data['tipo'])) {
                return; // Fila vacía, saltar
            }
            
            // Determinar tipo y procesar
            if (strtoupper($data['tipo']) === 'CONSOLIDADO') {
                $this->processConsolidado($data, $row);
            } elseif (strtoupper($data['tipo']) === 'CURSO') {
                $this->processCurso($data, $row);
            } else {
                $this->warn("Fila {$row}: Tipo desconocido '{$data['tipo']}'");
            }
            
        } catch (\Exception $e) {
            $this->stats['consolidados']['errores']++;
            $this->error("Error en fila {$row}: " . $e->getMessage());
        }
    }

    private function readRowData($worksheet, $row)
    {
        return [
            'nombre' => $this->getCellValue($worksheet, $row, 2), // Columna B
            'telefono' => $this->getCellValue($worksheet, $row, 3), // Columna C
            'tipo' => $this->getCellValue($worksheet, $row, 4), // Columna D
            'fecha' => $this->getCellValue($worksheet, $row, 5), // Columna E
            'contenedor' => $this->getCellValue($worksheet, $row, 6), // Columna F
            'documento' => $this->getCellValue($worksheet, $row, 7), // Columna G
            'nombre_completo' => $this->getCellValue($worksheet, $row, 8), // Columna H
        ];
    }

    private function getCellValue($worksheet, $row, $col)
    {
        $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
        return trim($value ?? '');
    }

    private function processConsolidado($data, $row)
    {
        // Crear o encontrar contenedor
        $contenedor = $this->findOrCreateContenedor($data['contenedor']);
        
        // Crear o actualizar cotización
        $cotizacion = $this->findOrCreateCotizacion($data, $contenedor->id);
        
        $this->stats['consolidados']['creados']++;
        $this->info("Fila {$row}: Consolidado procesado - {$data['nombre']}");
    }

    private function processCurso($data, $row)
    {
        // Crear o actualizar pedido de curso
        $pedidoCurso = $this->findOrCreatePedidoCurso($data);
        
        $this->stats['cursos']['creados']++;
        $this->info("Fila {$row}: Curso procesado - {$data['nombre']}");
    }

    private function findOrCreateContenedor($nombreContenedor)
    {
        if (empty($nombreContenedor)) {
            $nombreContenedor = 'Contenedor Default';
        }

        $contenedor = Contenedor::where('nombre', $nombreContenedor)
                               ->where('ID_Empresa', $this->empresaId)
                               ->first();

        if (!$contenedor) {
            $contenedor = Contenedor::create([
                'ID_Empresa' => $this->empresaId,
                'nombre' => $nombreContenedor,
                'estado' => 'ACTIVO',
                'fecha_creacion' => now(),
            ]);
            
            $this->stats['contenedores']['creados']++;
        }

        return $contenedor;
    }

    private function findOrCreateCotizacion($data, $contenedorId)
    {
        // Buscar por documento y contenedor
        $cotizacion = Cotizacion::where('documento', $data['documento'])
                               ->where('id_contenedor', $contenedorId)
                               ->first();

        if (!$cotizacion) {
            $cotizacion = Cotizacion::create([
                'id_contenedor' => $contenedorId,
                'nombre' => $data['nombre_completo'] ?: $data['nombre'],
                'documento' => $data['documento'],
                'telefono' => $data['telefono'],
                'fecha' => $this->parseDate($data['fecha']),
                'estado' => 'PENDIENTE',
                'estado_cotizador' => 'PENDIENTE',
                'monto' => 0,
                'logistica_final' => 0,
                'impuestos_final' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $cotizacion;
    }

    private function findOrCreatePedidoCurso($data)
    {
        // Buscar por documento
        $pedidoCurso = PedidoCurso::where('ID_Entidad', function($query) use ($data) {
            $query->select('ID_Entidad')
                  ->from('entidad')
                  ->where('Nu_Documento_Identidad', $data['documento'])
                  ->where('ID_Empresa', $this->empresaId);
        })->first();

        if (!$pedidoCurso) {
            // Crear entidad si no existe
            $entidad = $this->findOrCreateEntidad($data);
            
            // Obtener valores por defecto
            $pais = Pais::first() ?? Pais::create(['No_Pais' => 'Perú']);
            $moneda = Moneda::first() ?? Moneda::create(['No_Moneda' => 'PEN']);
            $campana = Campana::activas()->first() ?? Campana::create([
                'Fe_Creacion' => now(),
                'Fe_Inicio' => now(),
                'Fe_Fin' => now()->addYear(),
            ]);

            $pedidoCurso = PedidoCurso::create([
                'ID_Empresa' => $this->empresaId,
                'ID_Entidad' => $entidad->ID_Entidad,
                'ID_Pais' => $pais->ID_Pais,
                'ID_Moneda' => $moneda->ID_Moneda,
                'ID_Campana' => $campana->ID_Campana,
                'Fe_Emision' => $this->parseDate($data['fecha']),
                'Fe_Registro' => now(),
                'Ss_Total' => 0,
                'logistica_final' => 0,
                'impuestos_final' => 0,
                'Nu_Estado' => 1,
            ]);
        }

        return $pedidoCurso;
    }

    private function findOrCreateEntidad($data)
    {
        $entidad = Entidad::where('Nu_Documento_Identidad', $data['documento'])
                         ->where('ID_Empresa', $this->empresaId)
                         ->first();

        if (!$entidad) {
            $pais = Pais::first() ?? Pais::create(['No_Pais' => 'Perú']);
            
            $entidad = Entidad::create([
                'ID_Empresa' => $this->empresaId,
                'Nu_Tipo_Entidad' => 1, // Cliente
                'ID_Tipo_Documento_Identidad' => 1, // DNI por defecto
                'Nu_Documento_Identidad' => $data['documento'],
                'No_Entidad' => $data['nombre_completo'] ?: $data['nombre'],
                'Nu_Celular_Entidad' => $data['telefono'],
                'ID_Pais' => $pais->ID_Pais,
                'Nu_Estado' => 1,
                'Fe_Registro' => now(),
            ]);
        }

        return $entidad;
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return now();
        }

        // Intentar diferentes formatos de fecha
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'];
        
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                return $date;
            } catch (\Exception $e) {
                continue;
            }
        }

        // Si no se puede parsear, usar fecha actual
        return now();
    }

    private function displayResults()
    {
        $this->info("\n=== RESULTADOS DE LA IMPORTACIÓN ===");
        $this->info("Consolidados:");
        $this->info("  - Creados: {$this->stats['consolidados']['creados']}");
        $this->info("  - Actualizados: {$this->stats['consolidados']['actualizados']}");
        $this->info("  - Errores: {$this->stats['consolidados']['errores']}");
        
        $this->info("Cursos:");
        $this->info("  - Creados: {$this->stats['cursos']['creados']}");
        $this->info("  - Actualizados: {$this->stats['cursos']['actualizados']}");
        $this->info("  - Errores: {$this->stats['cursos']['errores']}");
        
        $this->info("Contenedores:");
        $this->info("  - Creados: {$this->stats['contenedores']['creados']}");
        $this->info("  - Errores: {$this->stats['contenedores']['errores']}");
        
        $total = $this->stats['consolidados']['creados'] + $this->stats['cursos']['creados'];
        $this->info("\nTotal de registros procesados: {$total}");
    }
} 