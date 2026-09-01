<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class GrupoMenuAccesoCloner
{
    /**
     * Copia permisos de menú del grupo origen al destino para cada grupo_usuario del destino.
     */
    public static function syncGrupoUsuarioMenus(int $origenGrupoId, int $destinoGrupoId, int $empresaId, int $orgId): void
    {
        $destinoRows = DB::table('grupo_usuario')
            ->where('ID_Grupo', $destinoGrupoId)
            ->where('ID_Empresa', $empresaId)
            ->where('ID_Organizacion', $orgId)
            ->get(['ID_Grupo_Usuario']);

        foreach ($destinoRows as $row) {
            self::copyMenusToGrupoUsuario($origenGrupoId, (int) $row->ID_Grupo_Usuario, $empresaId, $orgId);
        }
    }

    public static function copyMenusToGrupoUsuario(int $origenGrupoId, int $destinoGrupoUsuarioId, int $empresaId, int $orgId): void
    {
        $origenGrupoUsuarioIds = DB::table('grupo_usuario')
            ->where('ID_Grupo', $origenGrupoId)
            ->where('ID_Empresa', $empresaId)
            ->where('ID_Organizacion', $orgId)
            ->pluck('ID_Grupo_Usuario');

        if ($origenGrupoUsuarioIds->isEmpty()) {
            return;
        }

        $menus = DB::table('menu_acceso')
            ->whereIn('ID_Grupo_Usuario', $origenGrupoUsuarioIds)
            ->get();

        if ($menus->isEmpty()) {
            return;
        }

        $merged = [];
        foreach ($menus as $menu) {
            $menuId = (int) $menu->ID_Menu;
            if (!isset($merged[$menuId])) {
                $merged[$menuId] = [
                    'Nu_Consultar' => (int) $menu->Nu_Consultar,
                    'Nu_Agregar' => (int) $menu->Nu_Agregar,
                    'Nu_Editar' => (int) $menu->Nu_Editar,
                    'Nu_Eliminar' => (int) $menu->Nu_Eliminar,
                ];
                continue;
            }

            $merged[$menuId]['Nu_Consultar'] = max($merged[$menuId]['Nu_Consultar'], (int) $menu->Nu_Consultar);
            $merged[$menuId]['Nu_Agregar'] = max($merged[$menuId]['Nu_Agregar'], (int) $menu->Nu_Agregar);
            $merged[$menuId]['Nu_Editar'] = max($merged[$menuId]['Nu_Editar'], (int) $menu->Nu_Editar);
            $merged[$menuId]['Nu_Eliminar'] = max($merged[$menuId]['Nu_Eliminar'], (int) $menu->Nu_Eliminar);
        }

        DB::table('menu_acceso')->where('ID_Grupo_Usuario', $destinoGrupoUsuarioId)->delete();

        foreach ($merged as $menuId => $permisos) {
            DB::table('menu_acceso')->insert([
                'ID_Empresa' => $empresaId,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $destinoGrupoUsuarioId,
                'Nu_Consultar' => $permisos['Nu_Consultar'],
                'Nu_Agregar' => $permisos['Nu_Agregar'],
                'Nu_Editar' => $permisos['Nu_Editar'],
                'Nu_Eliminar' => $permisos['Nu_Eliminar'],
            ]);
        }
    }
}
