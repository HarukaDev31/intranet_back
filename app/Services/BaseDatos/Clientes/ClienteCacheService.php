<?php

namespace App\Services\BaseDatos\Clientes;

use App\Models\BaseDatos\Clientes\Cliente;
use App\Support\Cache\CachePayloadNormalizer;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class ClienteCacheService
{
    private const VERSION = 'v3';
    private const TAG = 'base-datos-clientes';

    public function rememberIndex(array $params, callable $resolver): array
    {
        $key = $this->key('index:' . md5(json_encode($this->stableParams($params))));
        return $this->rememberTagged($key, now()->addMinutes(3), $resolver);
    }

    public function rememberShow(int $id, callable $resolver): array
    {
        $key = $this->key("show:{$id}");
        return $this->rememberTagged($key, now()->addMinutes(1), $resolver);
    }

    public function invalidateAfterWrite(?Cliente $cliente = null): void
    {
        if ($cliente) {
            Cache::forget($this->key("show:{$cliente->id}"));
        }

        $this->flushTag();
    }

    private function key(string $suffix): string
    {
        return 'clientes:' . self::VERSION . ':' . $suffix;
    }

    private function rememberTagged(string $key, $ttl, callable $resolver): array
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            $tags = Cache::tags([self::TAG]);
            $cached = $tags->get($key);
            if (is_array($cached) && ! CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
                return $cached;
            }

            $payload = CachePayloadNormalizer::resolveArray($resolver);
            $tags->put($key, $payload, $ttl);

            return $payload;
        }

        return $this->remember($key, $ttl, $resolver);
    }

    private function remember(string $key, $ttl, callable $resolver): array
    {
        $cached = Cache::get($key);
        if (is_array($cached) && ! CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
            return $cached;
        }

        $payload = CachePayloadNormalizer::resolveArray($resolver);
        Cache::put($key, $payload, $ttl);

        return $payload;
    }

    private function flushTag(): void
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags([self::TAG])->flush();
        }
    }

    private function stableParams(array $params): array
    {
        ksort($params);
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $params[$k] = $this->stableParams($v);
            }
        }
        return $params;
    }
}
