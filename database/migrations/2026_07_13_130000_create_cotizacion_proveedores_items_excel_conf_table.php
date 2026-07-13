<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contenedor_consolidado_cotizacion_proveedores_items_excel_conf', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cotizacion');
            $table->unsignedInteger('id_proveedor');
            $table->string('initial_name')->nullable();
            $table->string('tipo_producto', 64)->default('GENERAL');
            $table->json('caracteristicas')->nullable();
            $table->decimal('confirmacion_qty', 12, 2)->nullable();
            $table->decimal('confirmacion_precio', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_cotizacion', 'fk_cccp_items_excel_conf_cotizacion')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion');
            $table->foreign('id_proveedor', 'fk_cccp_items_excel_conf_proveedor')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion_proveedores');
            $table->index('id_proveedor', 'idx_cccp_items_excel_conf_proveedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_cotizacion_proveedores_items_excel_conf');
    }
};
