<?php

namespace App\Http\Controllers\Broadcasting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
///import jwt
use Tymon\JWTAuth\Facades\JWTAuth;

class BroadcastController extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public $CHANNELS = [
        'private-Cotizador-notifications' => 'Cotizador',
        'private-Documentacion-notifications' => 'Documentacion',
        'private-Coordinacion-notifications' => 'Coordinación',
        'private-ContenedorAlmacen-notifications' => 'ContenedorAlmacen',
        'private-CatalogoChina-notifications' => 'CatalogoChina',
        'private-Administracion-notifications' => 'Administración',
    ];
    public function authenticate(Request $request)
    {
        Log::info('Authenticate request', [
            'channel' => $request->channel_name,
            'socket_id' => $request->socket_id,
            'headers' => $request->headers->all(),
            'all_data' => $request->all()
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                Log::error('Broadcasting auth failed: No authenticated user');
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            Log::info('User data', [
                'user_id' => $user->ID_Usuario,
                'grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo'
            ]);

            // Verificar manualmente el acceso al canal
            $channelName = $request->channel_name;
            
            // Remover el prefijo 'private-' si existe
            $channelWithoutPrefix = str_replace('private-', '', $channelName);

            if ($channelWithoutPrefix === $this->CHANNELS[$channelWithoutPrefix]) {
                if (!$user->grupo || $user->grupo->No_Grupo !== $this->CHANNELS[$channelWithoutPrefix]) {
                    Log::error('User not authorized for channel', [
                        'user_id' => $user->ID_Usuario,
                        'channel' => $channelName,
                        'grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo'
                    ]);
                    return response()->json(['message' => 'No autorizado para este canal'], 403);
                }

                // Generar la firma de autenticación manualmente
                $signature = hash_hmac(
                    'sha256',
                    $request->socket_id . ':' . $channelName,
                    config('broadcasting.connections.pusher.secret')
                );

                return response()->json([
                    'auth' => config('broadcasting.connections.pusher.key') . ':' . $signature
                ]);
            }

            // Para otros canales, usar el método estándar
            $response = Broadcast::auth($request);
            
            Log::info('Broadcasting auth success', [
                'user_id' => $user->ID_Usuario,
                'channel' => $channelName,
                'response' => $response->original
            ]);

            return response()->json($response->original);

        } catch (\Exception $e) {
            Log::error('Broadcasting auth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'channel' => $request->channel_name,
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Error de autenticación: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 403);
        }
    }
}
