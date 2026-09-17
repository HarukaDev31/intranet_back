<?php

namespace App\Services\Auth;

use App\Support\Cache\CachePayloadNormalizer;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class AuthMenuCacheService
{
    private const VERSION = 'v1';
    private const TAG = 'auth-menu';

    public function rememberInterno($usuario, callable $resolver): array
    {
        $key = $this->key(sprintf(
            'interno:u:%d:g:%d:e:%d:o:%d',
            (int) ($usuario->ID_Usuario ?? 0),
            (int) ($usuario->ID_Grupo ?? 0),
            (int) ($usuario->ID_Empresa ?? 0),
            (int) ($usuario->ID_Organizacion ?? 0)
        ));

        return $this->rememberTagged($key, now()->addMinutes(15), $resolver);
    }

    public function rememberExterno(int $userId, callable $resolver): array
    {
        $key = $this->key('externo:u:' . $userId);

        return $this->rememberTagged($key, now()->addMinutes(15), $resolver);
    }

    public function invalidateAll(): void
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags([self::TAG])->flush();
        }
    }

    private function key(string $suffix): string
    {
        return 'auth:menu:' . self::VERSION . ':' . $suffix;
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
            if ($payload !== []) {
                $tags->put($key, $payload, $ttl);
            }

            return $payload;
        }

        $cached = Cache::get($key);
        if (is_array($cached) && ! CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
            return $cached;
        }

        $payload = CachePayloadNormalizer::resolveArray($resolver);
        if ($payload !== []) {
            Cache::put($key, $payload, $ttl);
        }

        return $payload;
    }
}
