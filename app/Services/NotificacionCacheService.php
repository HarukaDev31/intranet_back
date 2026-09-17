<?php

namespace App\Services;

use App\Support\Cache\CachePayloadNormalizer;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class NotificacionCacheService
{
    private const VERSION = 'v1';
    private const TAG = 'notificaciones';

    public function rememberIndex(int $usuarioId, array $params, callable $resolver): array
    {
        $key = $this->key('index:' . $usuarioId . ':' . md5((string) json_encode($this->stableParams($params))));

        return $this->rememberTagged($key, now()->addSeconds(60), $resolver, $this->userTag($usuarioId));
    }

    public function rememberConteo(int $usuarioId, callable $resolver): array
    {
        $key = $this->key('conteo:' . $usuarioId);

        return $this->rememberTagged($key, now()->addSeconds(30), $resolver, $this->userTag($usuarioId));
    }

    public function invalidateForUser(int $usuarioId): void
    {
        Cache::forget($this->key('conteo:' . $usuarioId));

        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags([$this->userTag($usuarioId)])->flush();
        }
    }

    public function invalidateAll(): void
    {
        $this->flushTag();
    }

    private function userTag(int $usuarioId): string
    {
        return self::TAG . ':user:' . $usuarioId;
    }

    private function key(string $suffix): string
    {
        return 'notificaciones:' . self::VERSION . ':' . $suffix;
    }

    private function rememberTagged(string $key, $ttl, callable $resolver, ?string $extraTag = null): array
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            $tagNames = [self::TAG];
            if ($extraTag) {
                $tagNames[] = $extraTag;
            }
            $tags = Cache::tags($tagNames);
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
