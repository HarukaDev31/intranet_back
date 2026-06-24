<?php

namespace App\Observers\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\CargaConsolidada\ProveedorArriveDateHistoryService;
use App\Services\CargaConsolidada\ProveedorEstadosProveedorHistoryService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;

class CotizacionProveedorObserver
{
    /** @var ProveedorArriveDateHistoryService */
    private $arriveDateHistoryService;

    /** @var ProveedorEstadosProveedorHistoryService */
    private $estadosProveedorHistoryService;

    public function __construct(
        ProveedorArriveDateHistoryService $arriveDateHistoryService,
        ProveedorEstadosProveedorHistoryService $estadosProveedorHistoryService
    ) {
        $this->arriveDateHistoryService = $arriveDateHistoryService;
        $this->estadosProveedorHistoryService = $estadosProveedorHistoryService;
    }

    /**
     * @param CotizacionProveedor $proveedor
     */
    public function created(CotizacionProveedor $proveedor)
    {
        $this->arriveDateHistoryService->recordInitialDates($proveedor);
        $this->estadosProveedorHistoryService->recordInitialEstado($proveedor);
    }

    /**
     * @param CotizacionProveedor $proveedor
     */
    public function updated(CotizacionProveedor $proveedor)
    {
        $this->arriveDateHistoryService->recordFromProveedorChanges($proveedor);
        $this->estadosProveedorHistoryService->recordFromProveedorChanges($proveedor);
    }

    /**
     * @param CotizacionProveedor $proveedor
     */
    public function saved(CotizacionProveedor $proveedor)
    {
        app(SeguimientoConsolidadoDriveService::class)->queueSyncIfLinkedFromProveedor($proveedor);
    }
}
