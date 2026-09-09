<?php

namespace App\Observers\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CalculadoraImportacion;
use App\Services\CargaConsolidada\CargaConsolidadaCacheService;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;

class CotizacionObserver
{
    /** @var CargaConsolidadaCacheService */
    private $cache;

    public function __construct(CargaConsolidadaCacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Al pasar a CONFIRMADO se registra fecha_confirmacion; al salir de CONFIRMADO se limpia.
     */
    public function updating(Cotizacion $cotizacion): void
    {
        if (! $cotizacion->isDirty('estado_cotizador')) {
            return;
        }

        if ($cotizacion->estado_cotizador === 'CONFIRMADO') {
            $cotizacion->fecha_confirmacion = now();

            return;
        }

        if ($cotizacion->getOriginal('estado_cotizador') === 'CONFIRMADO') {
            $cotizacion->fecha_confirmacion = null;
        }
    }

    public function updated(Cotizacion $cotizacion): void
    {
        // Si el estado del cotizador cambió, sincronizar la calculadora según el nuevo valor
        if ($cotizacion->wasChanged('estado_cotizador')) {
            if ($cotizacion->estado_cotizador === 'CONFIRMADO') {
                CalculadoraImportacion::where('id_cotizacion', $cotizacion->id)
                    ->update(['estado' => CalculadoraImportacion::ESTADO_CONFIRMADO]);
            } elseif ($cotizacion->estado_cotizador === 'PENDIENTE') {
                // Cuando la cotización vuelve a PENDIENTE, en la calculadora debe quedar como COTIZADO
                CalculadoraImportacion::where('id_cotizacion', $cotizacion->id)
                    ->update(['estado' => CalculadoraImportacion::ESTADO_COTIZADO]);
            }
        }

        if (!empty($cotizacion->id_contenedor)) {
            app(SeguimientoConsolidadoDriveService::class)->queueSyncIfLinked((int) $cotizacion->id_contenedor);
        }

        $this->cache->invalidateIfCotizacionAffectsList($cotizacion);
    }

    public function created(Cotizacion $cotizacion): void
    {
        if (!empty($cotizacion->id_contenedor)) {
            app(SeguimientoConsolidadoDriveService::class)->queueSyncIfLinked((int) $cotizacion->id_contenedor);
        }

        $this->cache->invalidateModule();
    }

    public function deleted(Cotizacion $cotizacion): void
    {
        $this->cache->invalidateModule();
    }

    public function restored(Cotizacion $cotizacion): void
    {
        $this->cache->invalidateModule();
    }
}
