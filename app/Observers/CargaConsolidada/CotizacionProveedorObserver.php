<?php

namespace App\Observers\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\CargaConsolidada\CargaConsolidadaCacheService;
use App\Services\CargaConsolidada\ProveedorArriveDateHistoryService;
use App\Services\CargaConsolidada\ProveedorEstadosProveedorHistoryService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;

class CotizacionProveedorObserver
{
    /** @var ProveedorArriveDateHistoryService */
    private $arriveDateHistoryService;

    /** @var ProveedorEstadosProveedorHistoryService */
    private $estadosProveedorHistoryService;

    /** @var CargaConsolidadaCacheService */
    private $cache;

    public function __construct(
        ProveedorArriveDateHistoryService $arriveDateHistoryService,
        ProveedorEstadosProveedorHistoryService $estadosProveedorHistoryService,
        CargaConsolidadaCacheService $cache
    ) {
        $this->arriveDateHistoryService = $arriveDateHistoryService;
        $this->estadosProveedorHistoryService = $estadosProveedorHistoryService;
        $this->cache = $cache;
    }

    /**
     * @param CotizacionProveedor $proveedor
     */
    public function created(CotizacionProveedor $proveedor)
    {
        $this->arriveDateHistoryService->recordInitialDates($proveedor);
        $this->estadosProveedorHistoryService->recordInitialEstado($proveedor);
        $this->cache->invalidateModule();
    }

    /**
     * @param CotizacionProveedor $proveedor
     */
    public function updated(CotizacionProveedor $proveedor)
    {
        $this->arriveDateHistoryService->recordFromProveedorChanges($proveedor);
        $this->estadosProveedorHistoryService->recordFromProveedorChanges($proveedor);
        $this->cache->invalidateIfProveedorAffectsList($proveedor);
    }

    /**
     * @param CotizacionProveedor $proveedor
     */
    public function saved(CotizacionProveedor $proveedor)
    {
        app(SeguimientoConsolidadoDriveService::class)->queueSyncIfLinkedFromProveedor($proveedor);
    }

    public function deleted(CotizacionProveedor $proveedor)
    {
        $this->cache->invalidateModule();
    }
}
