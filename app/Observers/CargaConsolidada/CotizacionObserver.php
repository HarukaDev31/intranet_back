<?php

namespace App\Observers\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CalculadoraImportacion;

class CotizacionObserver
{
    /**
     * Handle the Cotizacion "updated" event.
     */
    public function updated(Cotizacion $cotizacion): void
    {
        // Si el estado del cotizador cambió a CONFIRMADO, sincronizar la calculadora
        if ($cotizacion->wasChanged('estado_cotizador') && $cotizacion->estado_cotizador === 'CONFIRMADO') {
            CalculadoraImportacion::where('id_cotizacion', $cotizacion->id)
                ->update(['estado' => CalculadoraImportacion::ESTADO_CONFIRMADO]);
        }
    }
}
