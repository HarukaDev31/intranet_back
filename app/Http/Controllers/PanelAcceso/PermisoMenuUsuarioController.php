<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermisoMenuUsuarioController extends Controller
{
    /**
     * Obtener todos los menús de usuarios externos con acceso del usuario dado.
     * GET /api/panel-acceso/menu-usuario/{userId}
     */
    public function getMenuPorUsuario($userId)
    {
        try {
            $usuario = DB::table('users')->where('id', $userId)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            // Obtener todos los menús activos (Nu_Activo = 0 significa activo)
            $menus = DB::table('menu_user AS MNU')
                ->select(
                    'MNU.ID_Menu',
                    'MNU.ID_Padre',
                    'MNU.Nu_Orden',
                    'MNU.No_Menu',
                    'MNU.No_Menu_Url',
                    'MNU.Txt_Url_Video',
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM menu_user_access AS ACC
                        WHERE ACC.ID_Menu = MNU.ID_Menu
                        AND ACC.user_id = ' . (int) $userId . '
                    ) AS tiene_acceso')
                )
                ->where('MNU.Nu_Activo', 0)
                ->orderBy('MNU.ID_Padre')
                ->orderBy('MNU.Nu_Orden')
                ->get();

            // Obtener nombres de menús padre
            $padreIds = $menus->where('ID_Padre', '!=', 0)->pluck('ID_Padre')->unique();
            $padres = DB::table('menu_user')
                ->whereIn('ID_Menu', $padreIds)
                ->pluck('No_Menu', 'ID_Menu');

            $data = $menus->map(function ($m) use ($padres) {
                return [
                    'ID_Menu'       => $m->ID_Menu,
                    'ID_Padre'      => $m->ID_Padre,
                    'Nu_Orden'      => $m->Nu_Orden,
                    'No_Menu'       => $m->No_Menu,
                    'No_Menu_Url'   => $m->No_Menu_Url,
                    'Txt_Url_Video' => $m->Txt_Url_Video,
                    'nombre_padre'  => $m->ID_Padre ? ($padres[$m->ID_Padre] ?? 'General') : null,
                    'tiene_acceso'  => (int) $m->tiene_acceso > 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'usuario' => [
                    'id'    => $usuario->id,
                    'email' => $usuario->email,
                    'nombre_completo' => trim(($usuario->name ?? '') . ' ' . ($usuario->lastname ?? '')),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('PermisoMenuUsuarioController@getMenuPorUsuario: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener permisos'], 500);
        }
    }

    /**
     * Guardar permisos de menú para un usuario externo.
     * POST /api/panel-acceso/menu-usuario
     * Body: { user_id: int, menu_ids: int[] }
     */
    public function guardarPermisos(Request $request)
    {
        try {
            $request->validate([
                'user_id'  => 'required|integer|exists:users,id',
                'menu_ids' => 'present|array',
                'menu_ids.*' => 'integer',
            ]);

            $userId  = (int) $request->user_id;
            $menuIds = array_map('intval', $request->menu_ids ?? []);

            DB::beginTransaction();

            // Eliminar permisos actuales
            DB::table('menu_user_access')->where('user_id', $userId)->delete();

            // Insertar los nuevos
            if (!empty($menuIds)) {
                $inserts = [];
                foreach ($menuIds as $menuId) {
                    $inserts[] = [
                        'ID_Menu'    => $menuId,
                        'user_id'    => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('menu_user_access')->insert($inserts);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Permisos guardados exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PermisoMenuUsuarioController@guardarPermisos: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al guardar permisos'], 500);
        }
    }
}
