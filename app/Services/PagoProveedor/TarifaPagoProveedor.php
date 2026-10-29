<?php

namespace App\Services\PagoProveedor;

/**
 * Tarifas fijas por tramo (RN-30). Los límites son inclusivos en el tramo medio:
 * menor a $3,000 · de $3,000 a $9,000 · mayor a $9,000.
 */
class TarifaPagoProveedor
{
    public function __construct(
        public float $limiteMedio = 3000,
        public float $limiteAlto = 9000,
        public float $bancariaUsdBaja = 100,
        public float $bancariaUsdMedia = 120,
        public float $bancariaUsdAlta = 150,
        public float $bancariaRmbBaja = 50,
        public float $bancariaRmbMedia = 90,
        public float $bancariaRmbAlta = 120,
        public float $administrativa = 30,
    ) {
    }

    public function bancaria(string $moneda, float $monto): float
    {
        if ($monto <= 0) {
            return 0.0;
        }

        $esUsd = strtoupper($moneda) === 'USD';

        if ($monto > $this->limiteAlto) {
            return $esUsd ? $this->bancariaUsdAlta : $this->bancariaRmbAlta;
        }

        if ($monto >= $this->limiteMedio) {
            return $esUsd ? $this->bancariaUsdMedia : $this->bancariaRmbMedia;
        }

        return $esUsd ? $this->bancariaUsdBaja : $this->bancariaRmbBaja;
    }
}
