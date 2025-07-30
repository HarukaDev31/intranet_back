<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\ProductoImportadoExcel;

class TestProductosPaginados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:productos-paginados {--page=1} {--limit=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la paginación de productos con campo carga_contenedor';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $page = (int) $this->option('page');
        $limit = (int) $this->option('limit');
        
        $this->info("=== Prueba de Productos Paginados (Página {$page}, Límite {$limit}) ===");
        $this->line('');

        try {
            // 1. Simular la lógica del controlador
            $this->info('1. Simulando lógica del controlador con paginación...');
            
            // Validar parámetros
            $limit = max(1, min(100, $limit));
            $page = max(1, $page);
            
            // Consulta con paginación
            $productos = ProductoImportadoExcel::with('contenedor')
                ->paginate($limit, ['*'], 'page', $page);
            
            // Transformar los datos
            $data = $productos->items();
            $transformedData = [];
            
            foreach ($data as $producto) {
                $productoData = $producto->toArray();
                
                // Agregar el campo carga del contenedor directamente al producto
                $productoData['carga_contenedor'] = $producto->contenedor ? $producto->contenedor->carga : null;
                
                // Remover el objeto contenedor completo
                unset($productoData['contenedor']);
                
                $transformedData[] = $productoData;
            }
            
            // Crear respuesta paginada
            $response = [
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $productos->currentPage(),
                    'last_page' => $productos->lastPage(),
                    'per_page' => $productos->perPage(),
                    'total' => $productos->total(),
                    'from' => $productos->firstItem(),
                    'to' => $productos->lastItem(),
                    'has_more_pages' => $productos->hasMorePages(),
                    'next_page_url' => $productos->nextPageUrl(),
                    'prev_page_url' => $productos->previousPageUrl(),
                ]
            ];
            
            $this->line("   ✅ Productos procesados: " . count($transformedData));
            $this->line('');

            // 2. Mostrar información de paginación
            $this->info('2. Información de paginación:');
            $this->line("   - Página actual: {$response['pagination']['current_page']}");
            $this->line("   - Última página: {$response['pagination']['last_page']}");
            $this->line("   - Items por página: {$response['pagination']['per_page']}");
            $this->line("   - Total de productos: {$response['pagination']['total']}");
            $this->line("   - Desde: {$response['pagination']['from']}");
            $this->line("   - Hasta: {$response['pagination']['to']}");
            $this->line("   - Tiene más páginas: " . ($response['pagination']['has_more_pages'] ? 'Sí' : 'No'));
            $this->line('');

            // 3. Mostrar productos de la página actual
            $this->info('3. Productos de la página actual:');
            
            foreach ($transformedData as $index => $producto) {
                $this->line("   Producto " . ($index + 1) . ":");
                $this->line("   - ID: {$producto['id']}");
                $this->line("   - Item: {$producto['item']}");
                $this->line("   - Nombre: {$producto['nombre_comercial']}");
                $this->line("   - Rubro: {$producto['rubro']}");
                $this->line("   - Carga Contenedor: {$producto['carga_contenedor']}");
                $this->line('');
            }

            // 4. Verificar estructura de respuesta
            $this->info('4. Estructura de respuesta:');
            $this->line("   ✅ Estructura correcta con 'data' y 'pagination'");
            $this->line("   - Data: " . count($response['data']) . " productos");
            $this->line("   - Pagination: " . count($response['pagination']) . " campos");
            
            // Verificar que no existe el objeto contenedor
            $primerProducto = $response['data'][0] ?? null;
            if ($primerProducto && !isset($primerProducto['contenedor'])) {
                $this->line("   - ✅ Objeto contenedor removido correctamente");
            } else {
                $this->line("   - ❌ Objeto contenedor aún presente");
            }
            
            $this->line('');

            // 5. Mostrar URLs de navegación
            $this->info('5. URLs de navegación:');
            if ($response['pagination']['prev_page_url']) {
                $this->line("   - Página anterior: {$response['pagination']['prev_page_url']}");
            } else {
                $this->line("   - Página anterior: No disponible (primera página)");
            }
            
            if ($response['pagination']['next_page_url']) {
                $this->line("   - Página siguiente: {$response['pagination']['next_page_url']}");
            } else {
                $this->line("   - Página siguiente: No disponible (última página)");
            }
            
            $this->line('');

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 