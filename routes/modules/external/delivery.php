<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\DeliveryController;

// Rutas protegidas para usuarios externos de importaciones
Route::group(['prefix' => 'clientes/delivery', 'middleware' => 'jwt.external'], function () {
    Route::get('/agencies', [DeliveryController::class, 'getAgencies']);
    Route::get('/{idConsolidado}', [DeliveryController::class, 'getClientesConsolidado']);
    Route::post('/provincia', [DeliveryController::class, 'storeProvinciaForm']);
});
