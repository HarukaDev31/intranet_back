<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRY-26-0003 (#2349/#2358): módulos del menú final de Encargado China (maqueta de Miguel) que aún no existían:
 * «Seguimiento», «Estado de contenedores» y «Configuración». Por ahora son pantallas vacías; su contenido se define después.
 */
return new class extends Migration
{
    /** @var array<int, array{name: string, name_china: string, url: string, icon: string, group: string}> */
    private const ITEMS = [
        ['name' => 'Seguimiento', 'name_china' => 'Tracking', 'url' => 'seguimiento-china', 'icon' => 'fa fa-users', 'group' => 'Encargado China'],
        ['name' => 'Estado de contenedores', 'name_china' => 'Container status', 'url' => 'estado-contenedores-china', 'icon' => 'fa fa-ship', 'group' => 'Encargado China'],
        ['name' => 'Configuración', 'name_china' => 'Settings', 'url' => 'configuracion-china', 'icon' => 'fa fa-cog', 'group' => 'Encargado China'],
    ];

    public function up(): void
    {
        foreach (self::ITEMS as $item) {
            $menuId = DB::table('menu')->where('url_intranet_v2', $item['url'])->value('ID_Menu');
            if (!$menuId) {
                $menuId = DB::table('menu')->insertGetId([
                    'ID_Padre' => 0,
                    'Nu_Orden' => ((int) DB::table('menu')->where('ID_Padre', 0)->max('Nu_Orden')) + 1,
                    'No_Menu' => $item['name'],
                    'No_Menu_Url' => $item['url'],
                    'No_Class_Controller' => 'ChinaBillingController',
                    'Txt_Css_Icons' => $item['icon'],
                    'Nu_Separador' => 0,
                    'Nu_Seguridad' => 0,
                    'Nu_Activo' => 0,
                    'Nu_Tipo_Sistema' => 0,
                    'Txt_Url_Video' => null,
                    'No_Menu_China' => $item['name_china'],
                    'show_father' => 1,
                    'url_intranet_v2' => $item['url'],
                ]);
            }
            $this->grant((int) $menuId, $item['group']);
        }

        $this->clearCache();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->whereIn('url_intranet_v2', array_column(self::ITEMS, 'url'))->pluck('ID_Menu');
        DB::table('menu_acceso')->whereIn('ID_Menu', $menuIds)->delete();
        DB::table('menu')->whereIn('ID_Menu', $menuIds)->delete();

        $this->clearCache();
    }

    /**
     * Todos los miembros del grupo (un usuario puede tener una fila por organización).
     */
    private function grant(int $menuId, string $group): void
    {
        $members = DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->where('g.No_Grupo', $group)
            ->get(['gu.ID_Grupo_Usuario', 'g.ID_Empresa']);

        foreach ($members as $member) {
            $exists = DB::table('menu_acceso')
                ->where('ID_Menu', $menuId)
                ->where('ID_Grupo_Usuario', $member->ID_Grupo_Usuario)
                ->exists();
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
    }

    private function clearCache(): void
    {
        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }
};
