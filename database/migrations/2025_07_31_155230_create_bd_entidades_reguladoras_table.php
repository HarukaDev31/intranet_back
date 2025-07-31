<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBdEntidadesReguladorasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bd_entidades_reguladoras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255)->comment('Nombre de la entidad reguladora');
            $table->text('descripcion')->nullable();
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');
            
            // Índices
            $table->unique('nombre', 'uk_nombre');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bd_entidades_reguladoras');
    }
}
