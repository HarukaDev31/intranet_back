<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Grupo;
use Illuminate\Support\Facades\Log;

class UsuarioGrupoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/usuarios/{id}/grupos",
     *     tags={"Usuarios"},
     *     summary="Obtener usuario con sus grupos",
     *     description="Obtiene la información de un usuario incluyendo todos sus grupos asignados",
     *     operationId="getUsuarioConGrupos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Usuario obtenido exitosamente"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     *
     * Obtener información del usuario con sus grupos
     */
    public function getUsuarioConGrupos($id)
    {
        try {
            $usuario = Usuario::with(['grupo', 'gruposUsuario.grupo', 'empresa', 'organizacion'])
                ->find($id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Obtener todos los grupos del usuario
            $todosLosGrupos = $usuario->getAllGrupos();

            return response()->json([
                'success' => true,
                'data' => [
                    'usuario' => [
                        'id' => $usuario->ID_Usuario,
                        'nombre' => $usuario->No_Usuario,
                        'nombres_apellidos' => $usuario->No_Nombres_Apellidos,
                        'email' => $usuario->Txt_Email,
                        'estado' => $usuario->Nu_Estado,
                        'grupo_principal' => [
                            'id' => $usuario->grupo ? $usuario->grupo->ID_Grupo : null,
                            'nombre' => $usuario->nombre_grupo_principal,
                            'descripcion' => $usuario->descripcion_grupo_principal,
                            'tipo_privilegio' => $usuario->tipo_privilegio_acceso
                        ],
                        'empresa' => $usuario->empresa ? [
                            'id' => $usuario->empresa->ID_Empresa,
                            'nombre' => $usuario->empresa->No_Empresa
                        ] : null,
                        'organizacion' => $usuario->organizacion ? [
                            'id' => $usuario->organizacion->ID_Organizacion,
                            'nombre' => $usuario->organizacion->No_Organizacion
                        ] : null
                    ],
                    'grupos' => $todosLosGrupos->map(function ($grupo) {
                        return [
                            'id' => $grupo->ID_Grupo,
                            'nombre' => $grupo->No_Grupo,
                            'descripcion' => $grupo->No_Grupo_Descripcion,
                            'tipo_privilegio' => $grupo->Nu_Tipo_Privilegio_Acceso,
                            'estado' => $grupo->Nu_Estado,
                            'notificacion' => $grupo->Nu_Notificacion
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener usuario con grupos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del usuario'
            ], 500);
        }
    }

    /**
     * Obtener usuarios por grupo
     */
    public function getUsuariosPorGrupo($grupoId)
    {
        try {
            $grupo = Grupo::with(['usuarios', 'empresa', 'organizacion'])
                ->find($grupoId);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'grupo' => [
                        'id' => $grupo->ID_Grupo,
                        'nombre' => $grupo->No_Grupo,
                        'descripcion' => $grupo->No_Grupo_Descripcion,
                        'tipo_privilegio' => $grupo->Nu_Tipo_Privilegio_Acceso,
                        'estado' => $grupo->Nu_Estado,
                        'notificacion' => $grupo->Nu_Notificacion,
                        'empresa' => $grupo->empresa ? [
                            'id' => $grupo->empresa->ID_Empresa,
                            'nombre' => $grupo->empresa->No_Empresa
                        ] : null,
                        'organizacion' => $grupo->organizacion ? [
                            'id' => $grupo->organizacion->ID_Organizacion,
                            'nombre' => $grupo->organizacion->No_Organizacion
                        ] : null
                    ],
                    'usuarios' => $grupo->usuarios->map(function ($usuario) {
                        return [
                            'id' => $usuario->ID_Usuario,
                            'nombre' => $usuario->No_Usuario,
                            'nombres_apellidos' => $usuario->No_Nombres_Apellidos,
                            'email' => $usuario->Txt_Email,
                            'estado' => $usuario->Nu_Estado,
                            'fecha_creacion' => $usuario->Fe_Creacion
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios por grupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios del grupo'
            ], 500);
        }
    }

    /**
     * Verificar si un usuario pertenece a un grupo
     */
    public function verificarPertenencia(Request $request)
    {
        try {
            $request->validate([
                'usuario_id' => 'required|integer',
                'grupo_id' => 'required|integer'
            ]);

            $usuario = Usuario::find($request->usuario_id);
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            $pertenece = $usuario->perteneceAGrupo($request->grupo_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'usuario_id' => $request->usuario_id,
                    'grupo_id' => $request->grupo_id,
                    'pertenece' => $pertenece
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al verificar pertenencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar pertenencia'
            ], 500);
        }
    }

    /**
     * Obtener grupos disponibles para un usuario
     */
    public function getGruposDisponibles($usuarioId)
    {
        try {
            $usuario = Usuario::with(['empresa', 'organizacion'])->find($usuarioId);
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Obtener grupos de la misma empresa y organización
            $gruposDisponibles = Grupo::where('ID_Empresa', $usuario->ID_Empresa)
                ->where('ID_Organizacion', $usuario->ID_Organizacion)
                ->where('Nu_Estado', 1) // Solo grupos activos
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'usuario' => [
                        'id' => $usuario->ID_Usuario,
                        'nombre' => $usuario->No_Usuario,
                        'empresa' => $usuario->empresa ? $usuario->empresa->No_Empresa : null,
                        'organizacion' => $usuario->organizacion ? $usuario->organizacion->No_Organizacion : null
                    ],
                    'grupos_disponibles' => $gruposDisponibles->map(function ($grupo) use ($usuario) {
                        return [
                            'id' => $grupo->ID_Grupo,
                            'nombre' => $grupo->No_Grupo,
                            'descripcion' => $grupo->No_Grupo_Descripcion,
                            'tipo_privilegio' => $grupo->Nu_Tipo_Privilegio_Acceso,
                            'asignado' => $usuario->perteneceAGrupo($grupo->ID_Grupo)
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener grupos disponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos disponibles'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de usuarios y grupos
     */
    public function getEstadisticas()
    {
        try {
            $totalUsuarios = Usuario::count();
            $usuariosActivos = Usuario::where('Nu_Estado', 1)->count();
            $totalGrupos = Grupo::count();
            $gruposActivos = Grupo::where('Nu_Estado', 1)->count();

            // Usuarios por grupo
            $usuariosPorGrupo = Grupo::withCount('usuarios')
                ->where('Nu_Estado', 1)
                ->orderBy('usuarios_count', 'desc')
                ->get()
                ->map(function ($grupo) {
                    return [
                        'grupo' => $grupo->No_Grupo,
                        'total_usuarios' => $grupo->usuarios_count
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen' => [
                        'total_usuarios' => $totalUsuarios,
                        'usuarios_activos' => $usuariosActivos,
                        'total_grupos' => $totalGrupos,
                        'grupos_activos' => $gruposActivos
                    ],
                    'usuarios_por_grupo' => $usuariosPorGrupo
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
}
