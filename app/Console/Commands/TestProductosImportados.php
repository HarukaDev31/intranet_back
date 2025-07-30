<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\ProductoImportadoExcel;

class TestProductosImportados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:productos-importados';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el modelo ProductoImportadoExcel';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba del Modelo ProductoImportadoExcel ===');
        $this->line('');

        try {
            // Probar consultas básicas
            $this->info('1. Probando consultas básicas:');
            $total = ProductoImportadoExcel::count();
            $this->line("   Total de productos: " . $total);
            
            if ($total > 0) {
                $producto = ProductoImportadoExcel::first();
                $this->line("   Primer producto:");
                $this->line("   - ID: " . $producto->id);
                $this->line("   - Nombre: " . ($producto->nombre_comercial ?: 'Sin nombre'));
                $this->line("   - Item: " . ($producto->item ?: 'Sin item'));
                $this->line("   - Tipo: " . $producto->tipo_formateado);
                $this->line("   - Precio: " . $producto->precio_formateado);
            }
            $this->line('');

            // Probar scopes
            $this->info('2. Probando scopes:');
            $libres = ProductoImportadoExcel::libres()->count();
            $restringidos = ProductoImportadoExcel::restringidos()->count();
            $this->line("   Productos libres: " . $libres);
            $this->line("   Productos restringidos: " . $restringidos);
            $this->line('');

            // Probar scopes de búsqueda
            $this->info('3. Probando scopes de búsqueda:');
            $conFoto = ProductoImportadoExcel::whereNotNull('foto')->count();
            $conPrecio = ProductoImportadoExcel::whereNotNull('precio_exw')->count();
            $conCaracteristicas = ProductoImportadoExcel::whereNotNull('caracteristicas')->count();
            $this->line("   Con foto: " . $conFoto);
            $this->line("   Con precio: " . $conPrecio);
            $this->line("   Con características: " . $conCaracteristicas);
            $this->line('');

            // Probar atributos calculados
            if ($total > 0) {
                $this->info('4. Probando atributos calculados:');
                $producto = ProductoImportadoExcel::first();
                
                $this->line("   Es libre: " . ($producto->es_libre ? 'Sí' : 'No'));
                $this->line("   Es restringido: " . ($producto->es_restringido ? 'Sí' : 'No'));
                $this->line("   Tiene foto: " . ($producto->tiene_foto ? 'Sí' : 'No'));
                $this->line("   Tiene link: " . ($producto->tiene_link ? 'Sí' : 'No'));
                $this->line("   Tiene características: " . ($producto->tiene_caracteristicas ? 'Sí' : 'No'));
                $this->line("   Tipo formateado: " . $producto->tipo_formateado);
                $this->line("   Rubro formateado: " . $producto->rubro_formateado);
                $this->line("   Tipo producto formateado: " . $producto->tipo_producto_formateado);
                
                if ($producto->tiene_foto) {
                    $this->line("   URL de foto: " . $producto->foto_url);
                }
                
                if ($producto->tiene_caracteristicas) {
                    $caracteristicas = $producto->caracteristicas_array;
                    $this->line("   Características (array): " . count($caracteristicas) . " elementos");
                }
                
                $infoRegulaciones = $producto->informacion_regulaciones;
                $this->line("   Información de regulaciones: " . count($infoRegulaciones) . " campos");
                
                $infoArancelaria = $producto->informacion_arancelaria;
                $this->line("   Información arancelaria: " . count($infoArancelaria) . " campos");
            }
            $this->line('');

            // Probar métodos estáticos
            $this->info('5. Probando métodos estáticos:');
            $tiposPermitidos = ProductoImportadoExcel::getTiposPermitidos();
            $this->line("   Tipos permitidos: " . implode(', ', $tiposPermitidos));
            
            $estadisticas = ProductoImportadoExcel::getEstadisticas();
            $this->line("   Estadísticas:");
            $this->line("   - Total: " . $estadisticas['total']);
            $this->line("   - Libres: " . $estadisticas['libres']);
            $this->line("   - Restringidos: " . $estadisticas['restringidos']);
            $this->line("   - Con foto: " . $estadisticas['con_foto']);
            $this->line("   - Con precio: " . $estadisticas['con_precio']);
            $this->line("   - Con características: " . $estadisticas['con_caracteristicas']);
            $this->line("   - Por contenedor: " . count($estadisticas['por_contenedor']) . " contenedores");
            
            $productosPorRubro = ProductoImportadoExcel::getProductosPorRubro();
            $this->line("   Productos por rubro: " . count($productosPorRubro) . " rubros");
            
            $productosPorTipo = ProductoImportadoExcel::getProductosPorTipo();
            $this->line("   Productos por tipo: " . count($productosPorTipo) . " tipos");
            $this->line('');

            // Probar creación de producto (simulación)
            $this->info('6. Probando creación de producto (simulación):');
            $productoNuevo = new ProductoImportadoExcel([
                'idContenedor' => 1,
                'item' => 'ITEM001',
                'nombre_comercial' => 'Producto de Prueba',
                'rubro' => 'Electrónicos',
                'tipo_producto' => 'Gadget',
                'precio_exw' => 99.99,
                'tipo' => ProductoImportadoExcel::TIPO_LIBRE
            ]);
            
            $this->line("   Producto creado (sin guardar):");
            $this->line("   - Nombre: " . $productoNuevo->nombre_comercial);
            $this->line("   - Tipo: " . $productoNuevo->tipo_formateado);
            $this->line("   - Precio: " . $productoNuevo->precio_formateado);
            $this->line("   - Es libre: " . ($productoNuevo->es_libre ? 'Sí' : 'No'));
            $this->line('');

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 