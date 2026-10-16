<?php

namespace App\Services\Landing;

use App\Services\SystemConfigService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class LandingConsolidadoCountdownService
{
    public const CONFIG_KEY = 'landing_consolidado_countdown_ends_at';
    public const CACHE_KEY = 'landing_consolidado:countdown:v1';
    public const CACHE_TTL_SECONDS = 3600;
    public const DEFAULT_ENDS_AT = '2026-10-06T23:59:59-05:00';
    public const TIMEZONE = 'America/Lima';

    /** @var SystemConfigService */
    protected $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @return array{ends_at: string, timezone: string}
     */
    public function getPublicPayload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->buildPayload();
        });
    }

    /**
     * @return array{ends_at: string, timezone: string}
     */
    public function getAdminPayload(): array
    {
        return $this->buildPayload();
    }

    /**
     * @param string $endsAt ISO 8601 o datetime parseable
     * @return array{ends_at: string, timezone: string}
     */
    public function updateEndsAt(string $endsAt): array
    {
        $normalized = $this->normalizeEndsAt($endsAt);

        $this->systemConfigService->set(
            self::CONFIG_KEY,
            $normalized,
            'Fecha/hora fin del contador de la landing curso (America/Lima)'
        );

        Cache::forget(self::CACHE_KEY);
        $this->systemConfigService->forgetCache(self::CONFIG_KEY);

        return $this->buildPayload();
    }

    /**
     * @return array{ends_at: string, timezone: string}
     */
    protected function buildPayload(): array
    {
        $raw = $this->systemConfigService->get(self::CONFIG_KEY, self::DEFAULT_ENDS_AT);
        $endsAt = $this->normalizeEndsAt((string) $raw);

        return [
            'ends_at' => $endsAt,
            'timezone' => self::TIMEZONE,
        ];
    }

    protected function normalizeEndsAt(string $value): string
    {
        $carbon = Carbon::parse($value, self::TIMEZONE)->setTimezone(self::TIMEZONE);

        return $carbon->format('Y-m-d\TH:i:sP');
    }
}
