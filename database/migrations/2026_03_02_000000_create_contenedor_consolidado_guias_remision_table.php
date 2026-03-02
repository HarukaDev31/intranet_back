<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la migración falló previamente (p. ej. por FK incompatible), la tabla puede existir sin constraint.
        // Esta tabla es nueva, así que es seguro recrearla.
        Schema::dropIfExists('contenedor_consolidado_guias_remision');

        Schema::create('contenedor_consolidado_guias_remision', function (Blueprint $table) {
            $table->id();
            // contenedor_consolidado_cotizacion.id es INT en este proyecto (ver tablas que ya referencian esa PK)
            $table->integer('quotation_id');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('quotation_id');
            $table->foreign('quotation_id')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_guias_remision');
    }
};

