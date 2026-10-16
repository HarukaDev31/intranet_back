<?php

use App\Http\Controllers\PublicSite\LandingConsolidadoCountdownController;
use Illuminate\Support\Facades\Route;

/*
| API pública (solo lectura): fecha fin del contador de la landing curso.
| Autenticación: Authorization: Bearer con cualquiera de:
| - LANDING_CURSO_FORM_TOKEN (= PUBLIC_LANDING_CURSO_FORM_TOKEN)
| - LANDING_CONSOLIDADO_FORM_TOKEN (= PUBLIC_LANDING_CONSOLIDADO_FORM_TOKEN)
*/
Route::prefix('public')->group(function () {
    Route::get('landing-consolidado/countdown', [LandingConsolidadoCountdownController::class, 'show'])
        ->middleware('landing.countdown.form_token')
        ->name('public.landing-consolidado.countdown.show');
});
