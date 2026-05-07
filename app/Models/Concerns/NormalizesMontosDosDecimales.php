<?php

namespace App\Models\Concerns;

trait NormalizesMontosDosDecimales
{
    /**
     * Evita pérdida de precisión por float/point al persistir DECIMAL(?,2).
     */
    public function setMontoAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['monto'] = null;

            return;
        }

        if (\is_bool($value)) {
            $this->attributes['monto'] = null;

            return;
        }

        if (\is_string($value)) {
            $s = str_replace(',', '.', preg_replace('/\s+/', '', $value));

            if ($s === '' || !is_numeric($s)) {
                $this->attributes['monto'] = null;

                return;
            }

            $this->attributes['monto'] = bcadd($s, '0', 2);

            return;
        }

        if (\is_int($value)) {
            $this->attributes['monto'] = bcdiv((string) $value, '1', 2);

            return;
        }

        if (\is_float($value)) {
            $cents = (int) round(round($value, 10) * 100);
            $sign = '';
            if ($cents < 0) {
                $sign = '-';
                $cents = -$cents;
            }

            $this->attributes['monto'] = $sign . bcdiv((string) $cents, '100', 2);

            return;
        }

        $this->attributes['monto'] = null;
    }
}
