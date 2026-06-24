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
}
