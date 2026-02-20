<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MenuCatalogoController extends Controller
{
    private const TIPOS_PRIVILEGIO = [
        1 => 'Personal Probusiness',
        2 => 'Personal China',
        3 => 'Proveedor Externo',
        4 => 'Cliente',
        5 => 'Jefe China',
        6 => 'Almacen China',
    ];

    /**
     * Listado de todos los menús con nombre del padre.
     * GET /api/panel-acceso/menus
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
                    m.url_intranet_v2 AS ruta,
                    m.No_Menu_Url    AS ruta_legacy,
                    m.Nu_Orden       AS orden,
                    m.Nu_Activo      AS nu_activo,
                    m.Txt_Url_Video  AS url_video,
                    m.show_father    AS show_father,
                    m.Nu_Seguridad   AS seguridad,
                    (SELECT COUNT(DISTINCT gu.ID_Grupo)
                     FROM menu_acceso ma
                     JOIN grupo_usuario gu ON gu.ID_Grupo_Usuario = ma.ID_Grupo_Usuario
                     WHERE ma.ID_Menu = m.ID_Menu) AS total_roles
                FROM menu m
                LEFT JOIN menu p ON p.ID_Menu = m.ID_Padre
                ORDER BY m.ID_Padre ASC, m.Nu_Orden ASC, m.ID_Menu ASC
            ");

            $data = array_map(function ($m) {
                return [
                    'id'           => $m->id,
                    'id_padre'     => $m->id_padre,
                    'padre_nombre' => $m->padre_nombre ?? null,
                    'nombre'       => $m->nombre,
                    'icono'        => $m->icono,
                    'ruta'         => $m->ruta,
                    'ruta_legacy'  => $m->ruta_legacy,
                    'orden'        => $m->orden,
                    // Invertir: Nu_Activo=0 significa activo en la BD
                    'activo'       => $m->nu_activo == 0 ? 1 : 0,
                    'url_video'    => $m->url_video,
                    'show_father'  => $m->show_father,
                    'seguridad'    => $m->seguridad,
                    'total_roles'  => (int) $m->total_roles,
                ];
            }, $menus);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('MenuCatalogoController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar menús'], 500);
        }
    }

    /**
     * Crear menú.
     * POST /api/panel-acceso/menus
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nombre'      => 'required|string|max:100',
                'id_padre'    => 'required|integer|min:0',
                'orden'       => 'required|integer|min:0',
                'icono'       => 'nullable|string|max:255',
                'ruta'        => 'nullable|string|max:255',
                'activo'      => 'required|boolean',
                'url_video'   => 'nullable|string|max:500',
                'show_father' => 'nullable|boolean',
            ]);

            // Nu_Activo invertido: activo=true → Nu_Activo=0
            $nuActivo = $request->boolean('activo') ? 0 : 1;

            $id = DB::table('menu')->insertGetId([
                'ID_Padre'        => $request->id_padre,
                'Nu_Orden'        => $request->orden,
                'No_Menu'         => $request->nombre,
                'Txt_Css_Icons'   => $request->icono ?? '',
                'url_intranet_v2' => $request->ruta ?? '',
                'Nu_Activo'       => $nuActivo,
                'Txt_Url_Video'   => $request->url_video ?? null,
                'show_father'     => $request->boolean('show_father') ? 1 : 0,
                'Nu_Separador'    => 0,
                'Nu_Seguridad'    => 0,
                'Nu_Tipo_Sistema' => 0,
                'No_Menu_Url'     => '',
                'No_Class_Controller' => '',
                'No_Menu_China'   => '',
            ]);

            return response()->json(['success' => true, 'message' => 'Menú creado exitosamente', 'data' => ['id' => $id]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MenuCatalogoController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear menú'], 500);
        }
    }

    /**
     * Actualizar menú.
     * PUT /api/panel-acceso/menus/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre'      => 'required|string|max:100',
                'id_padre'    => 'required|integer|min:0',
                'orden'       => 'required|integer|min:0',
                'icono'       => 'nullable|string|max:255',
                'ruta'        => 'nullable|string|max:255',
                'activo'      => 'required|boolean',
                'url_video'   => 'nullable|string|max:500',
                'show_father' => 'nullable|boolean',
            ]);

            $existe = DB::table('menu')->where('ID_Menu', $id)->first();
            if (!$existe) {
                return response()->json(['success' => false, 'message' => 'Menú no encontrado'], 404);
            }

            // Prevenir ciclos: id_padre no puede ser el mismo menú
            if ((int) $request->id_padre === (int) $id) {
                return response()->json(['success' => false, 'message' => 'Un menú no puede ser su propio padre'], 422);
            }

            $nuActivo = $request->boolean('activo') ? 0 : 1;

            DB::table('menu')->where('ID_Menu', $id)->update([
                'ID_Padre'        => $request->id_padre,
                'Nu_Orden'        => $request->orden,
                'No_Menu'         => $request->nombre,
                'Txt_Css_Icons'   => $request->icono ?? '',
                'url_intranet_v2' => $request->ruta ?? '',
                'Nu_Activo'       => $nuActivo,
                'Txt_Url_Video'   => $request->url_video ?? null,
                'show_father'     => $request->boolean('show_father') ? 1 : 0,
            ]);

            return response()->json(['success' => true, 'message' => 'Menú actualizado exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MenuCatalogoController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar menú'], 500);
        }
    }

    /**
     * Eliminar menú (primero desasigna de menu_acceso).
     * DELETE /api/panel-acceso/menus/{id}
     */
    public function destroy($id)
    {
        try {
            $existe = DB::table('menu')->where('ID_Menu', $id)->first();
            if (!$existe) {
                return response()->json(['success' => false, 'message' => 'Menú no encontrado'], 404);
            }

            DB::transaction(function () use ($id) {
                // 1. Desasignar de todos los roles
                DB::table('menu_acceso')->where('ID_Menu', $id)->delete();
                // 2. Eliminar el menú
                DB::table('menu')->where('ID_Menu', $id)->delete();
            });

            return response()->json(['success' => true, 'message' => 'Menú eliminado y desasignado de todos los roles']);
        } catch (\Exception $e) {
            Log::error('MenuCatalogoController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar menú'], 500);
        }
    }

    /**
     * Grupos/cargos que tienen acceso a este menú.
     * GET /api/panel-acceso/menus/{id}/grupos
     */
    public function getGruposConAcceso($id)
    {
        try {
            $grupos = DB::select("
                SELECT DISTINCT
                    g.ID_Grupo                    AS id,
                    g.No_Grupo                    AS cargo,
                    g.Nu_Tipo_Privilegio_Acceso   AS privilegio
                FROM menu_acceso ma
                JOIN grupo_usuario gu ON gu.ID_Grupo_Usuario = ma.ID_Grupo_Usuario
                JOIN grupo g ON g.ID_Grupo = gu.ID_Grupo
                WHERE ma.ID_Menu = ?
                ORDER BY g.No_Grupo
            ", [$id]);

            $data = array_map(function ($g) {
                return [
                    'id'                => $g->id,
                    'cargo'             => $g->cargo,
                    'privilegio'        => $g->privilegio,
                    'privilegio_nombre' => self::TIPOS_PRIVILEGIO[$g->privilegio] ?? 'Desconocido',
                ];
            }, $grupos);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('MenuCatalogoController@getGruposConAcceso: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener roles'], 500);
        }
    }

    /**
     * Subir icono personalizado.
     * POST /api/panel-acceso/menus/icon-upload
     */
    public function uploadIcon(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:png,jpg,jpeg,gif,svg+xml,svg|max:512',
            ]);

            $file = $request->file('file');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/menu-icons', $filename);

            $url = '/storage/menu-icons/' . $filename;

            return response()->json(['success' => true, 'url' => $url]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Archivo inválido. Use PNG, JPG, GIF o SVG (máx. 512KB)'], 422);
        } catch (\Exception $e) {
            Log::error('MenuCatalogoController@uploadIcon: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al subir el icono'], 500);
        }
    }
}
