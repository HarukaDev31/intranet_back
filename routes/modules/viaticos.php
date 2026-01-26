<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ViaticoController;

/*
|--------------------------------------------------------------------------
| Rutas de Viáticos
|--------------------------------------------------------------------------
|
| Rutas para el módulo de viáticos
|
*/

Route::group(['prefix' => 'viaticos', 'middleware' => 'jwt.auth'], function () {
    // Rutas generales
    Route::get('/', [ViaticoController::class, 'index']);
    Route::post('/', [ViaticoController::class, 'store']);
    Route::get('/{id}', [ViaticoController::class, 'show']);
    Route::put('/{id}', [ViaticoController::class, 'update']);
    Route::delete('/{id}', [ViaticoController::class, 'destroy']);
    
    // Rutas específicas para administración
    Route::get('/pendientes/list', [ViaticoController::class, 'pendientes']);
    Route::get('/completados/list', [ViaticoController::class, 'completados']);
});
