<?php

namespace App\Http\Middleware;

use App\Services\CargaConsolidada\CargaConsolidadaCacheService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CargaConsolidadaHttpCache
{
    /** @var CargaConsolidadaCacheService */
    private $cache;

    public function __construct(CargaConsolidadaCacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Cachea GET JSON del módulo e invalida en escrituras exitosas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->cache->shouldCacheGetRequest($request)) {
            $key = $this->cache->buildHttpCacheKey($request);
            $cached = $this->cache->getHttpPayload($key);
            if ($cached !== null) {
                return response($cached['content'], $cached['status'], $cached['headers'] ?? []);
            }

            $response = $next($request);
            if ($this->cache->shouldStoreGetResponse($response)) {
                $this->cache->putHttpPayload($key, $response);
            }

            return $response;
        }

        $response = $next($request);

        if ($this->cache->shouldInvalidateWriteRequest($request, $response)) {
            $this->cache->invalidateAfterHttpWrite($request);
        }

        return $response;
    }
}
