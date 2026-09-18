<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCotizacionProveedorArchivoIaTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cotizacion_proveedor_archivo_ia')) {
            return;
        }

        Schema::create('cotizacion_proveedor_archivo_ia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('organizacion_id');
            $table->unsignedInteger('id_contenedor');
            $table->unsignedInteger('id_cotizacion');
            $table->unsignedInteger('id_proveedor');
            $table->string('archivo_path', 500);
            $table->string('archivo_nombre_original', 255)->nullable();
            $table->enum('estado', ['pendiente', 'procesado', 'error'])->default('pendiente');
            $table->longText('data_extraida_json')->nullable();
            $table->boolean('editado_manualmente')->default(false);
            $table->unsignedInteger('id_usuario_creador')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('organizacion_id')
                ->references('ID_Organizacion')
                ->on('organizacion')
                ->onDelete('restrict');
            $table->foreign('id_proveedor', 'fk_cpai_id_proveedor')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion_proveedores')
                ->onDelete('cascade');

            $table->index(['id_contenedor', 'id_cotizacion'], 'idx_cpai_contenedor_cotizacion');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizacion_proveedor_archivo_ia');
    }
}
