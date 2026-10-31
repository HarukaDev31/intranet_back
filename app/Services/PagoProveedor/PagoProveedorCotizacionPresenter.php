<?php

namespace App\Services\PagoProveedor;

use App\Http\Resources\PagoProveedor\CotizacionResource;
use App\Models\PagoProveedor\PagoProveedorCotizacion;

class PagoProveedorCotizacionPresenter
{
    /**
     * @param  array<int, array<string, mixed>>  $codigosDisponibles
     * @return array<string, mixed>
     */
    public function cotizacion(
        PagoProveedorCotizacion $cotizacion,
        TarifaPagoProveedor $tarifa,
        bool $conHistorial = false,
        array $codigosDisponibles = []
    ): array {
        $resource = (new CotizacionResource($cotizacion))->supplierCodes($codigosDisponibles)->usingTarifa($tarifa);
        if ($conHistorial) {
            $resource->includeHistory();
        }

        return $resource->resolve();
    }
}
