<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita el ítem de menú "Contador landing": ahora se gestiona
 * desde un modal en landing/leads (tab Curso).
 */
class RemoveLandingCountdownMenuItem extends Migration
{
    private const MENU_URL = 'landing/countdown';

    public function up()
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $menuIds = DB::table('menu')->where('url_intranet_v2', self::MENU_URL)->pluck('ID_Menu');
        if ($menuIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('menu_acceso')) {
            DB::table('menu_acceso')->whereIn('ID_Menu', $menuIds)->delete();
        }

        DB::table('menu')->whereIn('ID_Menu', $menuIds)->delete();
    }

    public function down()
    {
        // No se recrea el menú: el flujo oficial es el modal en landing/leads.
    }
}
