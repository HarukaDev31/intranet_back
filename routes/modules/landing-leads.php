<?php

use App\Http\Controllers\Landing\LandingLeadAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Leads de Landing (privadas)
|--------------------------------------------------------------------------
|
| Consumo interno desde intranet para listar leads de:
| - landing_consolidado_leads
| - landing_curso_leads
|
*/

Route::group(['prefix' => 'landing-leads', 'middleware' => 'jwt.auth'], function () {
    Route::get('consolidado', [LandingLeadAdminController::class, 'consolidado']);
    Route::get('curso', [LandingLeadAdminController::class, 'curso']);
    Route::get('consolidado/export', [LandingLeadAdminController::class, 'exportConsolidado']);
    Route::get('curso/export', [LandingLeadAdminController::class, 'exportCurso']);
});

