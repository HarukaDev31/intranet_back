<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTramiteAduanaDocumentosTable extends Migration
{
    public function up()
    {
        Schema::create('tramite_aduana_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tramite');
            $table->string('categoria', 20);
            $table->string('nombre_documento');
            $table->string('extension', 20);
            $table->unsignedBigInteger('peso')->default(0);
            $table->string('nombre_original');
            $table->string('ruta');
            $table->timestamps();

            $table->foreign('id_tramite')
                ->references('id')
                ->on('consolidado_cotizacion_aduana_tramites')
                ->onDelete('cascade');

            $table->index('id_tramite');
            $table->index('categoria');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tramite_aduana_documentos');
    }
}
