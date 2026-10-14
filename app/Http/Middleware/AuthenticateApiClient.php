<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiClientAuditLog;
use App\Models\Usuario;
use App\Services\ApiClients\ApiClientService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticación de la API de integración: Authorization: Bearer pbk_...
 *
 * - El token se busca por su hash (SHA-256); no se guarda en claro.
 * - Exige el scope de la ruta (alias `api.client:{scope}`).
 * - Aplica el límite por minuto y la cuota diaria del cliente (429).
 * - Frena la fuerza bruta: intentos fallidos por IP.
 * - Actúa como el usuario BOT de la organización del cliente (así el scoping por organización
 *   de los modelos y los controladores existentes aplica solo a SU organización).
 * - Registra TODO request (también los fallidos) en api_client_audit_logs.
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, string $scope = ''): Response
    {
        $inicio = microtime(true);
        $ip = (string) $request->ip();

        // Anti fuerza bruta por IP (solo cuentan intentos fallidos).
        $claveFallos = 'api-client-fail:' . $ip;
        $maxFallos = max(1, (int) config('api_clients.failed_auth_per_minute', 20));
        if (RateLimiter::tooManyAttempts($claveFallos, $maxFallos)) {
            return $this->rechazar($request, null, $scope, 429, 'Demasiados intentos. Reintenta más tarde.', 'fail_ip_limit', $inicio, RateLimiter::availableIn($claveFallos));
        }

        $token = $this->tokenDe($request);
        if ($token === null) {
            RateLimiter::hit($claveFallos, 60);

            return $this->rechazar($request, null, $scope, 401, 'No autorizado.', 'sin_token', $inicio);
        }

        $client = ApiClient::query()->where('token_hash', ApiClientService::hashToken($token))->first();
        if (!$client || !$client->estaVigente()) {
            RateLimiter::hit($claveFallos, 60);

            return $this->rechazar($request, null, $scope, 401, 'No autorizado.', $client ? 'token_revocado' : 'token_invalido', $inicio, null, substr($token, 0, 12));
        }

        if ($scope !== '' && !$client->tieneScope($scope)) {
            return $this->rechazar($request, $client, $scope, 403, 'El token no tiene permiso para este recurso.', 'scope_insuficiente', $inicio);
        }

        $bot = $this->usuarioBot($client);
        if ($bot === null) {
            return $this->rechazar($request, $client, $scope, 403, 'Integración no disponible para esta organización.', 'bot_o_org_invalido', $inicio);
        }

        // Límite por minuto y cuota diaria por cliente.
        $claveMinuto = 'api-client:' . $client->id;
        $maxMinuto = max(1, (int) $client->rate_limit_per_minute);
        if (RateLimiter::tooManyAttempts($claveMinuto, $maxMinuto)) {
            return $this->rechazar($request, $client, $scope, 429, 'Límite de requests por minuto excedido.', 'rate_limit_minuto', $inicio, RateLimiter::availableIn($claveMinuto));
        }
        RateLimiter::hit($claveMinuto, 60);

        $segundosHastaManana = max(60, strtotime('tomorrow') - time());
        $claveDia = 'api-client-daily:' . $client->id . ':' . date('Ymd');
        Cache::add($claveDia, 0, $segundosHastaManana + 60);
        $usadosHoy = (int) Cache::increment($claveDia);
        if ($usadosHoy > max(1, (int) $client->daily_quota)) {
            return $this->rechazar($request, $client, $scope, 429, 'Cuota diaria excedida.', 'cuota_diaria', $inicio, $segundosHastaManana);
        }

        $this->marcarUso($client, $ip);

        // La integración actúa como el usuario BOT de SU organización.
        Auth::shouldUse('api');
        Auth::guard('api')->setUser($bot);
        $request->attributes->set('api_client', $client);
        $request->attributes->set('organizacion_id', (int) $client->organizacion_id);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->auditar($request, $client, $scope, 500, $inicio, 'excepcion: ' . class_basename($e));
            throw $e;
        }

        $response->headers->set('X-RateLimit-Limit', (string) $maxMinuto);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, RateLimiter::remaining($claveMinuto, $maxMinuto)));
        $response->headers->set('X-Quota-Remaining', (string) max(0, (int) $client->daily_quota - $usadosHoy));

        $this->auditar($request, $client, $scope, $response->getStatusCode(), $inicio, (string) $request->attributes->get('audit_error', ''));

        return $response;
    }

    private function tokenDe(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (strpos($header, 'Bearer ') !== 0) {
            return null;
        }
        $token = trim(substr($header, 7));
        // Formato exacto: pbk_ + 48 hex. Cualquier otra cosa se descarta sin tocar la BD.
        if (!preg_match('/^pbk_[a-f0-9]{48}$/', $token)) {
            return null;
        }

        return $token;
    }

    /**
     * Usuario BOT activo de la MISMA organización del cliente, con la organización activa.
     */
    private function usuarioBot(ApiClient $client): ?Usuario
    {
        if (!$client->id_usuario_bot) {
            return null;
        }
        $bot = Usuario::query()->find($client->id_usuario_bot);
        if (!$bot
            || (int) $bot->getAttribute('ID_Organizacion') !== (int) $client->organizacion_id
            || (int) $bot->getAttribute('Nu_Estado') !== 1
        ) {
            return null;
        }
        $orgActiva = DB::table('organizacion')
            ->where('ID_Organizacion', $client->organizacion_id)
            ->where('Nu_Estado', 1)
            ->exists();

        return $orgActiva ? $bot : null;
    }

    private function marcarUso(ApiClient $client, string $ip): void
    {
        try {
            $ultimo = $client->last_used_at;
            if ($ultimo === null || $ultimo->lt(now()->subMinute()) || $client->last_used_ip !== $ip) {
                $client->forceFill(['last_used_at' => now(), 'last_used_ip' => $ip])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('api.client: no se pudo marcar el uso: ' . $e->getMessage());
        }
    }

    private function rechazar(Request $request, ?ApiClient $client, string $scope, int $status, string $mensaje, string $motivo, float $inicio, ?int $reintentarEn = null, ?string $prefijoToken = null): Response
    {
        $this->auditar($request, $client, $scope, $status, $inicio, $motivo, $prefijoToken);
        $response = response()->json(['success' => false, 'message' => $mensaje], $status);
        if ($reintentarEn !== null) {
            $response->headers->set('Retry-After', (string) max(1, $reintentarEn));
        }

        return $response;
    }

    private function auditar(Request $request, ?ApiClient $client, string $scope, int $status, float $inicio, string $error = '', ?string $prefijoToken = null): void
    {
        try {
            $meta = $request->attributes->get('audit_meta');
            ApiClientAuditLog::query()->create([
                'api_client_id' => $client ? $client->id : null,
                'organizacion_id' => $client ? (int) $client->organizacion_id : null,
                'token_prefix' => $client ? $client->token_prefix : $prefijoToken,
                'method' => substr($request->method(), 0, 8),
                // Solo la ruta (sin query string: puede traer datos de clientes).
                'path' => substr('/' . ltrim($request->path(), '/'), 0, 255),
                'scope' => $scope !== '' ? $scope : null,
                'status_code' => $status,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
                'duration_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'error' => $error !== '' ? mb_substr($error, 0, 190) : null,
                'meta' => is_array($meta) ? $meta : null,
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe tumbar la API.
            Log::warning('api.client: no se pudo registrar la auditoría: ' . $e->getMessage());
        }
    }
}
