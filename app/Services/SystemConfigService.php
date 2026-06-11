<?php

namespace App\Services;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemConfigService
{
    public const KEY_EXCEL_SEGUIMIENTO_HORA_CORTE = 'excel_seguimiento_hora_corte';
    public const KEY_EXCEL_SEGUIMIENTO_TIMEZONE = 'excel_seguimiento_timezone';

    private const CACHE_PREFIX = 'system_config:';
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function get($key, $default = null)
    {
        if (!Schema::hasTable('system_configs')) {
            return $default;
        }

        return Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL_SECONDS,
            function () use ($key, $default) {
                $row = SystemConfig::query()->where('key', $key)->first();

                if (!$row || $row->value === null || trim((string) $row->value) === '') {
                    return $default;
                }

                return trim((string) $row->value);
            }
        );
    }

    /**
     * @param string $key
     * @param string $value
     * @param string|null $description
     */
    public function set($key, $value, $description = null)
    {
        if (!Schema::hasTable('system_configs')) {
            throw new \RuntimeException('Tabla system_configs no disponible.');
        }

        $payload = [
            'value' => $value,
        ];

        if ($description !== null) {
            $payload['description'] = $description;
        }

        SystemConfig::query()->updateOrCreate(
            ['key' => $key],
            $payload
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public function forgetCache($key)
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
