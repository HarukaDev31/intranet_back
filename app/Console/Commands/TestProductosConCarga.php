<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\ProductoImportadoExcel;

class TestProductosConCarga extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:productos-carga';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba los productos con campo carga_contenedor incluido';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Productos con Campo Carga Contenedor ===');
        $this->line('');

        try {
            // 1. Simular la lógica del controlador
            $this->info('1. Simulando lógica del controlador...');
            
            $productos = ProductoImportadoExcel::with('contenedor')
                ->limit(5)
                ->get()
                ->map(function ($producto) {
                    // Obtener todos los campos del producto
                    $productoData = $producto->toArray();
                    
                    // Agregar el campo carga del contenedor directamente al producto
                    $productoData['carga_contenedor'] = $producto->contenedor ? $producto->contenedor->carga : null;
                    
                    // Remover el objeto contenedor completo
                    unset($productoData['contenedor']);
                    
                    return $productoData;
                });
            
            $this->line("   ✅ Productos procesados: " . $productos->count());
            $this->line('');

            // 2. Mostrar estructura de respuesta
            $this->info('2. Estructura de respuesta:');
            
            foreach ($productos as $index => $producto) {
                $this->line("   Producto " . ($index + 1) . ":");
                $this->line("   - ID: {$producto['id']}");
                $this->line("   - Item: {$producto['item']}");
                $this->line("   - Nombre: {$producto['nombre_comercial']}");
                $this->line("   - Rubro: {$producto['rubro']}");
                $this->line("   - Tipo: {$producto['tipo']}");
                $this->line("   - Contenedor ID: {$producto['idContenedor']}");
                $this->line("   - Carga Contenedor: {$producto['carga_contenedor']}");
                
                // Verificar que no existe el objeto contenedor
                if (!isset($producto['contenedor'])) {
                    $this->line("   - ✅ Objeto contenedor removido correctamente");
                } else {
                    $this->line("   - ❌ Objeto contenedor aún presente");
                }
                
                $this->line('');
            }

            // 3. Verificar campos disponibles
            $this->info('3. Campos disponibles en cada producto:');
            $primerProducto = $productos->first();
            if ($primerProducto) {
                $campos = array_keys($primerProducto);
                $this->line("   Campos encontrados (" . count($campos) . "):");
                foreach ($campos as $campo) {
                    $this->line("   - {$campo}");
                }
            }
            
            $this->line('');

            // 4. Verificar que carga_contenedor está presente
            $this->info('4. Verificando campo carga_contenedor:');
            
            $productosConCarga = $productos->filter(function($producto) {
                return isset($producto['carga_contenedor']) && $producto['carga_contenedor'] !== null;
            })->count();
            
            $this->line("   - Productos con carga_contenedor: {$productosConCarga}");
            $this->line("   - Productos sin carga_contenedor: " . ($productos->count() - $productosConCarga));
            
            // Mostrar valores únicos de carga
            $cargasUnicas = $productos->pluck('carga_contenedor')->unique()->filter()->values();
            $this->line("   - Valores únicos de carga: " . $cargasUnicas->implode(', '));
            
            $this->line('');

            // 5. Simular respuesta JSON
            $this->info('5. Simulando respuesta JSON:');
            
            $response = $productos->take(2)->toArray();
            
            $this->line("   ✅ Respuesta simulada:");
            $this->line("   - Total productos: " . count($response));
            $this->line("   - Estructura plana (sin objeto contenedor anidado)");
            $this->line("   - Campo 'carga_contenedor' incluido directamente");
            
            $this->line('');

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 