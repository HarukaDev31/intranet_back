<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoporteTiSolicitudEvidenciasTable extends Migration
{
    public function up()
    {
        Schema::create('soporte_ti_solicitud_evidencias', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_id');
            $table->unsignedBigInteger('mensaje_id')->nullable();
            $table->string('tipo', 16);
            $table->text('texto')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('nombre', 255)->nullable();
            $table->string('tamano', 32)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('solicitud_id')->references('id')->on('soporte_ti_solicitudes')->onDelete('cascade');
            $table->foreign('mensaje_id')->references('id')->on('soporte_ti_mensajes')->onDelete('set null');
            $table->index(['solicitud_id', 'orden']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('soporte_ti_solicitud_evidencias');
    }
}
