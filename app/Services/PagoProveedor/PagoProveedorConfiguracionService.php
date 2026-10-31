<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorConfiguracion;

class PagoProveedorConfiguracionService
{
    private ?TarifaPagoProveedor $tariffMemo = null;

    public function __construct(
        private readonly PagoProveedorCacheService $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->cache->rememberConfiguration(function () {
            return PagoProveedorConfiguracion::query()
                ->orderBy('setting_key')
                ->get()
                ->mapWithKeys(fn (PagoProveedorConfiguracion $row) => [$row->setting_key => $row->setting_value])
                ->all();
        });
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $configuration = $this->all();

        return array_key_exists($key, $configuration) ? $configuration[$key] : $default;
    }

    public function save(string $key, mixed $value): void
    {
        PagoProveedorConfiguracion::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
        $this->tariffMemo = null;
        $this->cache->forgetConfiguration();
    }

    public function validityHours(): int
    {
        return max(1, (int) $this->value('vigencia_cotizacion_horas', 48));
    }

    public function tariff(): TarifaPagoProveedor
    {
        if ($this->tariffMemo instanceof TarifaPagoProveedor) {
            return $this->tariffMemo;
        }

        $usd = $this->list('tarifa_bancaria_usd', [100, 120, 150]);
        $rmb = $this->list('tarifa_bancaria_rmb', [50, 90, 120]);

        return $this->tariffMemo = new TarifaPagoProveedor(
            limiteMedio: (float) $this->value('tramo_medio', 3000),
            limiteAlto: (float) $this->value('tramo_alto', 9000),
            bancariaUsdBaja: (float) ($usd[0] ?? 100),
            bancariaUsdMedia: (float) ($usd[1] ?? 120),
            bancariaUsdAlta: (float) ($usd[2] ?? 150),
            bancariaRmbBaja: (float) ($rmb[0] ?? 50),
            bancariaRmbMedia: (float) ($rmb[1] ?? 90),
            bancariaRmbAlta: (float) ($rmb[2] ?? 120),
            administrativa: (float) $this->value('comision_administrativa', 30),
        );
    }

    /**
     * @param  array<int, float|int>  $default
     * @return array<int, float|int>
     */
    private function list(string $key, array $default): array
    {
        $value = $this->value($key, $default);

        return is_array($value) ? array_values($value) : $default;
    }
}
