<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorSolicitud;

/**
 * Código interno de la solicitud: código de socio + símbolo de la moneda + N.° de orden.
 * Yuanes: PE¥01. Dólares: PE$01. No se muestra al cliente.
 */
class PagoProveedorCodigoSolicitud
{
    public const SEPARADOR = '¥';

    public const SEPARADOR_USD = '$';

    /** Formato actual: socio + ¥ o $ + número. */
    public const PATRON = '/^([A-Z]{2})[¥$](\d+)$/u';

    private const SOCIOS = [
        PagoProveedorSolicitud::COUNTRY_PERU => 'PE',
        PagoProveedorSolicitud::COUNTRY_ECUADOR => 'EC',
        PagoProveedorSolicitud::COUNTRY_BOLIVIA => 'BO',
    ];

    public function armar(?string $pais, int $numeroOrden, ?string $moneda = null): string
    {
        return (self::SOCIOS[(string) $pais] ?? 'PE')
            . $this->simbolo($moneda)
            . str_pad((string) $numeroOrden, 2, '0', STR_PAD_LEFT);
    }

    public function deSolicitud(PagoProveedorSolicitud $solicitud, ?string $moneda = null): string
    {
        return $this->armar($solicitud->country, (int) $solicitud->order_number, $moneda);
    }

    public function simbolo(?string $moneda): string
    {
        return $moneda === PagoProveedorCotizacionProveedor::CURRENCY_USD ? self::SEPARADOR_USD : self::SEPARADOR;
    }

    /**
     * Ajusta el símbolo de un código ya en formato actual a la moneda (PE¥05 en dólares → PE$05). Null si no tiene ese formato.
     */
    public function conMoneda(string $codigo, ?string $moneda): ?string
    {
        if (!preg_match(self::PATRON, $codigo, $partes)) {
            return null;
        }

        return $partes[1] . $this->simbolo($moneda) . $partes[2];
    }
}
