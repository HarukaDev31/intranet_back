<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Grupo;

class GrupoController extends Controller
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
     * Listado de grupos con filtros opcionales.
     * GET /api/panel-acceso/grupos
     */
    public function index(Request $request)
    {
        try {
            $query = Grupo::query()
                ->select('grupo.*', 'emp.No_Empresa', 'org.No_Organizacion')
                ->join('empresa AS emp', 'emp.ID_Empresa', '=', 'grupo.ID_Empresa')
                ->join('organizacion AS org', 'org.ID_Organizacion', '=', 'grupo.ID_Organizacion');

            if ($request->filled('empresa_id')) {
                $query->where('grupo.ID_Empresa', $request->empresa_id);
            }

            if ($request->filled('org_id')) {
                $query->where('grupo.ID_Organizacion', $request->org_id);
            }

            if ($request->filled('search')) {
                $query->where('grupo.No_Grupo', 'like', '%' . $request->search . '%');
            }

            // Excluir grupo root (ID_Grupo=1) para usuarios no-root
            $user = auth()->user();
            if ($user->ID_Grupo != 1) {
                $query->where('grupo.ID_Grupo', '!=', 1);
            }

            $grupos = $query->orderBy('grupo.ID_Grupo', 'desc')->get();

            $data = $grupos->map(function ($g) {
                return [
                    'id'            => $g->ID_Grupo,
                    'id_empresa'    => $g->ID_Empresa,
                    'id_org'        => $g->ID_Organizacion,
                    'empresa'       => $g->No_Empresa,
                    'organizacion'  => $g->No_Organizacion,
                    'cargo'         => $g->No_Grupo,
                    'descripcion'   => $g->No_Grupo_Descripcion,
                    'privilegio'    => $g->Nu_Tipo_Privilegio_Acceso,
                    'privilegio_nombre' => self::TIPOS_PRIVILEGIO[$g->Nu_Tipo_Privilegio_Acceso] ?? 'Desconocido',
                    'notificacion'  => $g->Nu_Notificacion,
                    'estado'        => $g->Nu_Estado,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('GrupoController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar grupos'], 500);
        }
    }

    /**
     * Obtener un grupo por ID.
     * GET /api/panel-acceso/grupos/{id}
     */
    public function show($id)
    {
        try {
            $grupo = Grupo::with(['empresa', 'organizacion'])->find($id);

            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Grupo no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'            => $grupo->ID_Grupo,
                    'id_empresa'    => $grupo->ID_Empresa,
                    'id_org'        => $grupo->ID_Organizacion,
                    'empresa'       => $grupo->empresa ? $grupo->empresa->No_Empresa : null,
                    'organizacion'  => $grupo->organizacion ? $grupo->organizacion->No_Organizacion : null,
                    'cargo'         => $grupo->No_Grupo,
                    'descripcion'   => $grupo->No_Grupo_Descripcion,
                    'privilegio'    => $grupo->Nu_Tipo_Privilegio_Acceso,
                    'notificacion'  => $grupo->Nu_Notificacion,
                    'estado'        => $grupo->Nu_Estado,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GrupoController@show: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener grupo'], 500);
        }
    }

    /**
     * Crear nuevo grupo.
     * POST /api/panel-acceso/grupos
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'id_empresa'    => 'required|integer',
                'id_org'        => 'required|integer',
                'cargo'         => 'required|string|max:30',
                'descripcion'   => 'nullable|string|max:100',
                'privilegio'    => 'required|integer|in:1,2,3,4,5,6',
                'estado'        => 'required|integer|in:0,1',
            ]);

            $existe = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM grupo WHERE ID_Empresa = ? AND ID_Organizacion = ? AND No_Grupo = ? LIMIT 1',
                [$request->id_empresa, $request->id_org, $request->cargo]
            );

            if ($existe->existe > 0) {
                return response()->json(['success' => false, 'message' => 'El cargo ya existe'], 422);
            }

            $grupo = Grupo::create([
                'ID_Empresa'                => $request->id_empresa,
                'ID_Organizacion'           => $request->id_org,
                'No_Grupo'                  => $request->cargo,
                'No_Grupo_Descripcion'      => $request->descripcion,
                'Nu_Tipo_Privilegio_Acceso' => $request->privilegio,
                'Nu_Notificacion'           => 1,
                'Nu_Estado'                 => $request->estado,
            ]);

            return response()->json(['success' => true, 'message' => 'Cargo creado exitosamente', 'data' => ['id' => $grupo->ID_Grupo]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('GrupoController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear cargo'], 500);
        }
    }

    /**
     * Actualizar grupo.
     * PUT /api/panel-acceso/grupos/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'id_empresa'    => 'required|integer',
                'id_org'        => 'required|integer',
                'cargo'         => 'required|string|max:30',
                'descripcion'   => 'nullable|string|max:100',
                'privilegio'    => 'required|integer|in:1,2,3,4,5,6',
                'estado'        => 'required|integer|in:0,1',
            ]);

            $grupo = Grupo::find($id);
            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Cargo no encontrado'], 404);
            }

            // Validar duplicado solo si cambió org o nombre
            if ($grupo->ID_Organizacion != $request->id_org || $grupo->No_Grupo != $request->cargo) {
                $existe = DB::selectOne(
                    'SELECT COUNT(*) AS existe FROM grupo WHERE ID_Empresa = ? AND ID_Organizacion = ? AND No_Grupo = ? LIMIT 1',
                    [$request->id_empresa, $request->id_org, $request->cargo]
                );
                if ($existe->existe > 0) {
                    return response()->json(['success' => false, 'message' => 'El cargo ya existe'], 422);
                }
            }

            $grupo->update([
                'ID_Empresa'                => $request->id_empresa,
                'ID_Organizacion'           => $request->id_org,
                'No_Grupo'                  => $request->cargo,
                'No_Grupo_Descripcion'      => $request->descripcion,
                'Nu_Tipo_Privilegio_Acceso' => $request->privilegio,
                'Nu_Estado'                 => $request->estado,
            ]);

            return response()->json(['success' => true, 'message' => 'Cargo actualizado exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('GrupoController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar cargo'], 500);
        }
    }

    /**
     * Eliminar grupo.
     * DELETE /api/panel-acceso/grupos/{id}
     */
    public function destroy($id)
    {
        try {
            if ($id == 1) {
                return response()->json(['success' => false, 'message' => 'No se puede eliminar el grupo ROOT'], 422);
            }

            $tieneUsuarios = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM grupo_usuario WHERE ID_Grupo = ? LIMIT 1',
                [$id]
            );

            if ($tieneUsuarios->existe > 0) {
                return response()->json(['success' => false, 'message' => 'El cargo tiene usuario(s) asignado(s)'], 422);
            }

            $grupo = Grupo::find($id);
            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Cargo no encontrado'], 404);
            }

            $grupo->delete();

            return response()->json(['success' => true, 'message' => 'Cargo eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('GrupoController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar cargo'], 500);
        }
    }

    /**
     * Cambiar estado de notificación del grupo.
     * PATCH /api/panel-acceso/grupos/{id}/notificacion
     */
    public function updateNotificacion(Request $request, $id)
    {
        try {
            $request->validate([
                'notificacion' => 'required|integer|in:0,1',
            ]);

            $grupo = Grupo::find($id);
            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Cargo no encontrado'], 404);
            }

            $grupo->update(['Nu_Notificacion' => $request->notificacion]);

            return response()->json(['success' => true, 'message' => 'Notificación actualizada']);
        } catch (\Exception $e) {
            Log::error('GrupoController@updateNotificacion: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar notificación'], 500);
        }
    }
}
