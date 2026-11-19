<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orden del sidebar al login:
 * - menu_orden_grupo: orden por cargo (rol)
 * - menu_orden_usuario: si existe, pisa el del cargo solo para ese usuario
 */
class CreateMenuOrdenRolUsuarioTables extends Migration
{
    private const URL = 'panel-acceso/orden-menus';

    public function up()
    {
        if (!Schema::hasTable('menu_orden_grupo')) {
            Schema::create('menu_orden_grupo', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('ID_Grupo');
                $table->unsignedInteger('ID_Menu');
                $table->unsignedInteger('Nu_Orden')->default(0);
                $table->timestamps();
                $table->unique(['ID_Grupo', 'ID_Menu'], 'menu_orden_grupo_unique');
                $table->index('ID_Grupo');
            });
        }

        if (!Schema::hasTable('menu_orden_usuario')) {
            Schema::create('menu_orden_usuario', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('ID_Usuario');
                $table->unsignedInteger('ID_Menu');
                $table->unsignedInteger('Nu_Orden')->default(0);
                $table->timestamps();
                $table->unique(['ID_Usuario', 'ID_Menu'], 'menu_orden_usuario_unique');
                $table->index('ID_Usuario');
            });
        }

        $this->seedMenuItem();
    }

    public function down()
    {
        $menuId = DB::table('menu')->where('url_intranet_v2', self::URL)->value('ID_Menu');
        if ($menuId) {
            DB::table('menu_acceso')->where('ID_Menu', $menuId)->delete();
            DB::table('menu')->where('ID_Menu', $menuId)->delete();
        }

        Schema::dropIfExists('menu_orden_usuario');
        Schema::dropIfExists('menu_orden_grupo');
    }

    private function seedMenuItem()
    {
        if (!Schema::hasTable('menu') || !Schema::hasTable('menu_acceso')) {
            return;
        }

        $permisosMenu = DB::table('menu')
            ->where('url_intranet_v2', 'panel-acceso/permisos')
            ->where('ID_Organizacion', 1)
            ->orderBy('ID_Menu')
            ->first();
        if (!$permisosMenu) {
            return;
        }

        $menuId = DB::table('menu')->where('url_intranet_v2', self::URL)->value('ID_Menu');
        if (!$menuId) {
            $orden = ((int) DB::table('menu')->where('ID_Padre', $permisosMenu->ID_Padre)->max('Nu_Orden')) + 1;
            $menuId = DB::table('menu')->insertGetId([
                'ID_Organizacion' => $permisosMenu->ID_Organizacion,
                'ID_Padre' => $permisosMenu->ID_Padre,
                'Nu_Orden' => $orden,
                'No_Menu' => 'Orden Menú',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:bars-arrow-down',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'Menu Order',
                'url_intranet_v2' => self::URL,
                'show_father' => 1,
            ]);
        }

        $accesos = DB::table('menu_acceso')->where('ID_Menu', $permisosMenu->ID_Menu)->get();
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
                'Nu_Agregar' => 1,
                'Nu_Editar' => 1,
                'Nu_Eliminar' => 0,
            ]);
        }
    }
}
