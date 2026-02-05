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
        'private-JefeImportacion-notifications' => 'Jefe Importacion',
    ];
    
    /**
     * @OA\Post(
     *     path="/broadcasting/auth",
     *     tags={"Broadcasting"},
     *     summary="Autenticar canal de broadcasting",
     *     description="Autentica al usuario para acceder a canales privados de WebSocket",
     *     operationId="broadcastAuth",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="channel_name", type="string"),
     *             @OA\Property(property="socket_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Autenticación exitosa"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Acceso denegado al canal")
     * )
     *
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

            // Canal privado por usuario (calendario): private-App.Models.Usuario.{id}
            $userChannelPrefix = 'private-App.Models.Usuario.';
            if (strpos($channelName, $userChannelPrefix) === 0) {
                $channelUserId = (int) substr($channelName, strlen($userChannelPrefix));
                if ((int) $user->ID_Usuario === $channelUserId) {
                    $signature = hash_hmac(
                        'sha256',
                        $request->socket_id . ':' . $channelName,
                        config('broadcasting.connections.pusher.secret')
                    );
                    return response()->json([
                        'auth' => config('broadcasting.connections.pusher.key') . ':' . $signature
                    ]);
                }
                Log::error('User not authorized for user channel', [
                    'user_id' => $user->ID_Usuario,
                    'channel' => $channelName,
                    'channel_user_id' => $channelUserId
                ]);
                return response()->json(['message' => 'No autorizado para este canal'], 403);
            }

            // Verificar si el canal está en nuestra lista de canales configurados (por rol)
            if (isset($this->CHANNELS[$channelName])) {
                $requiredRole = $this->CHANNELS[$channelName];
                
                if (!$user->grupo || $user->grupo->No_Grupo !== $requiredRole) {
                    Log::error('User not authorized for channel', [
                        'user_id' => $user->ID_Usuario,
                        'channel' => $channelName,
                        'required_role' => $requiredRole,
                        'user_grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo'
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

            // Para otros canales, usar el método estándar de Laravel
            $response = Broadcast::auth($request);

            // $response puede no ser objeto (ej. null) o no tener ->original; no asumir tipo
            if (!is_object($response) || !property_exists($response, 'original')) {
                Log::error('Broadcasting auth: respuesta inválida', ['channel' => $channelName]);
                return response()->json(['message' => 'Error de autenticación del canal'], 403);
            }

            Log::info('Broadcasting auth success', [
                'user_id' => $user->ID_Usuario,
                'channel' => $channelName
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
