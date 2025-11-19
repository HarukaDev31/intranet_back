<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;

/*
|--------------------------------------------------------------------------
| Rutas de Campañas
|--------------------------------------------------------------------------
|
| Rutas para gestión de campañas
|
*/

Route::group(['prefix' => 'campaigns', 'middleware' => 'jwt.auth'], function () {
    
    Route::get('/', [CampaignController::class, 'index']);
    Route::post('/', [CampaignController::class, 'store']);
    Route::get('/{id}/students', [CampaignController::class, 'getStudents']);
    Route::get('{id}', [CampaignController::class, 'show']);
    Route::put('{id}', [CampaignController::class, 'update']);
    Route::delete('{id}', [CampaignController::class, 'destroy']);
});
