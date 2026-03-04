<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuExternoController extends Controller
{
    /**
     * Listado de todos los menús externos con nombre del padre y total de usuarios con acceso.
     * GET /api/panel-acceso/menus-externos
     */
    public function index()
    {
        try {
            $menus = DB::select("
                SELECT
                    m.ID_Menu        AS id,
                    m.ID_Padre       AS id_padre,
                    p.No_Menu        AS padre_nombre,
                    m.No_Menu        AS nombre,
                    m.Txt_Css_Icons  AS icono,
                    m.No_Menu_Url    AS ruta,
                    m.Nu_Orden       AS orden,
                    m.Nu_Activo      AS nu_activo,
                    m.Txt_Url_Video  AS url_video,
                    m.show_father    AS show_father,
                    (SELECT COUNT(*) FROM menu_user_access mua WHERE mua.ID_Menu = m.ID_Menu) AS total_usuarios
                FROM menu_user m
                LEFT JOIN menu_user p ON p.ID_Menu = m.ID_Padre
                ORDER BY m.ID_Padre ASC, m.Nu_Orden ASC, m.ID_Menu ASC
            ");

            $data = array_map(function ($m) {
                return [
                    'id'              => $m->id,
                    'id_padre'        => $m->id_padre,
                    'padre_nombre'    => $m->padre_nombre ?? null,
                    'nombre'          => $m->nombre,
                    'icono'           => $m->icono,
                    'ruta'            => $m->ruta,
                    'orden'           => $m->orden,
                    // Nu_Activo=0 significa activo en la BD (igual que menús internos)
                    'activo'          => $m->nu_activo == 0 ? 1 : 0,
                    'url_video'       => $m->url_video,
                    'show_father'     => $m->show_father,
                    'total_usuarios'  => (int) $m->total_usuarios,
                ];
            }, $menus);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('MenuExternoController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar menús externos'], 500);
        }
    }

    /**
     * Crear menú externo.
     * POST /api/panel-acceso/menus-externos
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nombre'      => 'required|string|max:50',
                'id_padre'    => 'required|integer|min:0',
                'orden'       => 'required|integer|min:0',
                'icono'       => 'nullable|string|max:30',
                'ruta'        => 'nullable|string|max:100',
                'activo'      => 'required|boolean',
                'url_video'   => 'nullable|string|max:500',
                'show_father' => 'nullable|boolean',
            ]);

            // Nu_Activo invertido: activo=true → Nu_Activo=0
            $nuActivo = $request->boolean('activo') ? 0 : 1;

            $id = DB::table('menu_user')->insertGetId([
                'ID_Padre'            => $request->id_padre,
                'Nu_Orden'            => $request->orden,
                'No_Menu'             => $request->nombre,
                'Txt_Css_Icons'       => $request->icono ?? '',
                'No_Menu_Url'         => $request->ruta ?? '',
                'Nu_Activo'           => $nuActivo,
                'Txt_Url_Video'       => $request->url_video ?? null,
                'show_father'         => $request->boolean('show_father') ? 1 : 0,
                'Nu_Separador'        => 0,
                'Nu_Seguridad'        => 0,
                'Nu_Tipo_Sistema'     => 0,
                'No_Class_Controller' => '',
                'No_Menu_China'       => null,
                'url_intranet_v2'     => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Menú externo creado exitosamente', 'data' => ['id' => $id]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MenuExternoController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear menú externo'], 500);
        }
    }

    /**
     * Actualizar menú externo.
     * PUT /api/panel-acceso/menus-externos/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre'      => 'required|string|max:50',
                'id_padre'    => 'required|integer|min:0',
                'orden'       => 'required|integer|min:0',
                'icono'       => 'nullable|string|max:30',
                'ruta'        => 'nullable|string|max:100',
                'activo'      => 'required|boolean',
                'url_video'   => 'nullable|string|max:500',
                'show_father' => 'nullable|boolean',
            ]);

            $existe = DB::table('menu_user')->where('ID_Menu', $id)->first();
            if (!$existe) {
                return response()->json(['success' => false, 'message' => 'Menú no encontrado'], 404);
            }

            if ((int) $request->id_padre === (int) $id) {
                return response()->json(['success' => false, 'message' => 'Un menú no puede ser su propio padre'], 422);
            }

            $nuActivo = $request->boolean('activo') ? 0 : 1;

            DB::table('menu_user')->where('ID_Menu', $id)->update([
                'ID_Padre'        => $request->id_padre,
                'Nu_Orden'        => $request->orden,
                'No_Menu'         => $request->nombre,
                'Txt_Css_Icons'   => $request->icono ?? '',
                'No_Menu_Url'     => $request->ruta ?? '',
                'Nu_Activo'       => $nuActivo,
                'Txt_Url_Video'   => $request->url_video ?? null,
                'show_father'     => $request->boolean('show_father') ? 1 : 0,
                'updated_at'      => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Menú externo actualizado exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MenuExternoController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar menú externo'], 500);
        }
    }

    /**
     * Eliminar menú externo (cascade FK a menu_user_access).
     * DELETE /api/panel-acceso/menus-externos/{id}
     */
    public function destroy($id)
    {
        try {
            $existe = DB::table('menu_user')->where('ID_Menu', $id)->first();
            if (!$existe) {
                return response()->json(['success' => false, 'message' => 'Menú no encontrado'], 404);
            }

            // menu_user_access tiene onDelete cascade, no necesita delete manual
            DB::table('menu_user')->where('ID_Menu', $id)->delete();

            return response()->json(['success' => true, 'message' => 'Menú externo eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('MenuExternoController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar menú externo'], 500);
        }
    }

    /**
     * Usuarios que tienen acceso a este menú.
     * GET /api/panel-acceso/menus-externos/{id}/usuarios
     */
    public function getUsuariosConAcceso($id)
    {
        try {
            $usuarios = DB::table('menu_user_access AS mua')
                ->join('users AS u', 'u.id', '=', 'mua.user_id')
                ->select('u.id', 'u.name', 'u.lastname', 'u.email')
                ->where('mua.ID_Menu', $id)
                ->orderBy('u.name')
                ->get();

            $data = $usuarios->map(function ($u) {
                return [
                    'id'             => $u->id,
                    'nombre_completo'=> trim(($u->name ?? '') . ' ' . ($u->lastname ?? '')),
                    'email'          => $u->email,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('MenuExternoController@getUsuariosConAcceso: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener usuarios'], 500);
        }
    }
}
