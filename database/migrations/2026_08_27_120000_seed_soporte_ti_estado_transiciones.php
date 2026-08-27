<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de transiciones de estado Soporte TI (grafo feliz + confirmación solicitante).
 * Idempotente: no inserta si ya hay filas.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('soporte_ti_estado_transiciones') || ! Schema::hasTable('soporte_ti_estados')) {
            return;
        }

        if (DB::table('soporte_ti_estado_transiciones')->exists()) {
            return;
        }

        $ids = DB::table('soporte_ti_estados')->pluck('id', 'codigo');
        if ($ids->isEmpty()) {
            return;
        }

        $nowPairs = array();
        $add = function ($from, $to, $rol, $tipo = null) use (&$nowPairs, $ids) {
            if (! isset($ids[$from], $ids[$to])) {
                return;
            }
            $nowPairs[] = array(
                'estado_origen_id' => (int) $ids[$from],
                'estado_destino_id' => (int) $ids[$to],
                'rol' => $rol,
                'tipo_solicitud' => $tipo,
            );
        };

        // Tipo B — staff
        $add('pendiente', 'en_progreso', 'staff', 'B');
        $add('en_progreso', 'hecho', 'staff', 'B');
        $add('hecho', 'desplegado', 'staff', 'B');
        $add('observado', 'en_progreso', 'staff', 'B');
        $add('desplegado', 'hecho', 'staff', 'B');

        // Tipo A — staff / pm
        $add('pendiente', 'en_maqueta', 'staff', 'A');
        $add('pendiente', 'en_maqueta', 'pm', 'A');
        $add('en_maqueta', 'en_progreso', 'staff', 'A');
        $add('en_maqueta', 'en_progreso', 'pm', 'A');
        $add('en_progreso', 'desplegado', 'staff', 'A');
        $add('en_progreso', 'desplegado', 'pm', 'A');
        $add('observado', 'en_progreso', 'staff', 'A');
        $add('observado', 'en_progreso', 'pm', 'A');
        $add('desplegado', 'en_progreso', 'staff', 'A');

        // Solicitante (confirmación)
        $add('desplegado', 'operativo', 'solicitante', null);
        $add('desplegado', 'observado', 'solicitante', null);

        // Analista (flujo B / ejecución)
        $add('pendiente', 'en_progreso', 'analista', 'B');
        $add('en_progreso', 'hecho', 'analista', 'B');
        $add('hecho', 'desplegado', 'analista', 'B');
        $add('observado', 'en_progreso', 'analista', 'B');
        $add('en_progreso', 'desplegado', 'analista', 'A');

        foreach ($nowPairs as $row) {
            DB::table('soporte_ti_estado_transiciones')->insertOrIgnore($row);
        }
    }

    public function down()
    {
        if (! Schema::hasTable('soporte_ti_estado_transiciones')) {
            return;
        }
        DB::table('soporte_ti_estado_transiciones')->delete();
    }
};
