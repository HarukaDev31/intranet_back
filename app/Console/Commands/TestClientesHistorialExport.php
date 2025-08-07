<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Exports\ClientesHistorialExport;
use Maatwebsite\Excel\Facades\Excel;

class TestClientesHistorialExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:clientes-historial-export {--empresa=} {--organizacion=} {--estado=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la funcionalidad de exportación de clientes con historial de compras';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Exportación de Clientes con Historial ===');
        $this->line('');

        try {
            // Obtener parámetros
            $empresa = $this->option('empresa');
            $organizacion = $this->option('organizacion');
            $estado = $this->option('estado');

            $this->info('Parámetros de prueba:');
            $this->line('   Empresa: ' . ($empresa ?: 'Todas'));
            $this->line('   Organización: ' . ($organizacion ?: 'Todas'));
            $this->line('   Estado: ' . ($estado !== null ? $estado : 'Todos'));
            $this->line('');

            // Preparar filtros
            $filters = [];
            if ($empresa) $filters['empresa'] = $empresa;
            if ($organizacion) $filters['organizacion'] = $organizacion;
            if ($estado !== null) $filters['estado'] = $estado;

            // Crear instancia del export
            $export = new ClientesHistorialExport($filters);
            
            // Obtener datos
            $this->info('Obteniendo datos...');
            $data = $export->collection();
            $this->info('Total de clientes encontrados: ' . $data->count());
            $this->line('');

            if ($data->count() == 0) {
                $this->warn('No se encontraron clientes con los filtros especificados');
                return 0;
            }

            // Mostrar encabezados
            $this->info('Estructura de columnas:');
            $headers = $export->headings();
            foreach ($headers as $index => $header) {
                $this->line('   ' . ($index + 1) . '. ' . $header);
            }
            $this->line('');

            // Mostrar vista previa de datos (primeros 3 registros)
            $this->info('Vista previa de datos (primeros 3 registros):');
            $this->line('');

            foreach ($data->take(3) as $index => $cliente) {
                $this->line('Cliente ' . ($index + 1) . ':');
                $this->line('   ID: ' . $cliente->ID_Entidad);
                $this->line('   Nombre: ' . $cliente->No_Entidad);
                $this->line('   DNI: ' . $cliente->Nu_Documento_Identidad);
                $this->line('   Email: ' . $cliente->Txt_Email_Entidad);
                $this->line('   Celular: ' . $cliente->Nu_Celular_Entidad);
                $this->line('   Fecha Registro: ' . ($cliente->Fe_Registro ? $cliente->Fe_Registro->format('d/m/Y') : 'N/A'));
                
                // Mostrar pedidos del cliente
                $pedidos = $cliente->pedidosCurso()->orderBy('Fe_Emision', 'desc')->take(3)->get();
                $this->line('   Pedidos encontrados: ' . $pedidos->count());
                
                foreach ($pedidos as $pedidoIndex => $pedido) {
                    $this->line('     Pedido ' . ($pedidoIndex + 1) . ':');
                    $this->line('       Fecha: ' . ($pedido->Fe_Emision ? $pedido->Fe_Emision->format('d/m/Y') : 'N/A'));
                    $this->line('       Monto: ' . ($pedido->Ss_Total ? number_format($pedido->Ss_Total, 2) : '0.00'));
                    $this->line('       Campaña: ' . ($pedido->campana ? $pedido->campana->No_Campana : 'N/A'));
                }
                $this->line('');
            }

            // Probar mapeo de datos
            $this->info('Probando mapeo de datos...');
            $sampleData = $data->first();
            if ($sampleData) {
                $mappedRow = $export->map($sampleData);
                $this->info('Mapeo exitoso. Columnas generadas: ' . count($mappedRow));
                
                // Mostrar algunas columnas mapeadas
                $this->line('   Columnas principales:');
                $this->line('     N.: ' . $mappedRow[0]);
                $this->line('     NOMBRE: ' . $mappedRow[1]);
                $this->line('     DNI: ' . $mappedRow[2]);
                $this->line('     CORREO: ' . $mappedRow[3]);
                $this->line('     WHATSAPP: ' . $mappedRow[4]);
                $this->line('     FECHAS: ' . $mappedRow[5]);
                $this->line('     SERVICIO: ' . $mappedRow[6]);
                $this->line('     CATEGORIA: ' . $mappedRow[7]);
            }
            $this->line('');

            // Verificar tipos de servicio y categorías
            $this->info('Verificando tipos de servicio y categorías...');
            $tiposServicio = [];
            $categorias = [];
            
            foreach ($data->take(10) as $cliente) {
                $tipoServicio = $this->getTipoServicio($cliente);
                $categoria = $this->getCategoriaCliente($cliente);
                
                if (!in_array($tipoServicio, $tiposServicio)) {
                    $tiposServicio[] = $tipoServicio;
                }
                
                if (!in_array($categoria, $categorias)) {
                    $categorias[] = $categoria;
                }
            }
            
            $this->line('   Tipos de servicio encontrados: ' . implode(', ', $tiposServicio));
            $this->line('   Categorías encontradas: ' . implode(', ', $categorias));
            $this->line('');

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Obtener el tipo de servicio del cliente
     */
    private function getTipoServicio($cliente)
    {
        if ($cliente->Nu_Curso == 1) {
            return 'Curso';
        } elseif ($cliente->Nu_Carga_Consolidada == 1) {
            return 'Carga Consolidada';
        } elseif ($cliente->Nu_Importacion_Grupal == 1) {
            return 'Importación Grupal';
        } elseif ($cliente->Nu_Viaje_Negocios == 1) {
            return 'Viaje de Negocios';
        } else {
            return 'Cliente General';
        }
    }

    /**
     * Obtener la categoría del cliente
     */
    private function getCategoriaCliente($cliente)
    {
        if ($cliente->Nu_Agente_Compra == 1) {
            return 'Agente de Compra';
        } elseif ($cliente->Nu_Como_Entero_Empresa == 1) {
            return 'Empresa';
        } else {
            return 'Cliente Individual';
        }
    }
} 