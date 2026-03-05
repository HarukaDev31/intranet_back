<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos asociados a una cotización de la calculadora de importación.
     */
    public function up(): void
    {
        Schema::create('calculadora_importacion_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_calculadora_importacion');
            $table->string('file_url', 500);
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->foreign('id_calculadora_importacion', 'calc_imp_docs_calc_imp_id_fk')
                ->references('id')
                ->on('calculadora_importacion')
                ->onDelete('cascade');
            $table->index('id_calculadora_importacion', 'calc_imp_docs_calc_imp_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculadora_importacion_documentos');
    }
};
