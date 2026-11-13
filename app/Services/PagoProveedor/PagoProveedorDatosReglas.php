<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;

class PagoProveedorDatosReglas
{
    public static function wechat(string $valor): string
    {
        $limpio = preg_replace('/\s+/', '', $valor) ?? '';
        if ($limpio === '' || !preg_match('/^\d+$/', $limpio)) {
            throw new PagoProveedorException('El WeChat debe tener solo dígitos.');
        }

        return $limpio;
    }

    /**
     * SWIFT/BIC: 8 u 11 caracteres, 6 letras y el resto alfanumérico.
     */
    public static function codigoBanco(string $tipo, string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        if ($tipo !== 'SWIFT') {
            if ($codigo === '') {
                throw new PagoProveedorException('Falta el código del banco.');
            }

            return $codigo;
        }

        if (!preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $codigo)) {
            throw new PagoProveedorException('El código SWIFT/BIC debe tener 8 u 11 caracteres alfanuméricos.');
        }

        return $codigo;
    }
}
