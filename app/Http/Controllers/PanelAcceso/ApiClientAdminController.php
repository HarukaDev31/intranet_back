<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiClientAuditLog;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Services\ApiClients\ApiClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mantenedor de API clients (credenciales de la API de integración).
 *
 * Acceso restringido: SOLO usuarios de la organización 1 con rol GERENCIA / GERENTE GENERAL
 * (o el usuario root). Estas credenciales dan acceso a datos de otras organizaciones, por eso
 * es más estricto que el resto del panel. El token en claro solo se devuelve al crear o rotar.
 */
class ApiClientAdminController extends Controller
{
    private const ID_ORGANIZACION_ADMIN = 1;

    private function autorizar(): ?JsonResponse
    {
        $u = auth()->user();
        $ok = $u instanceof Usuario
            && (int) $u->getAttribute('ID_Organizacion') === self::ID_ORGANIZACION_ADMIN
            && (
                strtolower(trim((string) $u->getAttribute('No_Usuario'))) === 'root'
                || in_array(trim((string) $u->getNombreGrupo()), [Usuario::ROL_GERENCIA, Usuario::ROL_GERENTE_GENERAL], true)
            );
        if (!$ok) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        return null;
    }

    // GET /api/panel-acceso/api-clients/catalogo
    public function catalogo(): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        $scopes = [];
        foreach ((array) config('api_clients.scopes', []) as $clave => $descripcion) {
            $scopes[] = ['key' => $clave, 'label' => $descripcion];
        }

