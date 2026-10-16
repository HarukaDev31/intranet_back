<?php

use App\Http\Controllers\PublicSite\LandingConsolidadoCountdownController;
use Illuminate\Support\Facades\Route;

/*
| API pública (solo lectura): fecha fin del contador de la landing consolidado.
| Autenticación: Authorization: Bearer {LANDING_CONSOLIDADO_FORM_TOKEN}
| (mismo valor que PUBLIC_LANDING_CONSOLIDADO_FORM_TOKEN en Astro)
*/
Route::prefix('public')->group(function () {
    Route::get('landing-consolidado/countdown', [LandingConsolidadoCountdownController::class, 'show'])
        ->middleware('landing.consolidado.form_token')
        ->name('public.landing-consolidado.countdown.show');
});
