<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Pago a Proveedor» (Cotizaciones y Solicitudes) es del perfil Cotizador (Ventas): los usuarios Cotizador creados
 * después de la migración original (p. ej. cotizaciones6) no tenían el menú. Se concede a todos los miembros del rol.
 */
return new class extends Migration
{
    public function up(): void
    {
        $padreId = DB::table('menu')->where('No_Menu', 'Pago a Proveedor')->where('ID_Padre', 0)->value('ID_Menu');
        $hijos = DB::table('menu')->whereIn('url_intranet_v2', ['pago-proveedor/cotizaciones', 'pago-proveedor/solicitudes'])->pluck('ID_Menu')->all();
        if (!$padreId || $hijos === []) {
            return;
        }
        $menus = array_merge([(int) $padreId], array_map('intval', $hijos));

        $miembros = DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->where('g.No_Grupo', 'Cotizador')
            ->get(['gu.ID_Grupo_Usuario', 'g.ID_Empresa']);

        foreach ($miembros as $miembro) {
            foreach ($menus as $menuId) {
                $existe = DB::table('menu_acceso')->where('ID_Menu', $menuId)->where('ID_Grupo_Usuario', $miembro->ID_Grupo_Usuario)->exists();
                if ($existe) {
                    continue;
                }
                DB::table('menu_acceso')->insert([
                    'ID_Empresa' => $miembro->ID_Empresa ?: 1,
                    'ID_Menu' => $menuId,
                    'ID_Grupo_Usuario' => $miembro->ID_Grupo_Usuario,
                    'Nu_Consultar' => 1,
                    'Nu_Agregar' => 1,
                    'Nu_Editar' => 1,
                    'Nu_Eliminar' => 1,
                ]);
            }
        }

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }

    public function down(): void
    {
    }
};
