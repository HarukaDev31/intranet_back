<?php

use App\Http\Controllers\PublicSite\LandingConsolidadoCountdownController;
use Illuminate\Support\Facades\Route;

/*
| API pública (solo lectura): fecha fin del contador de la landing curso.
| Autenticación: Authorization: Bearer {LANDING_CURSO_FORM_TOKEN}
| (mismo valor que PUBLIC_LANDING_CURSO_FORM_TOKEN en Astro / Netlify)
|
| La ruta conserva el path histórico /landing-consolidado/countdown.
*/
Route::prefix('public')->group(function () {
    Route::get('landing-consolidado/countdown', [LandingConsolidadoCountdownController::class, 'show'])
        ->middleware('landing.curso.form_token')
        ->name('public.landing-consolidado.countdown.show');
});
