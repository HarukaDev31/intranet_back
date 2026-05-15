<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SoporteTi\SoporteTiSolicitudController;
use App\Http\Controllers\SoporteTi\SoporteTiEstadoController;
use App\Http\Controllers\SoporteTi\SoporteTiChatController;

/*
|--------------------------------------------------------------------------
| Soporte TI — API intranet
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'soporte-ti', 'middleware' => 'jwt.auth'], function () {
    Route::get('/estados', [SoporteTiEstadoController::class, 'index']);
    Route::get('/chats/{chatUuid}/mensajes', [SoporteTiChatController::class, 'mensajes']);

    Route::get('/solicitudes', [SoporteTiSolicitudController::class, 'index']);
    Route::post('/solicitudes', [SoporteTiSolicitudController::class, 'store']);

    Route::get('/solicitudes/{id}/estados/historial', [SoporteTiSolicitudController::class, 'historialEstados']);
    Route::post('/solicitudes/{id}/estado', [SoporteTiSolicitudController::class, 'cambiarEstado']);
    Route::post('/solicitudes/{id}/mensajes', [SoporteTiSolicitudController::class, 'postMensaje']);
    Route::post('/solicitudes/{id}/maqueta', [SoporteTiSolicitudController::class, 'postMaqueta']);

    Route::get('/solicitudes/{id}', [SoporteTiSolicitudController::class, 'show']);
    Route::put('/solicitudes/{id}', [SoporteTiSolicitudController::class, 'update']);
});
