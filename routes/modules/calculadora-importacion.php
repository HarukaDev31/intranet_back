<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CalculadoraImportacion\CalculadoraImportacionController;
use App\Http\Controllers\CalculadoraImportacion\CalculadoraImportacionDocumentosController;

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
    Route::get('export-list', [CalculadoraImportacionController::class, 'exportList']);
    Route::post('export-cotizacion', [CalculadoraImportacionController::class, 'exportCotizacion']);
    Route::post('/', [CalculadoraImportacionController::class, 'store']);
    // Vincula / crea la cotización en carga consolidada desde una fila de calculadora
    // sin necesidad de cambiar el estado manualmente a "COTIZADO" desde el front.
    Route::post('vincular-cotizacion/{id}', [CalculadoraImportacionController::class, 'vincularCotizacionDesdeCalculadora']);
    Route::get('/cliente', [CalculadoraImportacionController::class, 'getCalculosPorCliente']);
    Route::post('/change-estado/{id}', [CalculadoraImportacionController::class, 'changeEstado']);

    // Documentos asociados a cotización calculadora
    Route::get('/{id}/documentos', [CalculadoraImportacionDocumentosController::class, 'index']);
    Route::post('/{id}/documentos', [CalculadoraImportacionDocumentosController::class, 'store']);
    Route::delete('/documentos/{idDocumento}', [CalculadoraImportacionDocumentosController::class, 'destroy']);

    Route::get('/{id}', [CalculadoraImportacionController::class, 'show']);
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
