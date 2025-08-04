<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('imports_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->integer('cantidad_rows');
            $table->string('tipo_importacion'); // 'cursos' o 'cotizaciones'
            $table->integer('empresa_id');
            $table->integer('usuario_id');
            $table->json('estadisticas')->nullable(); // Para guardar stats del proceso
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports_clientes');
    }
}; 