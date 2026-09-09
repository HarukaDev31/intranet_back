<?php

namespace App\Support\CargaConsolidada;

final class SeguimientoDriveCellRowKey
{
    public static function cotizaciones(int $idCotizacion, ?int $idProveedor = null): string
    {
        if ($idProveedor !== null && $idProveedor > 0) {
            return 'cot:' . $idCotizacion . ':prov:' . $idProveedor;
        }

        return 'cot:' . $idCotizacion;
    }

    public static function contactarProveedor(int $idProveedor): string
    {
        return 'contactar:prov:' . $idProveedor;
    }

    public static function urgenciaProveedor(int $idProveedor): string
    {
        return 'urgencia:prov:' . $idProveedor;
    }

    public static function yiwuProveedor(int $idProveedor): string
    {
        return 'yiwu:prov:' . $idProveedor;
    }

    public static function recibirProveedor(int $idProveedor): string
    {
        return 'recibir:prov:' . $idProveedor;
    }
}
