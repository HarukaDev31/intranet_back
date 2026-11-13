<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;

/**
 * Código interno de la solicitud de cada proveedor: código de socio + símbolo de la moneda + correlativo.
 * El correlativo es propio de cada proveedor y único en todo Pago a Proveedor (request_number): una orden
 * con dos proveedores tiene PE¥70 y PE¥71 (#1686). Yuanes: PE¥01. Dólares: PE$01. No se muestra al cliente.
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

    /**
     * Código del proveedor según su correlativo; sin correlativo, el de su solicitud (N.° de orden).
     */
    public function deProveedor(PagoProveedorSolicitudProveedor $proveedor, ?PagoProveedorSolicitud $solicitud = null): string
    {
        $solicitud ??= $proveedor->solicitud;
        if ($proveedor->request_number === null) {
            return $solicitud ? $this->deSolicitud($solicitud, $proveedor->currency) : $this->armar(null, 0, $proveedor->currency);
        }

        return $this->armar($solicitud?->country, (int) $proveedor->request_number, $proveedor->currency);
    }

    /**
     * Correlativo del texto buscado cuando tiene forma de código (PE¥70, pe$7, ¥70). Null si no lo es.
     */
    public function numeroBuscado(string $texto): ?int
    {
        return preg_match('/^(?:[A-Z]{2})?[¥$]0*(\d+)$/iu', trim($texto), $partes) ? (int) $partes[1] : null;
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
     * Código guardado en formato actual rearmado con el correlativo del proveedor (PE¥70 → PE¥71). Null si no tiene ese formato.
     */
    public function conNumero(string $codigo, int $numero, ?string $moneda): ?string
    {
        if (!preg_match(self::PATRON, $codigo, $partes)) {
            return null;
        }

        return $partes[1] . $this->simbolo($moneda) . str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
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
