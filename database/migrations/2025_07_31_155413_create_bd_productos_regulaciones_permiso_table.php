<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBdProductosRegulacionesPermisoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bd_productos_regulaciones_permiso', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_entidad_reguladora')->comment('ID de la entidad reguladora');
            $table->string('nombre', 255)->comment('Nombre del permiso');
            $table->text('c_permiso')->nullable()->comment('Código del permiso');
            $table->decimal('c_tramitador', 10, 2)->nullable()->comment('Código del tramitador');
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');
            
            // Índices
            $table->index('id_entidad_reguladora', 'idx_id_entidad_reguladora');
            $table->index('c_tramitador', 'idx_c_tramitador');
            $table->index('nombre', 'idx_nombre');
            $table->index('created_at', 'idx_created_at');
            
            // Foreign key
            $table->foreign('id_entidad_reguladora', 'fk_permiso_entidad')
                  ->references('id')
                  ->on('bd_entidades_reguladoras')
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
        Schema::dropIfExists('bd_productos_regulaciones_permiso');
    }
}
