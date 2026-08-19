<?php

namespace App\Support\Cache;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * Convierte payloads de caché a arrays serializables (evita __PHP_Incomplete_Class al deserializar).
 */
final class CachePayloadNormalizer
{
    public static function resolveArray(callable $resolver): array
    {
        $value = $resolver();

        return self::normalizePayloadArray(is_array($value) ? $value : (array) $value);
    }

    public static function normalizePayloadArray(array $payload): array
    {
        $normalized = self::normalize($payload);

        return is_array($normalized) ? $normalized : [];
    }

    public static function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Model || $value instanceof Arrayable) {
            return self::normalize($value->toArray());
        }

        if ($value instanceof Collection) {
            return self::normalize($value->all());
        }

        if ($value instanceof AbstractPaginator) {
            return self::normalize([
                'items' => $value->items(),
                'current_page' => $value->currentPage(),
                'last_page' => $value->lastPage(),
                'per_page' => $value->perPage(),
                'total' => $value->total(),
                'from' => $value->firstItem(),
                'to' => $value->lastItem(),
            ]);
        }

        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize());
        }

        if (is_object($value)) {
            if ($value instanceof \__PHP_Incomplete_Class) {
                return null;
            }

            if ($value instanceof \stdClass) {
                return self::normalize((array) $value);
            }

            return self::normalize(get_object_vars($value));
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalize($item);
        }

        return $normalized;
    }

    public static function containsUnsafeCachedValue(mixed $value): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        if ($value instanceof Model || $value instanceof Collection || $value instanceof AbstractPaginator) {
            return true;
        }

        if (is_object($value) && ! $value instanceof \stdClass) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (self::containsUnsafeCachedValue($item)) {
                return true;
            }
        }

        return false;
    }
}
