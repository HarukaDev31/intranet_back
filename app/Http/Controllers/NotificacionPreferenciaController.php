<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\NotificacionPreferenciaUsuario;

class NotificacionPreferenciaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Devuelve las preferencias de notificaciones websocket del usuario autenticado.
     *
     * El catálogo de tipos vive en el frontend; aquí solo se guardan los overrides
     * (usuario + clave + canal => habilitado).
     */
    public function index(): JsonResponse
    {
        try {
            $usuario = Auth::user();

            $preferencias = NotificacionPreferenciaUsuario::where('usuario_id', $usuario->ID_Usuario)
                ->get(['notification_key', 'canal', 'habilitado'])
                ->map(function ($pref) {
                    return [
                        'notification_key' => $pref->notification_key,
                        'canal' => $pref->canal,
                        'habilitado' => (bool) $pref->habilitado,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $preferencias,
                'message' => 'Preferencias obtenidas exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las preferencias: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guarda (en lote) las preferencias de notificaciones websocket del usuario.
     */
    public function update(Request $request): JsonResponse
    {
        $canalesValidos = NotificacionPreferenciaUsuario::canalesValidos();

        $validator = Validator::make($request->all(), [
            'preferencias' => 'required|array',
            'preferencias.*.notification_key' => 'required|string|max:150',
            'preferencias.*.canal' => 'required|string|in:' . implode(',', $canalesValidos),
            'preferencias.*.habilitado' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $usuario = Auth::user();
            $preferencias = $request->get('preferencias');

            foreach ($preferencias as $preferencia) {
                NotificacionPreferenciaUsuario::updateOrCreate(
                    [
                        'usuario_id' => $usuario->ID_Usuario,
                        'notification_key' => $preferencia['notification_key'],
                        'canal' => $preferencia['canal'],
                    ],
                    [
                        'habilitado' => (bool) $preferencia['habilitado'],
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Preferencias actualizadas exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar las preferencias: ' . $e->getMessage(),
            ], 500);
        }
    }
}
