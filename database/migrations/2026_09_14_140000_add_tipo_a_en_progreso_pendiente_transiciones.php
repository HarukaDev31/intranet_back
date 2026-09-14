<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo A: en Configuración / Pruebas / Capacitación se pausa el contador
 * pasando a Pendiente. Idempotente.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('soporte_ti_estado_transiciones') || !Schema::hasTable('soporte_ti_estados')) {
            return;
        }

        $ids = DB::table('soporte_ti_estados')->pluck('id', 'codigo');
        if ($ids->isEmpty() || !isset($ids['pendiente'], $ids['en_progreso'])) {
            return;
        }

        $rows = array(
            array(
                'estado_origen_id' => (int) $ids['en_progreso'],
                'estado_destino_id' => (int) $ids['pendiente'],
                'rol' => 'staff',
                'tipo_solicitud' => 'A',
            ),
            array(
                'estado_origen_id' => (int) $ids['en_progreso'],
                'estado_destino_id' => (int) $ids['pendiente'],
                'rol' => 'analista',
                'tipo_solicitud' => 'A',
            ),
            array(
                'estado_origen_id' => (int) $ids['en_progreso'],
                'estado_destino_id' => (int) $ids['pendiente'],
                'rol' => 'pm',
                'tipo_solicitud' => 'A',
            ),
        );

        foreach ($rows as $row) {
            DB::table('soporte_ti_estado_transiciones')->insertOrIgnore($row);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('soporte_ti_estado_transiciones') || !Schema::hasTable('soporte_ti_estados')) {
            return;
        }

        $ids = DB::table('soporte_ti_estados')->pluck('id', 'codigo');
        if ($ids->isEmpty() || !isset($ids['pendiente'], $ids['en_progreso'])) {
            return;
        }

        DB::table('soporte_ti_estado_transiciones')
            ->where('tipo_solicitud', 'A')
            ->where('estado_origen_id', (int) $ids['en_progreso'])
            ->where('estado_destino_id', (int) $ids['pendiente'])
            ->whereIn('rol', array('staff', 'analista', 'pm'))
            ->delete();
    }
};
