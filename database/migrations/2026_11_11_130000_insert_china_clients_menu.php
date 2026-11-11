<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú "Clientes" de Almacén China (socios e importadores).
 */
return new class extends Migration
{
    private const URL = 'clientes-china';

    public function up(): void
    {
        $menuId = DB::table('menu')->where('url_intranet_v2', self::URL)->value('ID_Menu');
        if (!$menuId) {
            $menuId = DB::table('menu')->insertGetId([
                'ID_Padre' => 0,
                'Nu_Orden' => ((int) DB::table('menu')->where('ID_Padre', 0)->max('Nu_Orden')) + 1,
                'No_Menu' => 'Clientes',
                'No_Menu_Url' => self::URL,
                'No_Class_Controller' => 'ChinaClientController',
                'Txt_Css_Icons' => 'fa fa-users',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'Clients',
                'show_father' => 1,
                'url_intranet_v2' => self::URL,
            ]);
        }

        $members = DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->where('g.No_Grupo', 'ContenedorAlmacen')
            ->get(['gu.ID_Grupo_Usuario', 'g.ID_Empresa']);
        foreach ($members as $member) {
            $exists = DB::table('menu_acceso')->where('ID_Menu', $menuId)->where('ID_Grupo_Usuario', $member->ID_Grupo_Usuario)->exists();
            if ($exists) {
                continue;
            }
            DB::table('menu_acceso')->insert([
                'ID_Empresa' => $member->ID_Empresa ?: 1,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $member->ID_Grupo_Usuario,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => 1,
                'Nu_Editar' => 1,
                'Nu_Eliminar' => 1,
            ]);
        }

        $this->clearCache();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->where('url_intranet_v2', self::URL)->pluck('ID_Menu');
        DB::table('menu_acceso')->whereIn('ID_Menu', $menuIds)->delete();
        DB::table('menu')->whereIn('ID_Menu', $menuIds)->delete();
        $this->clearCache();
    }

    private function clearCache(): void
    {
        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }
};
