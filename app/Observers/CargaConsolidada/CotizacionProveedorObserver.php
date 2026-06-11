<?php

namespace App\Observers\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;

class CotizacionProveedorObserver
{
    /**
     * @param CotizacionProveedor $proveedor
     */
    public function saved(CotizacionProveedor $proveedor)
    {
        app(SeguimientoConsolidadoDriveService::class)->queueSyncIfLinkedFromProveedor($proveedor);
    }
}
