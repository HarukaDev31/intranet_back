<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de system_configs para el contador de landing consolidado
 * + ítem de menú "Contador landing" (hermano de landing/leads).
 */
class AddLandingConsolidadoCountdownConfigAndMenu extends Migration
{
    private const MENU_URL = 'landing/countdown';
    private const CONFIG_KEY = 'landing_consolidado_countdown_ends_at';
    private const DEFAULT_ENDS_AT = '2026-10-06T23:59:59-05:00';

    public function up()
    {
        $this->seedConfig();
        $this->seedMenu();
    }

    public function down()
    {
        if (Schema::hasTable('menu') && Schema::hasTable('menu_acceso')) {
            $menuId = DB::table('menu')->where('url_intranet_v2', self::MENU_URL)->value('ID_Menu');
            if ($menuId) {
                DB::table('menu_acceso')->where('ID_Menu', $menuId)->delete();
                DB::table('menu')->where('ID_Menu', $menuId)->delete();
            }
        }

        if (Schema::hasTable('system_configs')) {
            DB::table('system_configs')->where('key', self::CONFIG_KEY)->delete();
        }
    }

    private function seedConfig()
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
            'description' => 'Fecha/hora fin del contador de la landing consolidado (America/Lima)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedMenu()
    {
        if (!Schema::hasTable('menu') || !Schema::hasTable('menu_acceso')) {
            return;
        }

        $sibling = DB::table('menu')
            ->where('url_intranet_v2', 'landing/leads')
            ->orderBy('ID_Menu')
            ->first();

        if (!$sibling) {
            return;
        }

        $menuId = DB::table('menu')->where('url_intranet_v2', self::MENU_URL)->value('ID_Menu');
        if (!$menuId) {
            $maxOrden = (int) DB::table('menu')
                ->where('ID_Padre', $sibling->ID_Padre)
                ->max('Nu_Orden');

            $payload = [
                'ID_Padre' => $sibling->ID_Padre,
                'Nu_Orden' => $maxOrden + 1,
                'No_Menu' => 'Contador landing',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:clock',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'Contador landing',
                'url_intranet_v2' => self::MENU_URL,
                'show_father' => $sibling->show_father ?? 1,
            ];

            if (Schema::hasColumn('menu', 'ID_Organizacion') && isset($sibling->ID_Organizacion)) {
                $payload['ID_Organizacion'] = $sibling->ID_Organizacion;
            }

            $menuId = DB::table('menu')->insertGetId($payload);
        }

        $accesos = DB::table('menu_acceso')->where('ID_Menu', $sibling->ID_Menu)->get();
        foreach ($accesos as $acceso) {
            $existe = DB::table('menu_acceso')
                ->where('ID_Menu', $menuId)
                ->where('ID_Grupo_Usuario', $acceso->ID_Grupo_Usuario)
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('menu_acceso')->insert([
                'ID_Empresa' => $acceso->ID_Empresa,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $acceso->ID_Grupo_Usuario,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => 0,
                'Nu_Editar' => 1,
                'Nu_Eliminar' => 0,
            ]);
        }
    }
}
