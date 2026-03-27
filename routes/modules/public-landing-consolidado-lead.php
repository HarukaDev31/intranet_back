<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\LandingConsolidadoLeadController;

/*
| API pública: lead “Únete al próximo consolidado” (landing Astro).
| Autenticación: Authorization: Bearer {LANDING_CONSOLIDADO_FORM_TOKEN}
*/
Route::prefix('public')->group(function () {
    Route::post('landing-consolidado/leads', [LandingConsolidadoLeadController::class, 'store'])
        ->middleware('landing.consolidado.form_token')
        ->name('public.landing-consolidado.leads.store');
});
