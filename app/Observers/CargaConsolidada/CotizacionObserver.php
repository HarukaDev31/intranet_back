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
    }
}
