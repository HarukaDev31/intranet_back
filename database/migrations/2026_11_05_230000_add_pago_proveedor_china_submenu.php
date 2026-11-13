<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones China entra a Pago a Proveedor por "Pagos en yuanes", en lugar de Solicitudes.
 */
return new class extends Migration
{
    private const GRUPO = 'ContenedorAlmacen';

    private const URL = 'pago-proveedor/pagos-yuanes';

    public function up(): void
    {
        $padreId = DB::table('menu')->where('No_Menu', 'Pago a Proveedor')->where('ID_Padre', 0)->value('ID_Menu');
        if (!$padreId) {
            return;
        }

        $menuId = DB::table('menu')->where('url_intranet_v2', self::URL)->value('ID_Menu')
            ?: DB::table('menu')->insertGetId([
                'ID_Padre' => $padreId,
                'Nu_Orden' => 5,
                'No_Menu' => 'Pagos en yuanes',
                'No_Menu_Url' => self::URL,
                'No_Class_Controller' => 'PagoProveedorCotizacionController',
                'Txt_Css_Icons' => 'fa fa-file-text-o',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'Yuan payments',
                'show_father' => 1,
                'url_intranet_v2' => self::URL,
            ]);

        $miembros = $this->miembros();
        $this->conceder((int) $menuId, $miembros);

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
        $menuIds = DB::table('menu')->where('url_intranet_v2', self::URL)->pluck('ID_Menu');
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
