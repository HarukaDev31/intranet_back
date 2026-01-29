<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViaticosPagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('viaticos_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('viatico_id');
            $table->string('concepto');
            $table->decimal('monto', 12, 2);
            // Campos del documento/imagen subido
            $table->string('file_path')->nullable()->comment('Ruta relativa en storage, ej: viaticos_pagos/xxx.pdf');
            $table->string('file_url', 500)->nullable()->comment('URL pública del archivo si se genera');
            $table->unsignedBigInteger('file_size')->nullable()->comment('Tamaño en bytes');
            $table->string('file_original_name', 255)->nullable()->comment('Nombre original del archivo');
            $table->string('file_mime_type', 100)->nullable()->comment('MIME type, ej: application/pdf, image/jpeg');
            $table->string('file_extension', 20)->nullable()->comment('Extensión del archivo');
            $table->timestamps();

            $table->foreign('viatico_id')
                ->references('id')
                ->on('viaticos')
                ->onDelete('cascade');
            $table->index('viatico_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('viaticos_pagos');
    }
}
