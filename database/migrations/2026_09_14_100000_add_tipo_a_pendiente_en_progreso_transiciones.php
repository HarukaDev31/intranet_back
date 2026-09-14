<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo A: el analista puede pasar de Pendiente a En progreso
 * (tras definir complejidad analista). Idempotente.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('soporte_ti_estado_transiciones') || !Schema::hasTable('soporte_ti_estados')) {
            return;
        }

        $ids = DB::table('soporte_ti_estados')->pluck('id', 'codigo');
        if ($ids->isEmpty() || !isset($ids['pendiente'], $ids['en_progreso'], $ids['en_maqueta'])) {
            return;
        }

        $rows = array(
            array(
                'estado_origen_id' => (int) $ids['pendiente'],
                'estado_destino_id' => (int) $ids['en_progreso'],
                'rol' => 'staff',
                'tipo_solicitud' => 'A',
            ),
            array(
                'estado_origen_id' => (int) $ids['pendiente'],
                'estado_destino_id' => (int) $ids['en_progreso'],
                'rol' => 'analista',
                'tipo_solicitud' => 'A',
            ),
            array(
                'estado_origen_id' => (int) $ids['en_maqueta'],
                'estado_destino_id' => (int) $ids['en_progreso'],
                'rol' => 'analista',
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
        if ($ids->isEmpty() || !isset($ids['pendiente'], $ids['en_progreso'], $ids['en_maqueta'])) {
            return;
        }

        DB::table('soporte_ti_estado_transiciones')
            ->where('tipo_solicitud', 'A')
            ->where('estado_destino_id', (int) $ids['en_progreso'])
            ->where(function ($q) use ($ids) {
                $q->where(function ($q2) use ($ids) {
                    $q2->where('estado_origen_id', (int) $ids['pendiente'])
                        ->whereIn('rol', array('staff', 'analista'));
                })->orWhere(function ($q2) use ($ids) {
                    $q2->where('estado_origen_id', (int) $ids['en_maqueta'])
                        ->where('rol', 'analista');
                });
            })
            ->delete();
    }
};
