<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Administración y China no veían Pago a Proveedor: el alta anterior
 * buscaba el grupo "Admin" y no incluía ContenedorAlmacen.
 */
class GrantPagoProveedorMenuAdminChina extends Migration
{
    public function up()
    {
        if (!DB::getSchemaBuilder()->hasTable('menu') || !DB::getSchemaBuilder()->hasTable('menu_acceso')) {
            return;
        }

        $padreId = DB::table('menu')
            ->where('No_Menu', 'Pago a Proveedor')
            ->where('ID_Padre', 0)
            ->value('ID_Menu');
        $solicitudesId = DB::table('menu')
            ->where('url_intranet_v2', 'pago-proveedor/solicitudes')
            ->value('ID_Menu');

        if (!$padreId || !$solicitudesId) {
            return;
        }

        $this->conceder([(int) $padreId, (int) $solicitudesId], ['Administración', 'ContenedorAlmacen']);

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }

    public function down()
    {
        $menuIds = DB::table('menu')
            ->where(function ($query) {
                $query->where(function ($padre) {
                    $padre->where('No_Menu', 'Pago a Proveedor')->where('ID_Padre', 0);
                })->orWhere('url_intranet_v2', 'pago-proveedor/solicitudes');
            })
            ->pluck('ID_Menu');

        if ($menuIds->isEmpty()) {
            return;
        }

        $grupos = DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->whereIn('g.No_Grupo', ['Administración', 'ContenedorAlmacen'])
            ->where('g.ID_Organizacion', 1)
            ->pluck('gu.ID_Grupo_Usuario');

        if ($grupos->isEmpty()) {
            return;
        }

        DB::table('menu_acceso')
            ->whereIn('ID_Menu', $menuIds)
            ->whereIn('ID_Grupo_Usuario', $grupos)
            ->delete();

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }

    /**
     * @param  array<int, int>  $menuIds
     * @param  array<int, string>  $grupos
     */
    private function conceder(array $menuIds, array $grupos)
    {
        $miembros = DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->whereIn('g.No_Grupo', $grupos)
            ->where('g.ID_Organizacion', 1)
            ->get(['gu.ID_Grupo_Usuario', 'g.ID_Empresa']);

        foreach ($miembros as $miembro) {
            foreach ($menuIds as $menuId) {
                $existe = DB::table('menu_acceso')
                    ->where('ID_Menu', $menuId)
                    ->where('ID_Grupo_Usuario', $miembro->ID_Grupo_Usuario)
                    ->exists();
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
    }
}
