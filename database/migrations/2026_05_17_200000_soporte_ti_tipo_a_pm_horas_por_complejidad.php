<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo A: horas de fases PM (sin configuración) por complejidad que asigna el PM.
 */
class SoporteTiTipoAPmHorasPorComplejidad extends Migration
{
  public function up()
  {
    if (!Schema::hasColumn('soporte_ti_sla_horas', 'ambito')) {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->string('ambito', 32)->default('general')->after('tipo_solicitud');
      });
    }

    try {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->dropUnique('soporte_ti_sla_horas_tipo_crit_unique');
      });
    } catch (\Exception $e) {
      // índice ya eliminado o nombre distinto
    }

    try {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->unique(
          array('tipo_solicitud', 'criticidad', 'ambito'),
          'soporte_ti_sla_horas_tipo_crit_amb_unique'
        );
      });
    } catch (\Exception $e) {
      // índice compuesto ya existe
    }

    DB::table('soporte_ti_sla_horas')
      ->where('tipo_solicitud', 'A')
      ->where(function ($q) {
        $q->whereNull('ambito')->orWhere('ambito', 'general');
      })
      ->update(array('ambito' => 'analista_config'));

    $sumFases = 64;
    if (Schema::hasTable('soporte_ti_fase_horas_a')) {
      $sumFases = (int) DB::table('soporte_ti_fase_horas_a')->sum('horas');
      if ($sumFases <= 0) {
        $sumFases = 64;
      }
    }

    $pmDefaults = array(
      'Baja' => (int) max(1, round($sumFases * 0.75)),
      'Media' => $sumFases,
      'Alta' => (int) max(1, round($sumFases * 1.25)),
      'Máxima' => (int) max(1, round($sumFases * 1.5)),
    );

    $now = now();
    foreach ($pmDefaults as $crit => $horas) {
      $exists = DB::table('soporte_ti_sla_horas')
        ->where('tipo_solicitud', 'A')
        ->where('criticidad', $crit)
        ->where('ambito', 'pm_fases')
        ->exists();
      if ($exists) {
        continue;
      }
      DB::table('soporte_ti_sla_horas')->insert(array(
        'tipo_solicitud' => 'A',
        'ambito' => 'pm_fases',
        'criticidad' => $crit,
        'horas' => $horas,
        'created_at' => $now,
        'updated_at' => $now,
      ));
    }
  }

  public function down()
  {
    DB::table('soporte_ti_sla_horas')
      ->where('tipo_solicitud', 'A')
      ->where('ambito', 'pm_fases')
      ->delete();

    try {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->dropUnique('soporte_ti_sla_horas_tipo_crit_amb_unique');
      });
    } catch (\Exception $e) {
    }

    try {
      Schema::table('soporte_ti_sla_horas', function (Blueprint $table) {
        $table->unique(array('tipo_solicitud', 'criticidad'), 'soporte_ti_sla_horas_tipo_crit_unique');
      });
    } catch (\Exception $e) {
    }
  }
}
