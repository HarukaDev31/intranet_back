<?php

namespace App\Services\PagoProveedor;

use App\Support\Cache\CachePayloadNormalizer;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class PagoProveedorCacheService
{
    private const VERSION = 'v2';

    private const TAG = 'pago-proveedor';

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberConfiguration(callable $resolver): array
    {
        return $this->remember($this->key('configuration'), now()->addHours(6), $resolver);
    }

    public function forgetConfiguration(): void
    {
        $this->forget($this->key('configuration'));
    }

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberConsolidatedQuote(string $code, callable $resolver): array
    {
        return $this->remember($this->key('cc:' . strtoupper(trim($code))), now()->addMinutes(5), $resolver);
    }

    public function forgetConsolidatedQuote(string $code): void
    {
        $this->forget($this->key('cc:' . strtoupper(trim($code))));
    }

    /**
     * A tagged entry only leaves the cache through the same tags it was stored with.
     */
    private function forget(string $key): void
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags([self::TAG])->forget($key);

            return;
        }

        Cache::forget($key);
    }

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function remember(string $key, \DateTimeInterface $ttl, callable $resolver): array
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            $tags = Cache::tags([self::TAG]);
            $cached = $tags->get($key);
            if (is_array($cached) && !CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
                return $cached;
            }

            $payload = CachePayloadNormalizer::resolveArray($resolver);
            $tags->put($key, $payload, $ttl);

            return $payload;
        }

        $cached = Cache::get($key);
        if (is_array($cached) && !CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
            return $cached;
        }

        $payload = CachePayloadNormalizer::resolveArray($resolver);
        Cache::put($key, $payload, $ttl);

        return $payload;
    }

    private function key(string $suffix): string
    {
        return 'pago-proveedor:' . self::VERSION . ':' . $suffix;
    }
}
