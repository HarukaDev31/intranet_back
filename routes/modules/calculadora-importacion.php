<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CalculadoraImportacion\CalculadoraImportacionController;

/*
|--------------------------------------------------------------------------
| Rutas de Calculadora de Importación
|--------------------------------------------------------------------------
|
| Rutas para usuarios internos y externos de la calculadora de importación
|
*/

// Calculadora - Usuarios internos
Route::group(['prefix' => 'calculadora-importacion', 'middleware' => 'jwt.auth'], function () {
    Route::post('clientes', [CalculadoraImportacionController::class, 'getClientesByWhatsapp']);
    Route::get('tarifas', [CalculadoraImportacionController::class, 'getTarifas']);
    Route::post('duplicate/{id}', [CalculadoraImportacionController::class, 'duplicate']);
    Route::get('/', [CalculadoraImportacionController::class, 'index']);
    Route::post('/', [CalculadoraImportacionController::class, 'store']);
    Route::get('/{id}', [CalculadoraImportacionController::class, 'show']);
    Route::get('/cliente', [CalculadoraImportacionController::class, 'getCalculosPorCliente']);
    Route::post('/change-estado/{id}', [CalculadoraImportacionController::class, 'changeEstado']);
    Route::delete('/{id}', [CalculadoraImportacionController::class, 'destroy']);
});

// Calculadora - Usuarios externos
Route::group(['prefix' => 'calculadora-importacion-external', 'middleware' => 'jwt.external'], function () {
    Route::get('/', [CalculadoraImportacionController::class, 'indexExternal']);
    Route::post('/', [CalculadoraImportacionController::class, 'storeExternal']);
    Route::get('/{id}', [CalculadoraImportacionController::class, 'showExternal']);
    Route::put('/{id}', [CalculadoraImportacionController::class, 'updateExternal']);
    Route::delete('/{id}', [CalculadoraImportacionController::class, 'destroyExternal']);
});
