<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de system_configs para el contador de la landing curso.
 * El mantenedor vive en un modal de landing/leads (sin ítem de menú).
 */
class AddLandingConsolidadoCountdownConfigAndMenu extends Migration
{
    private const CONFIG_KEY = 'landing_consolidado_countdown_ends_at';
    private const DEFAULT_ENDS_AT = '2026-10-06T23:59:59-05:00';

    public function up()
    {
        if (!Schema::hasTable('system_configs')) {
            return;
        }

        $exists = DB::table('system_configs')->where('key', self::CONFIG_KEY)->exists();
        if ($exists) {
            return;
        }

        $now = now();
        DB::table('system_configs')->insert([
            'key' => self::CONFIG_KEY,
            'value' => self::DEFAULT_ENDS_AT,
            'description' => 'Fecha/hora fin del contador de la landing curso (America/Lima)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        if (Schema::hasTable('system_configs')) {
            DB::table('system_configs')->where('key', self::CONFIG_KEY)->delete();
        }
    }
}
