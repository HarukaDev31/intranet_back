<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CargaConsolidada\CotizacionController;

/*
| API pública (solo lectura): datos de cotizaciones para integraciones externas.
| Autenticación: Authorization: Bearer {THIRD_PARTY_READ_ONLY_TOKEN}
*/
Route::prefix('public')->group(function () {
    Route::get('carga-consolidada/contenedor/cotizaciones/{idContenedor}/exportar', [CotizacionController::class, 'exportarCotizacionJson'])
        ->middleware(['third_party.token_access', 'throttle:third-party.cotizaciones-export'])
        ->name('public.carga-consolidada.cotizaciones.exportar-json');
});
