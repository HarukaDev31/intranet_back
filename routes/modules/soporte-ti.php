<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SoporteTi\SoporteTiSolicitudController;
use App\Http\Controllers\SoporteTi\SoporteTiEstadoController;
use App\Http\Controllers\SoporteTi\SoporteTiChatController;
use App\Http\Controllers\SoporteTi\SoporteTiSlaHorasController;
use App\Http\Controllers\SoporteTi\SoporteTiFaseHorasController;

/*
|--------------------------------------------------------------------------
| Soporte TI — API intranet
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'soporte-ti', 'middleware' => 'jwt.auth'], function () {
    Route::get('/estados', [SoporteTiEstadoController::class, 'index']);
    Route::get('/sla-horas', [SoporteTiSlaHorasController::class, 'index']);
    Route::put('/sla-horas', [SoporteTiSlaHorasController::class, 'update']);
    Route::get('/fase-horas-a', [SoporteTiFaseHorasController::class, 'index']);
    Route::put('/fase-horas-a', [SoporteTiFaseHorasController::class, 'update']);
    Route::get('/chats/{chatUuid}/mensajes', [SoporteTiChatController::class, 'mensajes']);
    Route::post('/chats/{chatUuid}/mensajes/leidos', [SoporteTiChatController::class, 'marcarLeidos']);
    Route::get('/chats/{chatUuid}/mensajes/{mensajeId}/info', [SoporteTiChatController::class, 'infoMensaje']);

    Route::get('/solicitudes', [SoporteTiSolicitudController::class, 'index']);
    Route::post('/solicitudes', [SoporteTiSolicitudController::class, 'store']);

    Route::get('/solicitudes/{id}/estados/historial', [SoporteTiSolicitudController::class, 'historialEstados']);
    Route::patch('/solicitudes/{id}/complejidad', [SoporteTiSolicitudController::class, 'actualizarComplejidad']);
    Route::patch('/solicitudes/{id}/prioridad', [SoporteTiSolicitudController::class, 'actualizarPrioridad']);
    Route::patch('/solicitudes/{id}/estado', [SoporteTiSolicitudController::class, 'actualizarEstado']);
    Route::post('/solicitudes/{id}/estado', [SoporteTiSolicitudController::class, 'cambiarEstado']);
    Route::post('/solicitudes/{id}/mensajes', [SoporteTiSolicitudController::class, 'postMensaje']);
    Route::post('/solicitudes/{id}/maqueta', [SoporteTiSolicitudController::class, 'postMaqueta']);

    Route::get('/solicitudes/{id}', [SoporteTiSolicitudController::class, 'show']);
    Route::put('/solicitudes/{id}', [SoporteTiSolicitudController::class, 'update']);
});
