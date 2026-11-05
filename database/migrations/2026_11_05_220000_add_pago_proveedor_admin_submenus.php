<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Administración entra a Pago a Proveedor por sus propios submenús, en lugar de Solicitudes.
 */
return new class extends Migration
{
    private const GRUPO = 'Administración';

    private const SUBMENUS = [
        ['nombre' => 'Aprobación de pagos', 'url' => 'pago-proveedor/aprobacion-pagos', 'orden' => 3],
        ['nombre' => 'Pagos en dólares', 'url' => 'pago-proveedor/pagos-dolares', 'orden' => 4],
    ];

    public function up(): void
    {
        $padreId = DB::table('menu')->where('No_Menu', 'Pago a Proveedor')->where('ID_Padre', 0)->value('ID_Menu');
        if (!$padreId) {
            return;
        }

        $miembros = $this->miembros();
        foreach (self::SUBMENUS as $submenu) {
            $menuId = DB::table('menu')->where('url_intranet_v2', $submenu['url'])->value('ID_Menu')
                ?: DB::table('menu')->insertGetId([
                    'ID_Padre' => $padreId,
                    'Nu_Orden' => $submenu['orden'],
                    'No_Menu' => $submenu['nombre'],
                    'No_Menu_Url' => $submenu['url'],
                    'No_Class_Controller' => 'PagoProveedorCotizacionController',
                    'Txt_Css_Icons' => 'fa fa-file-text-o',
                    'Nu_Separador' => 0,
                    'Nu_Seguridad' => 0,
                    'Nu_Activo' => 0,
                    'Nu_Tipo_Sistema' => 0,
                    'Txt_Url_Video' => null,
                    'No_Menu_China' => null,
                    'show_father' => 1,
                    'url_intranet_v2' => $submenu['url'],
                ]);
            $this->conceder((int) $menuId, $miembros);
        }

        $solicitudesId = DB::table('menu')->where('url_intranet_v2', 'pago-proveedor/solicitudes')->value('ID_Menu');
        if ($solicitudesId) {
            DB::table('menu_acceso')
                ->where('ID_Menu', $solicitudesId)
                ->whereIn('ID_Grupo_Usuario', $miembros->pluck('ID_Grupo_Usuario'))
                ->delete();
        }

        $this->limpiarCache();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->whereIn('url_intranet_v2', array_column(self::SUBMENUS, 'url'))->pluck('ID_Menu');
        DB::table('menu_acceso')->whereIn('ID_Menu', $menuIds)->delete();
        DB::table('menu')->whereIn('ID_Menu', $menuIds)->delete();

        $solicitudesId = DB::table('menu')->where('url_intranet_v2', 'pago-proveedor/solicitudes')->value('ID_Menu');
        if ($solicitudesId) {
            $this->conceder((int) $solicitudesId, $this->miembros());
        }

        $this->limpiarCache();
    }

    private function miembros()
    {
        return DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->where('g.No_Grupo', self::GRUPO)
            ->where('g.ID_Organizacion', 1)
            ->get(['gu.ID_Grupo_Usuario', 'g.ID_Empresa']);
    }

    private function conceder(int $menuId, $miembros): void
    {
        foreach ($miembros as $miembro) {
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

    private function limpiarCache(): void
    {
        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }
};
