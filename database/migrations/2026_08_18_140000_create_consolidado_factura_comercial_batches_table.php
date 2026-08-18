<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidado_factura_comercial_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_contenedor');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->string('estado', 20)->default('PENDING');
            $table->unsignedInteger('created_by')->nullable();
            $table->string('file_path')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->index(['id_contenedor', 'estado']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidado_factura_comercial_batches');
    }
};
