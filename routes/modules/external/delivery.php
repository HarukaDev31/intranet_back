<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\DeliveryController;
use App\Http\Controllers\CargaConsolidada\EntregaController;

// Rutas protegidas para usuarios externos de importaciones
Route::group(['prefix' => 'clientes/delivery', 'middleware' => 'jwt.external'], function () {
    Route::get('/agencies', [DeliveryController::class, 'getAgencies']);
    Route::post('/provincia', [DeliveryController::class, 'storeProvinciaForm']);
    Route::post('/lima', [DeliveryController::class, 'storeLimaForm']);
    Route::get('/horarios-disponibles/{idConsolidado}', [EntregaController::class, 'getHorariosDisponibles']);
    Route::get('/{idConsolidado}', [DeliveryController::class, 'getClientesConsolidado']);
   
});
