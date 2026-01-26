<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Dashboard por Usuario
|--------------------------------------------------------------------------
|
| Rutas para dashboard personalizado del usuario autenticado.
| Todas las rutas requieren autenticación JWT y solo muestran
| información del usuario autenticado.
|
*/

Route::group(['prefix' => 'dashboard-usuario', 'middleware' => 'jwt.auth'], function () {
    Route::prefix('ventas')->group(function () {
        Route::get('/resumen', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getResumenVentas']);
        Route::get('/por-vendedor', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getVentasPorVendedor']);
        Route::get('/filtros/contenedores', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getContenedoresFiltro']);
        Route::get('/filtros/vendedores', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getVendedoresFiltro']);
        Route::get('/evolucion/{idContenedor}', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getEvolucionContenedor']);
        Route::get('/evolucion-total', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getEvolucionTotal']);
        Route::get('/cotizaciones-confirmadas-por-vendedor-por-dia', [App\Http\Controllers\CargaConsolidada\DashboardUsuarioController::class, 'getCotizacionesConfirmadasPorVendedorPorDia']);
    });
});

