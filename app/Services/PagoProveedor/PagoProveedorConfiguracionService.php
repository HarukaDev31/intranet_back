<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorConfiguracion;

class PagoProveedorConfiguracionService
{
    public function todas(): array
    {
        return PagoProveedorConfiguracion::query()
            ->orderBy('clave')
            ->get()
            ->mapWithKeys(fn (PagoProveedorConfiguracion $fila) => [$fila->clave => $fila->valor])
            ->all();
    }

    public function obtener(string $clave, mixed $defecto = null): mixed
    {
        $fila = PagoProveedorConfiguracion::query()->where('clave', $clave)->first();

        return $fila ? $fila->valor : $defecto;
    }

    public function guardar(string $clave, mixed $valor): void
    {
        PagoProveedorConfiguracion::query()->updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor]
        );
    }

    public function horasVigencia(): int
    {
        return max(1, (int) $this->obtener('vigencia_cotizacion_horas', 48));
    }

    public function tarifa(): TarifaPagoProveedor
    {
        $usd = $this->lista('tarifa_bancaria_usd', [100, 120, 150]);
        $rmb = $this->lista('tarifa_bancaria_rmb', [50, 90, 120]);

        return new TarifaPagoProveedor(
            limiteMedio: (float) $this->obtener('tramo_medio', 3000),
            limiteAlto: (float) $this->obtener('tramo_alto', 9000),
            bancariaUsdBaja: (float) ($usd[0] ?? 100),
            bancariaUsdMedia: (float) ($usd[1] ?? 120),
            bancariaUsdAlta: (float) ($usd[2] ?? 150),
            bancariaRmbBaja: (float) ($rmb[0] ?? 50),
            bancariaRmbMedia: (float) ($rmb[1] ?? 90),
            bancariaRmbAlta: (float) ($rmb[2] ?? 120),
            administrativa: (float) $this->obtener('comision_administrativa', 30),
        );
    }

    /**
     * @param  array<int, float|int>  $defecto
     * @return array<int, float|int>
     */
    private function lista(string $clave, array $defecto): array
    {
        $valor = $this->obtener($clave, $defecto);

        return is_array($valor) ? array_values($valor) : $defecto;
    }
}
