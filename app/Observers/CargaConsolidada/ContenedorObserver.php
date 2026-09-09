<?php

namespace App\Observers\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Services\CargaConsolidada\CargaConsolidadaCacheService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoVincularEligibility;

class ContenedorObserver
{
    /** @var CargaConsolidadaCacheService */
    private $cache;

    public function __construct(CargaConsolidadaCacheService $cache)
    {
        $this->cache = $cache;
    }

    public function created(Contenedor $contenedor): void
    {
        $this->cache->invalidateModule();

        if (!SeguimientoConsolidadoVincularEligibility::puedeVincular($contenedor)) {
            return;
        }

        app(SeguimientoConsolidadoDriveService::class)->queueVincular((int) $contenedor->id);
    }

    public function updated(Contenedor $contenedor): void
    {
        $this->cache->invalidateModule();
    }

    public function deleted(Contenedor $contenedor): void
    {
        $this->cache->invalidateModule();
    }

    public function restored(Contenedor $contenedor): void
    {
        $this->cache->invalidateModule();
    }
}
