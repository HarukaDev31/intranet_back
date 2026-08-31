<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\Usuario;
use Illuminate\Cache\TaggableStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class CargaConsolidadaCacheService
{
    public const TAG = 'carga-consolidada';

    private const VERSION = 'v2';
    private const TTL_MINUTES = 3;
    private const LOCK_SECONDS = 20;
    private const BLOCK_SECONDS = 8;

    public function shouldCacheGetRequest(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        return ! $this->pathMatchesSkipPatterns($request);
    }

    public function shouldInvalidateWriteRequest(Request $request, BaseResponse $response): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        return $this->isModuleRequest($request);
    }

    public function shouldStoreGetResponse(BaseResponse $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/vnd.api+json');
    }

    /**
     * @return array{content: string, status: int, headers: array<string, array<int, string|null>>}|null
     */
    public function getHttpPayload(string $key): ?array
    {
        $payload = $this->rememberTaggedRead($key);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array{content: string, status: int, headers: array<string, array<int, string|null>>}
     */
    public function rememberHttpGet(Request $request, callable $resolver): array
    {
        $key = $this->buildHttpCacheKey($request);
        $ttl = now()->addMinutes(self::TTL_MINUTES);
        $lock = Cache::lock('lock:' . $key, self::LOCK_SECONDS);

        return $lock->block(self::BLOCK_SECONDS, function () use ($key, $ttl, $resolver) {
            $cached = $this->getTagged($key);
            if (is_array($cached)) {
                return $cached;
            }

            /** @var BaseResponse $response */
            $response = $resolver();
            $payload = $this->payloadFromResponse($response);
            $this->putTagged($key, $payload, $ttl);

            return $payload;
        });
    }

    public function putHttpPayload(string $key, BaseResponse $response): void
    {
        if (! $this->shouldStoreGetResponse($response)) {
            return;
        }

        $this->putTagged($key, $this->payloadFromResponse($response), now()->addMinutes(self::TTL_MINUTES));
    }

    public function buildHttpCacheKey(Request $request): string
    {
        $params = $request->query->all();
        ksort($params);

        $parts = [
            'path' => '/' . ltrim($request->path(), '/'),
            'user' => $this->userScope($request),
            'params' => md5(json_encode($params)),
            'epoch' => $this->cacheEpoch(),
        ];

        return $this->key('http:' . md5(json_encode($parts)));
    }

    public function invalidateModule(): void
    {
        $this->flushTag();
        $this->bumpCacheEpoch();
    }

    /** @deprecated alias */
    public function invalidateAfterWrite(): void
    {
        $this->invalidateModule();
    }

    public function invalidateAfterHttpWrite(Request $request): void
    {
        $this->invalidateModule();
    }

    public function invalidateIfCotizacionAffectsList(Cotizacion $cotizacion): void
    {
        if ($cotizacion->wasRecentlyCreated) {
            $this->invalidateModule();

            return;
        }

        $keys = [
            'estado_cotizador',
            'volumen',
            'volumen_final',
            'id_contenedor',
            'deleted_at',
            'id_cliente_importacion',
            'estado_cliente',
            'monto_final',
            'logistica_final',
            'impuestos_final',
            'estado_cotizacion_final',
        ];

        foreach ($keys as $key) {
            if ($cotizacion->wasChanged($key)) {
                $this->invalidateModule();

                return;
            }
        }
    }

    public function invalidateIfProveedorAffectsList(CotizacionProveedor $proveedor): void
    {
        if ($proveedor->wasRecentlyCreated) {
            $this->invalidateModule();

            return;
        }

        $keys = [
            'cbm_total_china',
            'cbm_total',
            'maxcbm',
            'volumen_doc',
            'id_contenedor',
            'id_cotizacion',
            'estados',
            'estados_proveedor',
            'estado',
        ];

        foreach ($keys as $key) {
            if ($proveedor->wasChanged($key)) {
                $this->invalidateModule();

                return;
            }
        }
    }

    /**
     * @return array{content: string, status: int, headers: array<string, array<int, string|null>>}
     */
    private function payloadFromResponse(BaseResponse $response): array
    {
        return [
            'content' => (string) $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => ['Content-Type' => [$response->headers->get('Content-Type', 'application/json')]],
        ];
    }

    private function userScope(Request $request): string
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (! $user) {
                return 'anon';
            }

            $role = method_exists($user, 'getNombreGrupo') ? (string) $user->getNombreGrupo() : '';
            $effectiveRole = $role;
            if ($role === Usuario::ROL_JEFE_IMPORTACION && $request->filled('role')) {
                $requestedRole = trim((string) $request->input('role'));
                if (in_array($requestedRole, [Usuario::ROL_COORDINACION, Usuario::ROL_DOCUMENTACION], true)) {
                    $effectiveRole = $requestedRole;
                }
            }

            $userId = method_exists($user, 'getIdUsuario') ? (string) $user->getIdUsuario() : (string) ($user->id ?? '0');

            return $userId . ':' . $effectiveRole;
        } catch (\Throwable $e) {
            return 'anon';
        }
    }

    private function isModuleRequest(Request $request): bool
    {
        $path = strtolower(trim($request->path(), '/'));

        return str_starts_with($path, 'api/carga-consolidada')
            || str_starts_with($path, 'carga-consolidada')
            || str_starts_with($path, 'api/consolidado')
            || str_starts_with($path, 'consolidado');
    }

    private function pathMatchesSkipPatterns(Request $request): bool
    {
        $path = strtolower($request->path());
        $patterns = (array) config('carga_consolidada.cache_skip_path_contains', []);

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern !== '' && str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rememberTaggedRead(string $key): ?array
    {
        $value = $this->getTagged($key);

        return is_array($value) ? $value : null;
    }

    /**
     * @return mixed
     */
    private function getTagged(string $key)
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            return Cache::tags([self::TAG])->get($key);
        }

        return Cache::get($key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function putTagged(string $key, array $payload, $ttl): void
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags([self::TAG])->put($key, $payload, $ttl);

            return;
        }

        Cache::put($key, $payload, $ttl);
    }

    private function flushTag(): void
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags([self::TAG])->flush();
        }
    }

    private function cacheEpoch(): string
    {
        $epoch = Cache::get($this->key('epoch'));
        if (! is_string($epoch) || $epoch === '') {
            return '0';
        }

        return $epoch;
    }

    private function bumpCacheEpoch(): void
    {
        Cache::forever($this->key('epoch'), (string) microtime(true));
    }

    private function key(string $suffix): string
    {
        return 'ccons:' . self::VERSION . ':' . $suffix;
    }
}
