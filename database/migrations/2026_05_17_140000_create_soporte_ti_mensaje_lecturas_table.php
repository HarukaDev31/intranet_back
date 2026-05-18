<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoporteTiMensajeLecturasTable extends Migration
{
  public function up()
  {
    Schema::create('soporte_ti_mensaje_lecturas', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->unsignedBigInteger('mensaje_id');
      $table->unsignedBigInteger('usuario_id');
      $table->timestamp('leido_en');

      $table->unique(['mensaje_id', 'usuario_id'], 'st_mensaje_lectura_unique');
      $table->foreign('mensaje_id')->references('id')->on('soporte_ti_mensajes')->onDelete('cascade');
      $table->index(['mensaje_id', 'usuario_id']);
    });
  }

  public function down()
  {
    Schema::dropIfExists('soporte_ti_mensaje_lecturas');
  }
}