        return response()->json(['success' => true, 'data' => [
            'scopes' => $scopes,
            'default_rate_limit_per_minute' => (int) config('api_clients.default_rate_limit_per_minute'),
            'default_daily_quota' => (int) config('api_clients.default_daily_quota'),
            'max_rate_limit_per_minute' => (int) config('api_clients.max_rate_limit_per_minute'),
            'max_daily_quota' => (int) config('api_clients.max_daily_quota'),
            'base_path' => '/api/integration/v1',
        ]]);
    }

    // GET /api/panel-acceso/api-clients
    public function index(): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        $clients = ApiClient::query()->with('organizacion:ID_Organizacion,No_Organizacion')->orderByDesc('id')->get();
        $hoy = now()->startOfDay();
        $usoHoy = ApiClientAuditLog::query()
            ->whereIn('api_client_id', $clients->pluck('id'))
            ->where('created_at', '>=', $hoy)
            ->select('api_client_id', DB::raw('COUNT(*) as total'))
            ->groupBy('api_client_id')
            ->pluck('total', 'api_client_id');

        return response()->json(['success' => true, 'data' => $clients->map(function (ApiClient $c) use ($usoHoy) {
            return $this->serializar($c, (int) ($usoHoy[$c->id] ?? 0));
        })->values()]);
    }

    // POST /api/panel-acceso/api-clients
    public function store(Request $request): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        try {
            $datos = $request->validate([
                'organizacion_id' => 'required|integer|min:2|exists:organizacion,ID_Organizacion',
                'nombre' => 'required|string|min:3|max:120',
                'scopes' => 'required|array|min:1',
                'scopes.*' => 'string|in:' . implode(',', array_keys((array) config('api_clients.scopes', []))),
                'rate_limit_per_minute' => 'nullable|integer|min:1|max:' . (int) config('api_clients.max_rate_limit_per_minute'),
                'daily_quota' => 'nullable|integer|min:1|max:' . (int) config('api_clients.max_daily_quota'),
            ]);

            $org = Organizacion::query()->find((int) $datos['organizacion_id']);
            if (!$org || (int) $org->getAttribute('Nu_Estado') !== 1) {
                return response()->json(['success' => false, 'message' => 'La organización no está activa.'], 422);
            }

            $resultado = app(ApiClientService::class)->crear($datos, (int) auth()->id());
            Log::info('api-client creado', ['api_client_id' => $resultado['client']->id, 'org' => $datos['organizacion_id'], 'por' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'API client creado. Copia el token: no se volverá a mostrar.',
                'data' => $this->serializar($resultado['client']->load('organizacion:ID_Organizacion,No_Organizacion'), 0),
                'token' => $resultado['token'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('ApiClientAdminController@store: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al crear el API client'], 500);
        }
    }

    // PUT /api/panel-acceso/api-clients/{id}
    public function update(Request $request, $id): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        $client = ApiClient::query()->find((int) $id);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        try {
            $datos = $request->validate([
                'nombre' => 'sometimes|required|string|min:3|max:120',
                'scopes' => 'sometimes|required|array|min:1',
                'scopes.*' => 'string|in:' . implode(',', array_keys((array) config('api_clients.scopes', []))),
                'rate_limit_per_minute' => 'sometimes|required|integer|min:1|max:' . (int) config('api_clients.max_rate_limit_per_minute'),
                'daily_quota' => 'sometimes|required|integer|min:1|max:' . (int) config('api_clients.max_daily_quota'),
                'activo' => 'sometimes|boolean',
            ]);

            if (array_key_exists('scopes', $datos)) {
                $datos['scopes'] = app(ApiClientService::class)->scopesValidos($datos['scopes']);
            }
            // Reactivar un cliente revocado exige rotar el token (así el anterior no vuelve a servir).
            if (!empty($datos['activo']) && $client->revoked_at !== null) {
                return response()->json(['success' => false, 'message' => 'El cliente está revocado: rota el token para reactivarlo.'], 422);
            }

            $client->fill($datos)->save();

            return response()->json(['success' => true, 'message' => 'Actualizado', 'data' => $this->serializar($client->load('organizacion:ID_Organizacion,No_Organizacion'), 0)]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        }
    }

    // POST /api/panel-acceso/api-clients/{id}/rotar-token
    public function rotarToken($id): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        $client = ApiClient::query()->find((int) $id);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $token = app(ApiClientService::class)->rotarToken($client);
        $client->forceFill(['activo' => true])->save();
        Log::info('api-client token rotado', ['api_client_id' => $client->id, 'por' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => 'Token rotado. Copia el nuevo token: no se volverá a mostrar y el anterior ya no funciona.',
            'data' => $this->serializar($client->load('organizacion:ID_Organizacion,No_Organizacion'), 0),
            'token' => $token,
        ]);
    }

    // POST /api/panel-acceso/api-clients/{id}/revocar
    public function revocar($id): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        $client = ApiClient::query()->find((int) $id);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        app(ApiClientService::class)->revocar($client);
        Log::info('api-client revocado', ['api_client_id' => $client->id, 'por' => auth()->id()]);

        return response()->json(['success' => true, 'message' => 'Token revocado']);
    }

    // GET /api/panel-acceso/api-clients/{id}/auditoria?desde=&hasta=&status=&page=
    public function auditoria(Request $request, $id): JsonResponse
    {
        if ($d = $this->autorizar()) {
            return $d;
        }

        $client = ApiClient::query()->find((int) $id);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $datos = $request->validate([
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|integer|min:100|max:599',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $base = ApiClientAuditLog::query()->where('api_client_id', $client->id);
        if (!empty($datos['desde'])) {
            $base->where('created_at', '>=', $datos['desde'] . ' 00:00:00');
        }
        if (!empty($datos['hasta'])) {
            $base->where('created_at', '<=', $datos['hasta'] . ' 23:59:59');
        }

        $resumen = (clone $base)
            ->selectRaw('COUNT(*) as total, SUM(status_code BETWEEN 200 AND 299) as ok, SUM(status_code >= 400 AND status_code < 500) as errores_cliente, SUM(status_code >= 500) as errores_servidor, SUM(status_code = 429) as limitados, ROUND(AVG(duration_ms)) as ms_promedio')
            ->first();
        $porDia = (clone $base)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as total')
            ->groupBy('dia')->orderByDesc('dia')->limit(30)->get();
        $porRuta = (clone $base)
            ->selectRaw('method, path, COUNT(*) as total')
            ->groupBy('method', 'path')->orderByDesc('total')->limit(10)->get();
        $porIp = (clone $base)
            ->selectRaw('ip, COUNT(*) as total')
            ->groupBy('ip')->orderByDesc('total')->limit(10)->get();

        $logs = (clone $base)
            ->when(!empty($datos['status']), function ($q) use ($datos) {
                $q->where('status_code', (int) $datos['status']);
            })
            ->orderByDesc('id')
            ->paginate((int) ($datos['per_page'] ?? 25));

        return response()->json([
            'success' => true,
            'data' => [
                'cliente' => $this->serializar($client->load('organizacion:ID_Organizacion,No_Organizacion'), 0),
                'resumen' => $resumen,
                'por_dia' => $porDia,
                'por_ruta' => $porRuta,
                'por_ip' => $porIp,
                'logs' => $logs->items(),
            ],
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(ApiClient $c, int $usoHoy): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'organizacion_id' => (int) $c->organizacion_id,
            'organizacion' => $c->relationLoaded('organizacion') && $c->organizacion ? $c->organizacion->getAttribute('No_Organizacion') : null,
            'token_prefix' => $c->token_prefix,
            'scopes' => (array) $c->scopes,
            'id_usuario_bot' => $c->id_usuario_bot,
            'rate_limit_per_minute' => $c->rate_limit_per_minute,
            'daily_quota' => $c->daily_quota,
            'activo' => (bool) $c->activo,
            'revocado' => $c->revoked_at !== null,
            'last_used_at' => optional($c->last_used_at)->toIso8601String(),
            'last_used_ip' => $c->last_used_ip,
            'requests_hoy' => $usoHoy,
            'created_at' => optional($c->created_at)->toIso8601String(),
        ];
    }
}
