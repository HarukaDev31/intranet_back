<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\LandingCursoLeadController;

/*
| API pública: lead landing curso (Astro).
| Autenticación: Authorization: Bearer {LANDING_CURSO_FORM_TOKEN}
*/
Route::prefix('public')->group(function () {
    Route::post('landing-curso/leads', [LandingCursoLeadController::class, 'store'])
        ->middleware('landing.curso.form_token')
        ->name('public.landing-curso.leads.store');
});
