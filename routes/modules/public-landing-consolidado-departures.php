<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\LandingConsolidadoDeparturesController;

/*
| API pública (solo lectura): próximas fechas de cierre de contenedores
| pendientes (estado_china = PENDIENTE) para landing Astro.
| Autenticación: Authorization: Bearer {LANDING_CONSOLIDADO_FORM_TOKEN}
*/
Route::prefix('public')->group(function () {
    Route::get('landing-consolidado/next-departures', [LandingConsolidadoDeparturesController::class, 'index'])
        ->middleware('landing.consolidado.form_token')
        ->name('public.landing-consolidado.next-departures.index');
});
