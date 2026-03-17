<?php

namespace App\Services\CalculadoraImportacion;

use App\Models\CalculadoraImportacion;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CalculadoraImportacionCacheService
{
    private const VERSION = 'v1';
    private const TAG = 'calculadora-importacion';

    public function rememberTarifas(callable $resolver): array
    {
        $key = $this->key('tarifas');
        return $this->remember($key, now()->addHours(6), $resolver);
    }

    public function rememberIndex(array $params, callable $resolver): array
    {
        $key = $this->key('index:' . md5(json_encode($this->stableParams($params))));
        return $this->rememberTagged($key, now()->addMinutes(3), $resolver);
    }

    public function rememberShow(int $id, callable $resolver): array
    {
        $key = $this->key("show:{$id}");
        return $this->rememberTagged($key, now()->addMinutes(5), $resolver);
    }

    public function rememberCalculosPorCliente(string $dni, callable $resolver): array
    {
        $dni = trim($dni);
        $key = $this->key('por-cliente:' . $dni);
        return $this->rememberTagged($key, now()->addMinutes(5), $resolver);
    }

    public function rememberClientesByWhatsapp(string $whatsapp, callable $resolver): array
    {
        $normalized = preg_replace('/[^0-9]/', '', $whatsapp);
        if (Str::startsWith($normalized, '51') && strlen($normalized) === 11) {
            $normalized = substr($normalized, 2);
        }
        $key = $this->key('clientes-by-whatsapp:' . $normalized);
        return $this->remember($key, now()->addMinutes(5), $resolver);
    }

    public function invalidateAfterWrite(?CalculadoraImportacion $calculadora = null, array $context = []): void
    {
        // Invalidaciones puntuales
        if ($calculadora) {
            Cache::forget($this->key("show:{$calculadora->id}"));
            if (!empty($calculadora->dni_cliente)) {
                Cache::forget($this->key('por-cliente:' . trim($calculadora->dni_cliente)));
            }
            if (!empty($calculadora->whatsapp_cliente)) {
                $this->invalidateWhatsapp((string) $calculadora->whatsapp_cliente);
            }
        }

        if (!empty($context['dni_cliente'])) {
            Cache::forget($this->key('por-cliente:' . trim((string) $context['dni_cliente'])));
        }
        if (!empty($context['whatsapp'])) {
            $this->invalidateWhatsapp((string) $context['whatsapp']);
        }

        // El listado depende de múltiples filtros → invalidar por tag (si aplica)
        $this->flushTag();

        // Tarifas pueden cambiar raramente; se invalidan si el store soporta tags en write-flows.
        // No las flush aquí por defecto.
    }

    public function flushTarifas(): void
    {
        Cache::forget($this->key('tarifas'));
    }

    private function invalidateWhatsapp(string $whatsapp): void
    {
        $normalized = preg_replace('/[^0-9]/', '', $whatsapp);
        if (Str::startsWith($normalized, '51') && strlen($normalized) === 11) {
            $normalized = substr($normalized, 2);
        }
        Cache::forget($this->key('clientes-by-whatsapp:' . $normalized));
    }

    private function key(string $suffix): string
    {
        return 'calcimp:' . self::VERSION . ':' . $suffix;
    }

    private function remember(string $key, $ttl, callable $resolver): array
    {
        return Cache::remember($key, $ttl, function () use ($resolver) {
            $value = $resolver();
            return is_array($value) ? $value : (array) $value;
        });
    }

    private function rememberTagged(string $key, $ttl, callable $resolver): array
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            return Cache::tags([self::TAG])->remember($key, $ttl, function () use ($resolver) {
                $value = $resolver();
                return is_array($value) ? $value : (array) $value;
            });
        }
        return $this->remember($key, $ttl, $resolver);
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

