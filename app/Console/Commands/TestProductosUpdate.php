<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\ProductoImportadoExcel;
use App\Models\BaseDatos\EntidadReguladora;
use App\Models\BaseDatos\ProductoRubro;

class TestProductosUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:productos-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la funcionalidad de actualización de productos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Actualización de Productos ===');

        // Verificar que existen productos
        $producto = ProductoImportadoExcel::first();
        if (!$producto) {
            $this->error('No hay productos en la base de datos');
            return 1;
        }

        $this->info("Producto encontrado: ID {$producto->id} - {$producto->nombre_comercial}");

        // Verificar que existen entidades y tipos de etiquetado
        $entidad = EntidadReguladora::first();
        $tipoEtiquetado = ProductoRubro::first();

        if (!$entidad) {
            $this->warn('No hay entidades reguladoras en la base de datos');
        } else {
            $this->info("Entidad encontrada: ID {$entidad->id} - {$entidad->nombre}");
        }

        if (!$tipoEtiquetado) {
            $this->warn('No hay tipos de etiquetado en la base de datos');
        } else {
            $this->info("Tipo de etiquetado encontrado: ID {$tipoEtiquetado->id} - {$tipoEtiquetado->nombre}");
        }

        // Probar actualización básica
        $this->info("\n--- Probando actualización básica ---");
        $updateData = [
            'link' => 'https://www.alibaba.com/product-detail/test',
            'arancel_sunat' => '10%',
            'arancel_tlc' => '0%',
            'correlativo' => 'NO',
            'antidumping' => 'NO',
            'tipo_producto' => 'LIBRE',
            'etiquetado' => 'NORMAL',
            'doc_especial' => 'NO',
            'tiene_observaciones' => false
        ];

        try {
            $producto->update($updateData);
            $this->info('✓ Actualización básica exitosa');
        } catch (\Exception $e) {
            $this->error('✗ Error en actualización básica: ' . $e->getMessage());
        }

        // Probar actualización con antidumping
        $this->info("\n--- Probando actualización con antidumping ---");
        $updateData = [
            'antidumping' => 'SI',
            'antidumping_value' => '25.5%'
        ];

        try {
            $producto->update($updateData);
            $this->info('✓ Actualización con antidumping exitosa');
        } catch (\Exception $e) {
            $this->error('✗ Error en actualización con antidumping: ' . $e->getMessage());
        }

        // Probar actualización con producto restringido (si hay entidad)
        if ($entidad) {
            $this->info("\n--- Probando actualización con producto restringido ---");
            $updateData = [
                'tipo_producto' => 'RESTRINGIDO',
                'entidad_id' => $entidad->id
            ];

            try {
                $producto->update($updateData);
                $this->info('✓ Actualización con producto restringido exitosa');
            } catch (\Exception $e) {
                $this->error('✗ Error en actualización con producto restringido: ' . $e->getMessage());
            }
        }

        // Probar actualización con etiquetado especial (si hay tipo de etiquetado)
        if ($tipoEtiquetado) {
            $this->info("\n--- Probando actualización con etiquetado especial ---");
            $updateData = [
                'etiquetado' => 'ESPECIAL',
                'tipo_etiquetado_id' => $tipoEtiquetado->id
            ];

            try {
                $producto->update($updateData);
                $this->info('✓ Actualización con etiquetado especial exitosa');
            } catch (\Exception $e) {
                $this->error('✗ Error en actualización con etiquetado especial: ' . $e->getMessage());
            }
        }

        // Probar actualización con observaciones
        $this->info("\n--- Probando actualización con observaciones ---");
        $updateData = [
            'tiene_observaciones' => true,
            'observaciones' => 'Producto requiere certificación adicional para importación.'
        ];

        try {
            $producto->update($updateData);
            $this->info('✓ Actualización con observaciones exitosa');
        } catch (\Exception $e) {
            $this->error('✗ Error en actualización con observaciones: ' . $e->getMessage());
        }

        // Mostrar estado final del producto
        $producto->refresh();
        $this->info("\n--- Estado final del producto ---");
        $this->info("ID: {$producto->id}");
        $this->info("Nombre: {$producto->nombre_comercial}");
        $this->info("Link: {$producto->link}");
        $this->info("Arancel SUNAT: {$producto->arancel_sunat}");
        $this->info("Arancel TLC: {$producto->arancel_tlc}");
        $this->info("Antidumping: {$producto->antidumping}");
        $this->info("Valor Antidumping: {$producto->antidumping_value}");
        $this->info("Tipo Producto: {$producto->tipo_producto}");
        $this->info("Entidad ID: {$producto->entidad_id}");
        $this->info("Etiquetado: {$producto->etiquetado}");
        $this->info("Tipo Etiquetado ID: {$producto->tipo_etiquetado_id}");
        $this->info("Doc Especial: {$producto->doc_especial}");
        $this->info("Tiene Observaciones: " . ($producto->tiene_observaciones ? 'Sí' : 'No'));
        $this->info("Observaciones: {$producto->observaciones}");

        $this->info("\n=== Prueba completada ===");
        return 0;
    }
}
