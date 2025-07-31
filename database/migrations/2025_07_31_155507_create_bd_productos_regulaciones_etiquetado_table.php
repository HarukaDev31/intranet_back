<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBdProductosRegulacionesEtiquetadoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bd_productos_regulaciones_etiquetado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_rubro')->comment('ID del rubro (referencia a bd_productos)');
            $table->text('observaciones')->nullable()->comment('Observaciones sobre el etiquetado');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');
            
            // Índices
            $table->index('id_rubro', 'idx_id_rubro');
            $table->index('created_at', 'idx_created_at');
            
            // Foreign key
            $table->foreign('id_rubro', 'fk_etiquetado_rubro')
                  ->references('id')
                  ->on('bd_productos')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bd_productos_regulaciones_etiquetado');
    }
}
