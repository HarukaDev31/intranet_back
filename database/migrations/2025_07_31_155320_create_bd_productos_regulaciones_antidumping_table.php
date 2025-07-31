<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBdProductosRegulacionesAntidumpingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bd_productos_regulaciones_antidumping', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_rubro')->comment('ID del rubro (referencia a bd_productos)');
            $table->text('descripcion_producto')->comment('Descripción del producto');
            $table->string('partida', 50)->comment('Partida arancelaria');
            $table->decimal('antidumping', 10, 2)->nullable()->comment('Valor del antidumping');
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');
            
            // Índices
            $table->index('id_rubro', 'idx_id_rubro');
            $table->index('partida', 'idx_partida');
            $table->index('created_at', 'idx_created_at');
            $table->index('antidumping', 'idx_antidumping');
            
            // Foreign key
            $table->foreign('id_rubro', 'fk_antidumping_rubro')
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
        Schema::dropIfExists('bd_productos_regulaciones_antidumping');
    }
}
