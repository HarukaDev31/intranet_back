<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContenedorSeguimientoRowSyncTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('contenedor_seguimiento_row_sync')) {
            return;
        }

        Schema::create('contenedor_seguimiento_row_sync', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('id_contenedor');
            $table->string('tabla', 32);
            $table->unsignedInteger('id_cotizacion')->nullable();
            $table->unsignedInteger('id_proveedor')->nullable();
            $table->string('data_hash', 64);
            $table->dateTime('ultima_actualizacion');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(
                ['id_contenedor', 'tabla', 'id_cotizacion', 'id_proveedor'],
                'uniq_csrs_contenedor_tabla_keys'
            );
            $table->index(['id_contenedor', 'tabla'], 'idx_csrs_contenedor_tabla');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contenedor_seguimiento_row_sync');
    }
}
