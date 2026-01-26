<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Calendar\CalendarController;

/*
|--------------------------------------------------------------------------
| Rutas de Calendario
|--------------------------------------------------------------------------
|
| Rutas para gestión de eventos del calendario
|
*/

Route::group(['prefix' => 'calendar', 'middleware' => 'jwt.auth'], function () {
    Route::get('/events', [CalendarController::class, 'getEvents']);
    Route::get('/events/{id}', [CalendarController::class, 'getEvent']);
    Route::post('/events', [CalendarController::class, 'createEvent']);
    Route::put('/events/{id}', [CalendarController::class, 'updateEvent']);
    Route::delete('/events/{id}', [CalendarController::class, 'deleteEvent']);
    Route::put('/events/{id}/move', [CalendarController::class, 'moveEvent']);
    Route::put('/task-days/{taskDayId}', [CalendarController::class, 'updateTaskDay']);
    Route::delete('/task-days/{taskDayId}', [CalendarController::class, 'deleteTaskDay']);
});

