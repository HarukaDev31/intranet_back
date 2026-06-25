<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\NotificacionPreferenciaController;

/*
|--------------------------------------------------------------------------
| Rutas de Notificaciones
|--------------------------------------------------------------------------
|
| Rutas para gestión de notificaciones
|
*/

Route::group(['prefix' => 'notificaciones', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [NotificacionController::class, 'index']);
    Route::get('/conteo-no-leidas', [NotificacionController::class, 'conteoNoLeidas']);

    // Preferencias de notificaciones websocket (panel por usuario).
    // Deben declararse antes de /{id} para que no las capture la ruta dinámica.
    Route::get('/preferencias', [NotificacionPreferenciaController::class, 'index']);
    Route::put('/preferencias', [NotificacionPreferenciaController::class, 'update']);

    Route::get('/{id}', [NotificacionController::class, 'show']);
    Route::post('/', [NotificacionController::class, 'store']);
    Route::post('/marcar-multiples-leidas', [NotificacionController::class, 'marcarMultiplesComoLeidas']);
    Route::put('/{id}/archivar', [NotificacionController::class, 'archivar']);
    Route::put('/{id}/marcar-leida', [NotificacionController::class, 'marcarComoLeida']);
});
