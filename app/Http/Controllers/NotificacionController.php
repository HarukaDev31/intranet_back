<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Notificacion;
use App\Models\Usuario;
use Carbon\Carbon;

class NotificacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Obtener notificaciones para el usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $usuario = Auth::user();
            
            $filtros = [
                'modulo' => $request->get('modulo'),
                'tipo' => $request->get('tipo'),
                'prioridad_minima' => $request->get('prioridad_minima'),
                'no_leidas' => $request->boolean('no_leidas', false)
            ];

            // Filtrar valores null
            $filtros = array_filter($filtros, function($value) {
                return $value !== null;
            });

            $notificaciones = Notificacion::paraUsuario($usuario, $filtros)
                ->with(['creador', 'usuarioDestinatario'])
                ->paginate($request->get('per_page', 15));
            
            // Log de notificaciones después de paginar
            Log::info('Notificaciones después de paginar', [
                'total' => $notificaciones->total(),
                'count' => $notificaciones->count(),
                'current_page' => $notificaciones->currentPage(),
                'notificaciones_ids' => $notificaciones->pluck('id')->toArray()
            ]);

            // Transformar las notificaciones para incluir información específica del usuario
            $notificaciones->getCollection()->transform(function ($notificacion) use ($usuario) {
                $textoPersonalizado = $notificacion->getTextoParaRol(
                    $usuario->grupo ? $usuario->grupo->No_Grupo : 'default'
                );

                // Verificar si fue leída por el usuario actual
                $estadoUsuario = $notificacion->usuarios()
                    ->where('usuario_id', $usuario->ID_Usuario)
                    ->first();

                return [
                    'id' => $notificacion->id,
                    'titulo' => $textoPersonalizado['titulo'],
                    'mensaje' => $textoPersonalizado['mensaje'],
                    'descripcion' => $textoPersonalizado['descripcion'],
                    'modulo' => $notificacion->modulo,
                    'navigate_to' => $notificacion->navigate_to,
                    'navigate_params' => $notificacion->navigate_params,
                    'tipo' => $notificacion->tipo,
                    'icono' => $notificacion->icono,
                    'prioridad' => $notificacion->prioridad,
                    'referencia_tipo' => $notificacion->referencia_tipo,
                    'referencia_id' => $notificacion->referencia_id,
                    'fecha_creacion' => $notificacion->created_at,
                    'fecha_expiracion' => $notificacion->fecha_expiracion,
                    'creador' => $notificacion->creador ? [
                        'id' => $notificacion->creador->ID_Usuario,
                        'nombre' => $notificacion->creador->No_Usuario
                    ] : null,
                    'estado_usuario' => [
                        'leida' => $estadoUsuario ? $estadoUsuario->pivot->leida : false,
                        'fecha_lectura' => $estadoUsuario ? $estadoUsuario->pivot->fecha_lectura : null,
                        'archivada' => $estadoUsuario ? $estadoUsuario->pivot->archivada : false,
                        'fecha_archivado' => $estadoUsuario ? $estadoUsuario->pivot->fecha_archivado : null
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $notificaciones,
                'message' => 'Notificaciones obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notificaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el conteo de notificaciones no leídas
     */
    public function conteoNoLeidas(): JsonResponse
    {
        try {
            $usuario = Auth::user();
            
            $conteo = Notificacion::paraUsuario($usuario, ['no_leidas' => true])
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_no_leidas' => $conteo
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el conteo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar una notificación como leída
     */
    public function marcarComoLeida(Request $request, int $id): JsonResponse
    {
        try {
            $usuario = Auth::user();
            $notificacion = Notificacion::findOrFail($id);

            $notificacion->marcarComoLeida($usuario->ID_Usuario);

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar como leída: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar múltiples notificaciones como leídas
     */
    public function marcarMultiplesComoLeidas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notificacion_ids' => 'required|array',
            'notificacion_ids.*' => 'integer|exists:notificaciones,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $usuario = Auth::user();
            $notificacionIds = $request->get('notificacion_ids');

            foreach ($notificacionIds as $notificacionId) {
                $notificacion = Notificacion::find($notificacionId);
                if ($notificacion) {
                    $notificacion->marcarComoLeida($usuario->ID_Usuario);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificaciones marcadas como leídas'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar como leídas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archivar una notificación
     */
    public function archivar(Request $request, int $id): JsonResponse
    {
        try {
            $usuario = Auth::user();
            $notificacion = Notificacion::findOrFail($id);

            $notificacion->archivar($usuario->ID_Usuario);

            return response()->json([
                'success' => true,
                'message' => 'Notificación archivada'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al archivar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva notificación (solo para administradores)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'descripcion' => 'nullable|string',
            'configuracion_roles' => 'nullable|array',
            'modulo' => 'required|string|max:100',
            'rol_destinatario' => 'nullable|string|max:100',
            'usuario_destinatario' => 'nullable|integer|exists:usuario,ID_Usuario',
            'navigate_to' => 'nullable|string|max:500',
            'navigate_params' => 'nullable|array',
            'tipo' => 'required|in:info,success,warning,error',
            'icono' => 'nullable|string|max:100',
            'prioridad' => 'required|integer|min:1|max:5',
            'referencia_tipo' => 'nullable|string|max:100',
            'referencia_id' => 'nullable|integer',
            'fecha_expiracion' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $usuario = Auth::user();

            $notificacion = Notificacion::create(array_merge(
                $validator->validated(),
                ['creado_por' => $usuario->ID_Usuario]
            ));

            return response()->json([
                'success' => true,
                'data' => $notificacion,
                'message' => 'Notificación creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una notificación específica
     */
    public function show(int $id): JsonResponse
    {
        try {
            $usuario = Auth::user();
            $notificacion = Notificacion::with(['creador', 'usuarioDestinatario'])
                ->findOrFail($id);

            // Verificar si el usuario puede ver esta notificación
            $puedeVer = $notificacion->usuario_destinatario === null || 
                       $notificacion->usuario_destinatario === $usuario->ID_Usuario ||
                       $notificacion->rol_destinatario === null ||
                       ($usuario->grupo && $notificacion->rol_destinatario === $usuario->grupo->No_Grupo);

            if (!$puedeVer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver esta notificación'
                ], 403);
            }

            $textoPersonalizado = $notificacion->getTextoParaRol(
                $usuario->grupo ? $usuario->grupo->No_Grupo : 'default'
            );

            $estadoUsuario = $notificacion->usuarios()
                ->where('usuario_id', $usuario->ID_Usuario)
                ->first();

            $data = [
                'id' => $notificacion->id,
                'titulo' => $textoPersonalizado['titulo'],
                'mensaje' => $textoPersonalizado['mensaje'],
                'descripcion' => $textoPersonalizado['descripcion'],
                'modulo' => $notificacion->modulo,
                'navigate_to' => $notificacion->navigate_to,
                'navigate_params' => $notificacion->navigate_params,
                'tipo' => $notificacion->tipo,
                'icono' => $notificacion->icono,
                'prioridad' => $notificacion->prioridad,
                'referencia_tipo' => $notificacion->referencia_tipo,
                'referencia_id' => $notificacion->referencia_id,
                'fecha_creacion' => $notificacion->created_at,
                'fecha_expiracion' => $notificacion->fecha_expiracion,
                'creador' => $notificacion->creador ? [
                    'id' => $notificacion->creador->ID_Usuario,
                    'nombre' => $notificacion->creador->No_Usuario
                ] : null,
                'estado_usuario' => [
                    'leida' => $estadoUsuario ? $estadoUsuario->pivot->leida : false,
                    'fecha_lectura' => $estadoUsuario ? $estadoUsuario->pivot->fecha_lectura : null,
                    'archivada' => $estadoUsuario ? $estadoUsuario->pivot->archivada : false,
                    'fecha_archivado' => $estadoUsuario ? $estadoUsuario->pivot->fecha_archivado : null
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la notificación: ' . $e->getMessage()
            ], 500);
        }
    }
}