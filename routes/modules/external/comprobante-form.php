<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\ComprobanteFormController;

Route::group(['prefix' => 'clientes/comprobante-form', 'middleware' => 'jwt.external'], function () {
    // Obtener importadores (cotizaciones confirmadas) para un contenedor
    Route::get('/{idContenedor}', [ComprobanteFormController::class, 'getClientes']);
    // Guardar formulario
    Route::post('/{idContenedor}', [ComprobanteFormController::class, 'store']);
});
