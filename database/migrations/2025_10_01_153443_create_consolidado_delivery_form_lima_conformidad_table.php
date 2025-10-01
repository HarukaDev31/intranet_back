<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoDeliveryFormLimaConformidadTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consolidado_delivery_form_lima_conformidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consolidado_delivery_form_lima_id');
            $table->integer('id_cotizacion');
            $table->unsignedInteger('id_contenedor');
            $table->string('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->string('file_original_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Claves foráneas
            $table->foreign('consolidado_delivery_form_lima_id', 'fk_lima_conformidad_form')
                  ->references('id')
                  ->on('consolidado_delivery_form_lima')
                  ->onDelete('cascade');
                  
            $table->foreign('id_cotizacion', 'fk_lima_conformidad_cotizacion')
                  ->references('id')
                  ->on('contenedor_consolidado_cotizacion')
                  ->onDelete('cascade');
                  
            $table->foreign('id_contenedor', 'fk_lima_conformidad_contenedor')
                  ->references('id')
                  ->on('carga_consolidada_contenedor')
                  ->onDelete('cascade');
            
            // Índices
            $table->index('consolidado_delivery_form_lima_id', 'idx_lima_conformidad_form');
            $table->index('id_cotizacion', 'idx_lima_conformidad_cotizacion');
            $table->index('id_contenedor', 'idx_lima_conformidad_contenedor');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consolidado_delivery_form_lima_conformidad');
    }
}
