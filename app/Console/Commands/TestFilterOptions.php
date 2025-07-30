<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\CargaConsolidadaContenedor;
use App\Models\BaseDatos\ProductoImportadoExcel;

class TestFilterOptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:filter-options';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba las opciones de filtro para productos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Opciones de Filtro ===');
        $this->line('');

        try {
            // 1. Probar tabla carga_consolidada_contenedor
            $this->info('1. Probando tabla carga_consolidada_contenedor...');
            
            try {
                $cargas = CargaConsolidadaContenedor::getCargasUnicas();
                $this->line('   ✅ Cargas obtenidas: ' . count($cargas));
                
                if (count($cargas) > 0) {
                    $this->line('   - Primeras 5 cargas:');
                    foreach (array_slice($cargas, 0, 5) as $carga) {
                        $this->line('     * ' . $carga);
                    }
                }
            } catch (\Exception $e) {
                $this->warn('   ⚠️  Error con tabla carga_consolidada_contenedor: ' . $e->getMessage());
                $this->line('   - Probablemente la tabla no existe o no tiene datos');
            }
            
            $this->line('');

            // 2. Probar opciones de productos
            $this->info('2. Probando opciones de productos...');
            
            // Rubros
            $rubros = ProductoImportadoExcel::select('rubro')
                ->whereNotNull('rubro')
                ->where('rubro', '!=', '')
                ->distinct()
                ->orderBy('rubro')
                ->pluck('rubro')
                ->toArray();
            
            $this->line('   ✅ Rubros obtenidos: ' . count($rubros));
            if (count($rubros) > 0) {
                $this->line('   - Primeros 5 rubros:');
                foreach (array_slice($rubros, 0, 5) as $rubro) {
                    $this->line('     * ' . $rubro);
                }
            }
            
            // Tipos de producto
            $tiposProducto = ProductoImportadoExcel::select('tipo_producto')
                ->whereNotNull('tipo_producto')
                ->where('tipo_producto', '!=', '')
                ->distinct()
                ->orderBy('tipo_producto')
                ->pluck('tipo_producto')
                ->toArray();
            
            $this->line('   ✅ Tipos de producto obtenidos: ' . count($tiposProducto));
            if (count($tiposProducto) > 0) {
                $this->line('   - Primeros 5 tipos:');
                foreach (array_slice($tiposProducto, 0, 5) as $tipo) {
                    $this->line('     * ' . $tipo);
                }
            }
            
            // Tipos
            $tipos = ProductoImportadoExcel::select('tipo')
                ->whereNotNull('tipo')
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo')
                ->toArray();
            
            $this->line('   ✅ Tipos obtenidos: ' . count($tipos));
            if (count($tipos) > 0) {
                $this->line('   - Tipos disponibles:');
                foreach ($tipos as $tipo) {
                    $this->line('     * ' . $tipo);
                }
            }
            
            // Contenedores
            $contenedores = ProductoImportadoExcel::select('idContenedor')
                ->whereNotNull('idContenedor')
                ->distinct()
                ->orderBy('idContenedor')
                ->pluck('idContenedor')
                ->toArray();
            
            $this->line('   ✅ Contenedores obtenidos: ' . count($contenedores));
            if (count($contenedores) > 0) {
                $this->line('   - Primeros 5 contenedores:');
                foreach (array_slice($contenedores, 0, 5) as $contenedor) {
                    $this->line('     * ' . $contenedor);
                }
            }
            
            $this->line('');

            // 3. Simular respuesta del endpoint
            $this->info('3. Simulando respuesta del endpoint...');
            
            $response = [
                'status' => 'success',
                'data' => [
                    'cargas' => $cargas ?? [],
                    'rubros' => $rubros,
                    'tipos_producto' => $tiposProducto,
                    'tipos' => $tipos,
                    'contenedores' => $contenedores
                ]
            ];
            
            $this->line('   ✅ Respuesta simulada:');
            $this->line('   - Status: ' . $response['status']);
            $this->line('   - Cargas: ' . count($response['data']['cargas']));
            $this->line('   - Rubros: ' . count($response['data']['rubros']));
            $this->line('   - Tipos de producto: ' . count($response['data']['tipos_producto']));
            $this->line('   - Tipos: ' . count($response['data']['tipos']));
            $this->line('   - Contenedores: ' . count($response['data']['contenedores']));
            
            $this->line('');

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 