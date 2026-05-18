<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPrioridadToSoporteTiSolicitudes extends Migration
{
  public function up()
  {
    Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
      if (!Schema::hasColumn('soporte_ti_solicitudes', 'prioridad')) {
        $table->unsignedTinyInteger('prioridad')->default(2)->after('titulo');
      }
    });
  }

  public function down()
  {
    if (Schema::hasColumn('soporte_ti_solicitudes', 'prioridad')) {
      Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
        $table->dropColumn('prioridad');
      });
    }
  }
}
