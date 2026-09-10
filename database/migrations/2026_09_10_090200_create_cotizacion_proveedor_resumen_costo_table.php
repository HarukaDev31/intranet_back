<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCotizacionProveedorResumenCostoTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cotizacion_proveedor_resumen_costo')) {
            return;
        }

        Schema::create('cotizacion_proveedor_resumen_costo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('organizacion_id');
            $table->unsignedBigInteger('id_cotizacion_proveedor_resumen');
            $table->string('concepto', 150);
            $table->unsignedInteger('orden')->default(0);
            $table->decimal('valor', 12, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('organizacion_id')
                ->references('ID_Organizacion')
                ->on('organizacion')
                ->onDelete('restrict');
            $table->foreign('id_cotizacion_proveedor_resumen', 'fk_cprc_id_resumen')
                ->references('id')
                ->on('cotizacion_proveedor_resumen')
                ->onDelete('cascade');

            $table->index(['id_cotizacion_proveedor_resumen', 'orden'], 'idx_cprc_resumen_orden');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizacion_proveedor_resumen_costo');
    }
}
