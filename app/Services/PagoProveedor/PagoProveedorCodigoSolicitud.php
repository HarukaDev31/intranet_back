<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorSolicitud;

/**
 * Código interno de la solicitud: código de socio + N.° de orden (PE¥01, PE¥02). No se muestra al cliente.
 */
class PagoProveedorCodigoSolicitud
{
    public const SEPARADOR = '¥';

    private const SOCIOS = [
        PagoProveedorSolicitud::COUNTRY_PERU => 'PE',
        PagoProveedorSolicitud::COUNTRY_ECUADOR => 'EC',
        PagoProveedorSolicitud::COUNTRY_BOLIVIA => 'BO',
    ];

    public function armar(?string $pais, int $numeroOrden): string
    {
        return (self::SOCIOS[(string) $pais] ?? 'PE')
            . self::SEPARADOR
            . str_pad((string) $numeroOrden, 2, '0', STR_PAD_LEFT);
    }

    public function deSolicitud(PagoProveedorSolicitud $solicitud): string
    {
        return $this->armar($solicitud->country, (int) $solicitud->order_number);
    }
}
