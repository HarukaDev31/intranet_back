<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\ProductoImportadoExcel;

class TestProductosConRelacion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:productos-relacion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el endpoint de productos con relación de contenedor';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Productos con Relación de Contenedor ===');
        $this->line('');

        try {
            // 1. Probar consulta con relación
            $this->info('1. Probando consulta con relación...');
            
            $productos = ProductoImportadoExcel::with('contenedor')->limit(5)->get();
            
            $this->line("   ✅ Productos obtenidos: " . $productos->count());
            $this->line('');

            // 2. Mostrar detalles de cada producto
            $this->info('2. Detalles de productos con contenedor:');
            
            foreach ($productos as $index => $producto) {
                $this->line("   Producto " . ($index + 1) . ":");
                $this->line("   - ID: {$producto->id}");
                $this->line("   - Item: {$producto->item}");
                $this->line("   - Nombre: {$producto->nombre_comercial}");
                $this->line("   - Rubro: {$producto->rubro}");
                $this->line("   - Tipo: {$producto->tipo}");
                $this->line("   - Contenedor ID: {$producto->idContenedor}");
                
                if ($producto->contenedor) {
                    $this->line("   - Carga del contenedor: {$producto->contenedor->carga}");
                    $this->line("   - Mes: {$producto->contenedor->mes}");
                    $this->line("   - Estado: {$producto->contenedor->estado}");
                    $this->line("   - Tipo de carga: {$producto->contenedor->tipo_carga}");
                } else {
                    $this->line("   - ⚠️  No tiene contenedor asociado");
                }
                
                $this->line('');
            }

            // 3. Probar estadísticas
            $this->info('3. Estadísticas de la relación:');
            
            $totalProductos = ProductoImportadoExcel::count();
            $productosConContenedor = ProductoImportadoExcel::whereHas('contenedor')->count();
            $productosSinContenedor = ProductoImportadoExcel::whereDoesntHave('contenedor')->count();
            
            $this->line("   - Total de productos: {$totalProductos}");
            $this->line("   - Productos con contenedor: {$productosConContenedor}");
            $this->line("   - Productos sin contenedor: {$productosSinContenedor}");
            $this->line("");

            // 4. Probar filtros con relación
            $this->info('4. Probando filtros con relación:');
            
            // Productos de contenedor específico
            $productosContenedor58 = ProductoImportadoExcel::with('contenedor')
                ->where('idContenedor', 58)
                ->count();
            $this->line("   - Productos del contenedor 58: {$productosContenedor58}");
            
            // Productos con carga específica
            $productosCarga8 = ProductoImportadoExcel::with('contenedor')
                ->whereHas('contenedor', function($query) {
                    $query->where('carga', '8');
                })
                ->count();
            $this->line("   - Productos con carga 8: {$productosCarga8}");
            
            // Productos por estado de contenedor
            $productosContenedorCompletado = ProductoImportadoExcel::with('contenedor')
                ->whereHas('contenedor', function($query) {
                    $query->where('estado', 'COMPLETADO');
                })
                ->count();
            $this->line("   - Productos de contenedores completados: {$productosContenedorCompletado}");
            
            $this->line("");

            // 5. Simular respuesta del endpoint
            $this->info('5. Simulando respuesta del endpoint:');
            
            $productosResponse = ProductoImportadoExcel::with('contenedor')->limit(3)->get();
            
            $response = [
                'status' => 'success',
                'data' => $productosResponse->map(function($producto) {
                    return [
                        'id' => $producto->id,
                        'item' => $producto->item,
                        'nombre_comercial' => $producto->nombre_comercial,
                        'rubro' => $producto->rubro,
                        'tipo' => $producto->tipo,
                        'contenedor' => $producto->contenedor ? [
                            'id' => $producto->contenedor->id,
                            'carga' => $producto->contenedor->carga,
                            'mes' => $producto->contenedor->mes,
                            'estado' => $producto->contenedor->estado,
                            'tipo_carga' => $producto->contenedor->tipo_carga
                        ] : null
                    ];
                })
            ];
            
            $this->line("   ✅ Respuesta simulada:");
            $this->line("   - Status: " . $response['status']);
            $this->line("   - Productos: " . count($response['data']));
            $this->line("   - Productos con contenedor: " . $response['data']->filter(fn($p) => $p['contenedor'])->count());
            
            $this->line("");

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 