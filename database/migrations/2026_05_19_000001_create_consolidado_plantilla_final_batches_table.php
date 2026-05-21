<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoPlantillaFinalBatchesTable extends Migration
{
    public function up()
    {
        Schema::create('consolidado_plantilla_final_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_contenedor');
            $table->unsignedInteger('clientes_excel')->default(0);
            $table->unsignedInteger('clientes_completados')->default(0);
            $table->unsignedInteger('clientes_error')->default(0);
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->string('estado', 20)->default('PENDING');
            $table->unsignedInteger('created_by')->nullable();
            $table->string('plantilla_url')->nullable();
            $table->string('zip_path')->nullable();
            $table->string('nombre_plantilla')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->index(['id_contenedor', 'estado']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('consolidado_plantilla_final_batches');
    }
}
