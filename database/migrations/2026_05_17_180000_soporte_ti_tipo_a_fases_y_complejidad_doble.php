<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SoporteTiTipoAFasesYComplejidadDoble extends Migration
{
  public function up()
  {
    Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
      $table->string('complejidad_pm', 40)->default('Por definir')->after('criticidad');
      $table->string('complejidad_analista', 40)->default('Por definir')->after('complejidad_pm');
    });

    if (!Schema::hasColumn('soporte_ti_sla_horas', 'ambito')) {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->string('ambito', 32)->default('general')->after('tipo_solicitud');
      });
    }

    DB::table('soporte_ti_sla_horas')
      ->where('tipo_solicitud', 'A')
      ->update(array('ambito' => 'analista_config'));

    Schema::create('soporte_ti_fase_horas_a', function (Blueprint $table) {
      $table->id();
      $table->string('fase_codigo', 32);
      $table->unsignedSmallInteger('horas')->default(8);
      $table->timestamps();
      $table->unique('fase_codigo', 'soporte_ti_fase_horas_a_fase_unique');
    });

    $now = now();
    $fases = array(
      array('fase_codigo' => 'levantamiento', 'horas' => 16),
      array('fase_codigo' => 'maqueta', 'horas' => 24),
      array('fase_codigo' => 'pruebas', 'horas' => 16),
      array('fase_codigo' => 'capacitacion', 'horas' => 8),
    );
    foreach ($fases as $f) {
      DB::table('soporte_ti_fase_horas_a')->insert(array(
        'fase_codigo' => $f['fase_codigo'],
        'horas' => $f['horas'],
        'created_at' => $now,
        'updated_at' => $now,
      ));
    }
  }

  public function down()
  {
    Schema::dropIfExists('soporte_ti_fase_horas_a');

    if (Schema::hasColumn('soporte_ti_sla_horas', 'ambito')) {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->dropColumn('ambito');
      });
    }

    Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
      $table->dropColumn(array('complejidad_pm', 'complejidad_analista'));
    });
  }
}
