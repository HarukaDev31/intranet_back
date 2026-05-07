<?php

namespace App\Support;

/**
 * Persistencia segura DECIMAL(?,2) sin pérdida por float/string intermedios.
 */
final class MonetarioDosDecimales
{
    /**
     * @param  mixed  $value  string numérico, int, float o null
     */
    public static function paraBd($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_bool($value)) {
            return null;
        }

        if (\is_string($value)) {
            $s = str_replace(',', '.', preg_replace('/\s+/', '', $value));

            if ($s === '' || !is_numeric($s)) {
                return null;
            }

            return bcadd($s, '0', 2);
        }

        if (\is_int($value)) {
            return bcdiv((string) $value, '1', 2);
        }

        if (\is_float($value)) {
            $cents = (int) round(round($value, 10) * 100);
            $sign = '';
            if ($cents < 0) {
                $sign = '-';
                $cents = -$cents;
            }

            return $sign . bcdiv((string) $cents, '100', 2);
        }

        return null;
    }

    /**
     * Suma valores ya persistidos como DECIMAL/ string (evita SUM() agregado como float en PHP).
     *
     * @param  iterable<int|string|null>  $valoresMontosCrudos
     */
    public static function sumarMontosColumnaBd(iterable $valoresMontosCrudos): string
    {
        if (!\function_exists('bcadd')) {
            $acc = 0.0;
            foreach ($valoresMontosCrudos as $monto) {
                if ($monto === null || $monto === '') {
                    continue;
                }
                $piece = str_replace(',', '.', preg_replace('#\s+#', '', (string) $monto));
                if ($piece === '' || !is_numeric($piece)) {
                    continue;
                }
                $acc += (float) $piece;
            }

            return sprintf('%.2F', round($acc, 2));
        }

        $sum = '0.00';
        foreach ($valoresMontosCrudos as $monto) {
            if ($monto === null || $monto === '') {
                continue;
            }
            $piece = str_replace(',', '.', preg_replace('#\s+#', '', (string) $monto));
            if ($piece === '' || !is_numeric($piece)) {
                continue;
            }
            $piece2 = bcadd($piece, '0', 2);
            $sum = bcadd($sum, $piece2, 2);
        }

        return bcadd($sum, '0', 2);
    }
}
