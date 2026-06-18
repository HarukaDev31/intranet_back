<?php

namespace App\Services\CargaConsolidada;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ThirdPartyCotizacionExportCacheService
{
    private const VERSION = 'v1';
    private const CACHE_TTL_MINUTES = 10;
    private const LOCK_SECONDS = 30;
    private const BLOCK_SECONDS = 10;

    /**
     * @return array{success: bool, data: array<int, array<string, mixed>>, total: int}
     */
    public function rememberResponse(int $idContenedor, Request $request, callable $resolver): array
    {
        $key = $this->key($idContenedor, $request);
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);
        $lock = Cache::lock("lock:{$key}", self::LOCK_SECONDS);

        return $lock->block(self::BLOCK_SECONDS, function () use ($key, $ttl, $resolver) {
            /** @var array{success: bool, data: array<int, array<string, mixed>>, total: int} $payload */
            $payload = Cache::remember($key, $ttl, function () use ($resolver) {
                $result = $resolver();
                $data = $result['data'] ?? [];

                return [
                    'success' => true,
                    'data' => is_array($data) ? $data : [],
                    'total' => count($data),
                ];
            });

            return $payload;
        });
    }

    private function key(int $idContenedor, Request $request): string
    {
        $params = [
            'estado_coordinacion' => $request->input('estado_coordinacion'),
            'estado_china' => $request->input('estado_china'),
            'tipo_cliente' => $request->input('tipo_cliente'),
            'sort_by' => $request->input('sort_by', 'id'),
            'sort_order' => $request->input('sort_order', 'asc'),
        ];

        ksort($params);

        return sprintf(
            'third-party:%s:cotizaciones-export:%d:%s',
            self::VERSION,
            $idContenedor,
            md5(json_encode($params))
        );
    }
}
