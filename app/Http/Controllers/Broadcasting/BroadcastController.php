<?php

namespace App\Http\Controllers\Broadcasting;

use App\Http\Controllers\Controller;
use App\Models\SoporteTi\SoporteTiChatSala;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class BroadcastController extends Controller
{
    /**
     * @return array{key: string|null, secret: string|null}
     */
    private function broadcastCredentials(): array
    {
        $connection = config('broadcasting.default', 'reverb');
        $config = config("broadcasting.connections.{$connection}", []);

        return [
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
        ];
    }
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
        'private-whatsapp-inbox.coordinacion' => '__wa_inbox__',
        'private-whatsapp-copiloto.ventas' => '__wa_copiloto_ventas__',
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

            $user->loadMissing('grupo');

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
                    return response()->json($this->pusherAuthPayload($request, $channelName));
                }
                Log::error('User not authorized for user channel', [
                    'user_id' => $user->ID_Usuario,
                    'channel' => $channelName,
                    'channel_user_id' => $channelUserId
                ]);
                return response()->json(['message' => 'No autorizado para este canal'], 403);
            }

            // Observaciones documentación expediente: private-coordinacion-documentacion-expediente.{idProveedor}
            $docExpPrefix = 'private-coordinacion-documentacion-expediente.';
            if (strpos($channelName, $docExpPrefix) === 0) {
                if ($this->usuarioPuedeAccederDocumentacionExpedienteObservaciones($user)) {
                    return response()->json($this->pusherAuthPayload($request, $channelName));
                }
                Log::error('User not authorized for documentacion expediente channel', [
                    'user_id' => $user->ID_Usuario,
                    'channel' => $channelName,
                ]);
                return response()->json(['message' => 'No autorizado para este canal'], 403);
            }

            // Chat Soporte TI: private-soporte-ti.chat.{uuid}
            $soporteTiPrefix = 'private-soporte-ti.chat.';
            if (strpos($channelName, $soporteTiPrefix) === 0) {
                $chatUuid = substr($channelName, strlen($soporteTiPrefix));
                if ($this->usuarioPuedeAccederSalaSoporteTi($user, $chatUuid)) {
                    return response()->json($this->pusherAuthPayload($request, $channelName));
                }
                Log::error('User not authorized for soporte-ti chat channel', [
                    'user_id' => $user->ID_Usuario,
                    'channel' => $channelName,
                    'chat_uuid' => $chatUuid,
                ]);
                return response()->json(['message' => 'No autorizado para este canal'], 403);
            }

            // Verificar si el canal está en nuestra lista de canales configurados (por rol)
            if (isset($this->CHANNELS[$channelName])) {
                $requiredRole = $this->CHANNELS[$channelName];

                if ($requiredRole === '__wa_copiloto_ventas__') {
                    if (!$this->usuarioPuedeAccederWaCopilotoVentas($user)) {
                        Log::error('User not authorized for wa-copiloto channel', [
                            'user_id' => $user->ID_Usuario,
                            'channel' => $channelName,
                            'user_grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo',
                        ]);
                        return response()->json(['message' => 'No autorizado para este canal'], 403);
                    }

                    return response()->json($this->pusherAuthPayload($request, $channelName));
                }

                if ($requiredRole === '__wa_inbox__') {
                    if (!$this->usuarioPuedeAccederWhatsappInbox($user)) {
                        Log::error('User not authorized for whatsapp-inbox channel', [
                            'user_id' => $user->ID_Usuario,
                            'channel' => $channelName,
                            'user_grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo',
                        ]);
                        return response()->json(['message' => 'No autorizado para este canal'], 403);
                    }

                    return response()->json($this->pusherAuthPayload($request, $channelName));
                }

                if (
                    !$user->grupo
                    || trim((string) $user->grupo->No_Grupo) !== trim((string) $requiredRole)
                ) {
                    Log::error('User not authorized for channel', [
                        'user_id' => $user->ID_Usuario,
                        'channel' => $channelName,
                        'required_role' => $requiredRole,
                        'user_grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo'
                    ]);
                    return response()->json(['message' => 'No autorizado para este canal'], 403);
                }

                return response()->json($this->pusherAuthPayload($request, $channelName));
            }

            // Canal seguimiento Drive por contenedor
            $seguimientoPrefix = 'private-carga-consolidada.seguimiento-drive.';
            if (strpos($channelName, $seguimientoPrefix) === 0) {
                $allowedRoles = [
                    Usuario::ROL_COTIZADOR,
                    Usuario::ROL_COORDINACION,
                    Usuario::ROL_ADMINISTRACION,
                    Usuario::ROL_JEFE_IMPORTACION,
                ];

                if ($user->grupo && in_array($user->grupo->No_Grupo, $allowedRoles, true)) {
                    return response()->json($this->pusherAuthPayload($request, $channelName));
                }

                Log::error('User not authorized for seguimiento-drive channel', [
                    'user_id' => $user->ID_Usuario,
                    'channel' => $channelName,
                    'user_grupo' => $user->grupo ? $user->grupo->No_Grupo : 'Sin grupo',
                ]);

                return response()->json(['message' => 'No autorizado para este canal'], 403);
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

    /**
     * Misma regla que SoporteTiService: PM/Soporte ven todas; resto solo sus solicitudes.
     *
     * @param \App\Models\Usuario $user
     * @param string $chatUuid
     * @return bool
     */
    /**
     * Misma regla que routes/channels.php → whatsapp-copiloto.ventas
     *
     * @param \App\Models\Usuario $user
     * @return bool
     */
    protected function usuarioPuedeAccederWaCopilotoVentas($user)
    {
        if (!$user) {
            return false;
        }

        if (!$user->grupo) {
            return (int) $user->getIdUsuario() === 28791;
        }

        $grupo = $user->grupo->No_Grupo;
        if (
            $grupo === Usuario::ROL_COTIZADOR
            || $grupo === Usuario::ROL_ADMINISTRACION
            || $grupo === Usuario::ROL_GERENCIA
        ) {
            return true;
        }

        return (int) $user->getIdUsuario() === 28791;
    }

    /**
     * @param \App\Models\Usuario $user
     * @return bool
     */
    protected function usuarioPuedeAccederWhatsappInbox($user)
    {
        return $user instanceof Usuario && $user->puedeAccederWhatsappInbox();
    }

    /**
     * Canal dedicado de observaciones del expediente (no whatsapp-inbox).
     *
     * @param \App\Models\Usuario $user
     * @return bool
     */
    protected function usuarioPuedeAccederDocumentacionExpedienteObservaciones($user)
    {
        if (!$user || !$user->grupo) {
            return false;
        }

        return trim((string) $user->grupo->No_Grupo) === Usuario::ROL_COORDINACION;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $channelName
     * @return array<string, string>
     */
    protected function pusherAuthPayload(Request $request, $channelName)
    {
        $credentials = $this->broadcastCredentials();
        $signature = hash_hmac(
            'sha256',
            $request->socket_id . ':' . $channelName,
            $credentials['secret']
        );

        return [
            'auth' => $credentials['key'] . ':' . $signature,
        ];
    }

    /**
     * @param \App\Models\Usuario $user
     * @param string $chatUuid
     * @return bool
     */
    protected function usuarioPuedeAccederSalaSoporteTi($user, $chatUuid)
    {
        $sala = SoporteTiChatSala::where('chat_uuid', $chatUuid)->first();
        if (!$sala) {
            return false;
        }

        $solicitud = $sala->solicitud;
        if (!$solicitud) {
            return false;
        }

        $user->loadMissing('grupo');
        $grupo = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
        $esStaff = $grupo === strtolower(Usuario::ROL_PM)
            || $grupo === strtolower(Usuario::ROL_SOPORTE);

        if ($esStaff) {
            return true;
        }

        $uid = (int) $user->ID_Usuario;
        return $solicitud->solicitante_user_id !== null
            && (int) $solicitud->solicitante_user_id === $uid;
    }
}
