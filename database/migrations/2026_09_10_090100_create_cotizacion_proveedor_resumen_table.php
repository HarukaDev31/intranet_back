<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCotizacionProveedorResumenTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cotizacion_proveedor_resumen')) {
            return;
        }

        Schema::create('cotizacion_proveedor_resumen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('organizacion_id');
            $table->unsignedInteger('id_contenedor');
            $table->unsignedInteger('id_cotizacion');
            $table->unsignedInteger('id_proveedor');
            $table->string('producto', 150);
            $table->decimal('volumen_cbm', 10, 4)->nullable();
            $table->unsignedInteger('unidades')->nullable();
            $table->string('incoterm', 50)->nullable();
            $table->decimal('costo_unitario_estimado', 12, 4)->nullable();
            $table->decimal('inversion_total', 12, 2)->nullable();
            $table->string('moneda', 3)->default('USD');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('organizacion_id')
                ->references('ID_Organizacion')
                ->on('organizacion')
                ->onDelete('restrict');
            $table->foreign('id_proveedor', 'fk_cpr_id_proveedor')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion_proveedores')
                ->onDelete('cascade');

            $table->index(['id_contenedor', 'id_cotizacion'], 'idx_cpr_contenedor_cotizacion');
            $table->unique('id_proveedor', 'uniq_cpr_id_proveedor');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizacion_proveedor_resumen');
    }
}
