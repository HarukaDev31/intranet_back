<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horas PM tipo A: matriz fase × complejidad (la que asigna el PM).
 */
class SoporteTiFaseHorasAPorComplejidad extends Migration
{
  public function up()
  {
    if (!Schema::hasTable('soporte_ti_fase_horas_a')) {
      return;
    }

    if (!Schema::hasColumn('soporte_ti_fase_horas_a', 'criticidad')) {
      Schema::table('soporte_ti_fase_horas_a', function (Blueprint $table) {
        $table->string('criticidad', 20)->default('Media')->after('fase_codigo');
      });
    }

    try {
      Schema::table('soporte_ti_fase_horas_a', function (Blueprint $table) {
        $table->dropUnique('soporte_ti_fase_horas_a_fase_unique');
      });
    } catch (\Exception $e) {
    }

    try {
      Schema::table('soporte_ti_fase_horas_a', function (Blueprint $table) {
        $table->unique(
          array('fase_codigo', 'criticidad'),
          'soporte_ti_fase_horas_a_fase_crit_unique'
        );
      });
    } catch (\Exception $e) {
    }

    $complejidades = array('Baja', 'Media', 'Alta', 'Máxima');
    $mult = array('Baja' => 0.75, 'Media' => 1.0, 'Alta' => 1.25, 'Máxima' => 1.5);
    $fases = array('levantamiento', 'maqueta', 'pruebas', 'capacitacion');
    $defaults = array(
      'levantamiento' => 16,
      'maqueta' => 24,
      'pruebas' => 16,
      'capacitacion' => 8,
    );

    $existentes = DB::table('soporte_ti_fase_horas_a')->get();
    $basePorFase = array();
    foreach ($fases as $fc) {
      $basePorFase[$fc] = isset($defaults[$fc]) ? (int) $defaults[$fc] : 8;
    }
    foreach ($existentes as $row) {
      $fc = $row->fase_codigo;
      if (in_array($fc, $fases, true)) {
        $basePorFase[$fc] = (int) $row->horas;
      }
    }

    DB::table('soporte_ti_fase_horas_a')->truncate();

    $now = now();
    foreach ($complejidades as $crit) {
      foreach ($fases as $fc) {
        $horas = (int) max(1, round($basePorFase[$fc] * $mult[$crit]));
        DB::table('soporte_ti_fase_horas_a')->insert(array(
          'fase_codigo' => $fc,
          'criticidad' => $crit,
          'horas' => $horas,
          'created_at' => $now,
          'updated_at' => $now,
        ));
      }
    }
  }

  public function down()
  {
    if (!Schema::hasTable('soporte_ti_fase_horas_a')) {
      return;
    }

    $fases = array('levantamiento', 'maqueta', 'pruebas', 'capacitacion');
    $rows = DB::table('soporte_ti_fase_horas_a')
      ->where('criticidad', 'Media')
      ->whereIn('fase_codigo', $fases)
      ->get();

    DB::table('soporte_ti_fase_horas_a')->truncate();

    $now = now();
    foreach ($rows as $row) {
      DB::table('soporte_ti_fase_horas_a')->insert(array(
        'fase_codigo' => $row->fase_codigo,
        'horas' => (int) $row->horas,
        'created_at' => $now,
        'updated_at' => $now,
      ));
    }

    if (Schema::hasColumn('soporte_ti_fase_horas_a', 'criticidad')) {
      try {
        Schema::table('soporte_ti_fase_horas_a', function (Blueprint $table) {
          $table->dropUnique('soporte_ti_fase_horas_a_fase_crit_unique');
        });
      } catch (\Exception $e) {
      }
      Schema::table('soporte_ti_fase_horas_a', function (Blueprint $table) {
        $table->dropColumn('criticidad');
      });
      try {
        Schema::table('soporte_ti_fase_horas_a', function (Blueprint $table) {
          $table->unique('fase_codigo', 'soporte_ti_fase_horas_a_fase_unique');
        });
      } catch (\Exception $e) {
      }
    }
  }
}
