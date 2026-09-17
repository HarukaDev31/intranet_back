<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Services\NotificacionCacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    /** @var NotificacionCacheService */
    private $notificacionCache;

    public function __construct(NotificacionCacheService $notificacionCache)
    {
        $this->middleware('auth:api');
        $this->notificacionCache = $notificacionCache;
    }

    /**
     * @OA\Get(
     *     path="/notificaciones",
     *     tags={"Notificaciones"},
     *     summary="Listar notificaciones",
     *     description="Obtiene las notificaciones del usuario autenticado con filtros opcionales",
     *     operationId="getNotificaciones",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="modulo",
     *         in="query",
     *         description="Filtrar por módulo",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="tipo",
     *         in="query",
     *         description="Filtrar por tipo de notificación",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="prioridad_minima",
     *         in="query",
     *         description="Filtrar por prioridad mínima",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="no_leidas",
     *         in="query",
     *         description="Filtrar solo no leídas",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notificaciones obtenidas exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="conteos", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="no_leidas", type="integer"),
     *                 @OA\Property(property="leidas", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * Obtener notificaciones para el usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $usuario = Auth::user();
            $usuario->loadMissing('grupo');
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 15);

            $filtros = [
                'modulo' => $request->get('modulo'),
                'tipo' => $request->get('tipo'),
                'prioridad_minima' => $request->get('prioridad_minima')
            ];

            if ($request->has('no_leidas')) {
                $filtros['no_leidas'] = $request->boolean('no_leidas');
            }

            $filtros = array_filter($filtros, function ($value) {
                return $value !== null;
            });

            $payload = $this->notificacionCache->rememberIndex((int) $usuario->ID_Usuario, [
                'filtros' => $filtros,
                'page' => $page,
                'per_page' => $perPage,
            ], function () use ($usuario, $filtros, $perPage) {
                $notificaciones = Notificacion::paraUsuario($usuario, $filtros)
                    ->with(['creador', 'usuarioDestinatario'])
                    ->paginate($perPage);

                $filtrosParaConteo = $filtros;
                unset($filtrosParaConteo['no_leidas']);

                $idsPagina = $notificaciones->getCollection()->pluck('id');
                $estadosPorNotificacion = $idsPagina->isEmpty()
                    ? collect()
                    : DB::table('notificacion_usuario')
                        ->where('usuario_id', $usuario->ID_Usuario)
                        ->whereIn('notificacion_id', $idsPagina)
                        ->get()
                        ->keyBy('notificacion_id');

                $rol = $usuario->grupo ? $usuario->grupo->No_Grupo : 'default';
                $notificaciones->getCollection()->transform(function ($notificacion) use ($rol, $estadosPorNotificacion) {
                    $textoPersonalizado = $notificacion->getTextoParaRol($rol);
                    $estadoUsuario = $estadosPorNotificacion->get($notificacion->id);

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
                        'fecha_creacion' => $notificacion->created_at?->toIso8601String(),
                        'fecha_expiracion' => $notificacion->fecha_expiracion?->toIso8601String(),
                        'creador' => $notificacion->creador ? [
                            'id' => $notificacion->creador->ID_Usuario,
                            'nombre' => $notificacion->creador->No_Usuario
                        ] : null,
                        'estado_usuario' => [
                            'leida' => $estadoUsuario ? (bool) $estadoUsuario->leida : false,
                            'fecha_lectura' => $estadoUsuario ? $estadoUsuario->fecha_lectura : null,
                            'archivada' => $estadoUsuario ? (bool) $estadoUsuario->archivada : false,
                            'fecha_archivado' => $estadoUsuario ? $estadoUsuario->fecha_archivado : null
                        ]
                    ];
                });

                return [
                    'success' => true,
                    'data' => $notificaciones->toArray(),
                    'conteos' => [
                        'total' => Notificacion::paraUsuario($usuario, $filtrosParaConteo)->count(),
                        'no_leidas' => Notificacion::paraUsuario($usuario, array_merge($filtrosParaConteo, ['no_leidas' => true]))->count(),
                        'leidas' => Notificacion::paraUsuario($usuario, array_merge($filtrosParaConteo, ['no_leidas' => false]))->count(),
                    ],
                    'message' => 'Notificaciones obtenidas exitosamente',
                ];
            });

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notificaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/notificaciones/conteo-no-leidas",
     *     tags={"Notificaciones"},
     *     summary="Contar notificaciones no leídas",
     *     description="Obtiene el conteo de notificaciones no leídas del usuario autenticado",
     *     operationId="conteoNoLeidas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Conteo obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * Obtener el conteo de notificaciones no leídas
     */
    public function conteoNoLeidas(): JsonResponse
    {
        try {
            $usuario = Auth::user();

            $payload = $this->notificacionCache->rememberConteo((int) $usuario->ID_Usuario, function () use ($usuario) {
                return [
                    'total_no_leidas' => Notificacion::paraUsuario($usuario, ['no_leidas' => true])->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $payload
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el conteo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/notificaciones/{id}/marcar-leida",
     *     tags={"Notificaciones"},
     *     summary="Marcar notificación como leída",
     *     description="Marca una notificación específica como leída",
     *     operationId="marcarComoLeida",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notificación marcada como leída"),
     *     @OA\Response(response=404, description="Notificación no encontrada")
     * )
     *
     * Marcar una notificación como leída
     */
    public function marcarComoLeida(Request $request, int $id): JsonResponse
    {
        try {
            $usuario = Auth::user();
            $notificacion = Notificacion::findOrFail($id);

            $notificacion->marcarComoLeida($usuario->ID_Usuario);
            $this->notificacionCache->invalidateForUser((int) $usuario->ID_Usuario);

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
     * @OA\Post(
     *     path="/notificaciones/marcar-multiples-leidas",
     *     tags={"Notificaciones"},
     *     summary="Marcar múltiples notificaciones como leídas",
     *     description="Marca varias notificaciones como leídas",
     *     operationId="marcarMultiplesComoLeidas",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="notificacion_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Notificaciones marcadas como leídas"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     *
     * Marcar múltiples notificaciones como leídas
     */
    public function marcarMultiplesComoLeidas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notificacion_ids' => 'required|array',
            'notificacion_ids.*' => 'integer',
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

            Notificacion::marcarMultiplesComoLeidas(
                $request->get('notificacion_ids'),
                $usuario->ID_Usuario
            );
            $this->notificacionCache->invalidateForUser((int) $usuario->ID_Usuario);

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
     * @OA\Put(
     *     path="/notificaciones/{id}/archivar",
     *     tags={"Notificaciones"},
     *     summary="Archivar notificación",
     *     description="Archiva una notificación específica",
     *     operationId="archivarNotificacion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notificación archivada"),
     *     @OA\Response(response=404, description="Notificación no encontrada")
     * )
     *
     * Archivar una notificación
     */
    public function archivar(Request $request, int $id): JsonResponse
    {
        try {
            $usuario = Auth::user();
            $notificacion = Notificacion::findOrFail($id);

            $notificacion->archivar($usuario->ID_Usuario);
            $this->notificacionCache->invalidateForUser((int) $usuario->ID_Usuario);

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
