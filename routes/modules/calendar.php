<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\Calendar\CalendarActivityController;

/*
|--------------------------------------------------------------------------
| Rutas de Calendario - Especificación API
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'calendar', 'middleware' => 'jwt.auth'], function () {
    // 1. Eventos / Actividades
    Route::get('/events', [CalendarController::class, 'getEvents']);
    Route::get('/events/{id}', [CalendarController::class, 'getEvent']);
    Route::post('/events', [CalendarController::class, 'createEvent']);
    Route::put('/events/{id}', [CalendarController::class, 'updateEvent']);
    Route::put('/events/{id}/status', [CalendarController::class, 'updateEventStatus']);
    Route::delete('/events/{id}', [CalendarController::class, 'deleteEvent']);
    Route::put('/events/{id}/move', [CalendarController::class, 'moveEvent']);

    // Catálogo de tipos de actividad (dropdown)
    Route::get('/activities', [CalendarActivityController::class, 'index']);
    Route::post('/activities', [CalendarActivityController::class, 'storeActivity']);
    Route::put('/activities/{id}', [CalendarActivityController::class, 'updateActivity']);
    Route::delete('/activities/{id}', [CalendarActivityController::class, 'destroyActivity']);
    Route::put('/activities/{id}/priority', [CalendarActivityController::class, 'updateEventPriority']);
    Route::put('/activities/{id}/notes', [CalendarActivityController::class, 'updateEventNotes']);

    // Catálogo de actividades (GET, POST, PUT, DELETE)
    Route::get('/activity-catalog', [CalendarActivityController::class, 'index']);
    Route::post('/activity-catalog', [CalendarActivityController::class, 'store']);
    Route::put('/activity-catalog/{id}', [CalendarActivityController::class, 'updateCatalog']);
    Route::delete('/activity-catalog/{id}', [CalendarActivityController::class, 'destroy']);
    Route::post('/activity-catalog/reorder', [CalendarActivityController::class, 'reorderCatalog']);

    // 2. Estado y prioridad - Charges
    Route::get('/charges/{chargeId}/tracking', [CalendarActivityController::class, 'getChargeTracking']);
    Route::put('/charges/{chargeId}/status', [CalendarActivityController::class, 'updateChargeStatus']);
    Route::put('/charges/{chargeId}/notes', [CalendarActivityController::class, 'updateChargeNotes']);

    // Tracking / Historial por actividad
    Route::get('/activities/{activityId}/tracking', [CalendarActivityController::class, 'getActivityTracking']);

    // 3. Responsables
    Route::get('/responsables', [CalendarActivityController::class, 'getResponsables']);
    Route::get('/users/responsible', [CalendarActivityController::class, 'getResponsibleUsers']);
    Route::post('/activities/{id}/responsables', [CalendarActivityController::class, 'addResponsable']);
    Route::delete('/activities/{id}/responsables/{userId}', [CalendarActivityController::class, 'removeResponsable']);

    // 4. Colores
    Route::get('/colors', [CalendarActivityController::class, 'getColors']);
    Route::put('/colors', [CalendarActivityController::class, 'updateColor']);

    // Colores por consolidado
    Route::get('/consolidado-colors', [CalendarActivityController::class, 'getConsolidadoColors']);
    Route::put('/consolidado-colors', [CalendarActivityController::class, 'updateConsolidadoColor']);

    // 5. Contenedores
    Route::get('/contenedores', [CalendarActivityController::class, 'getContenedores']);
    Route::get('/consolidados-dropdown', [CalendarActivityController::class, 'getConsolidadosDropdown']);

    // 6. Progreso
    Route::get('/progress', [CalendarActivityController::class, 'getProgress']);

    // Formulario "Nueva actividad"
    Route::get('/my-calendar', [CalendarActivityController::class, 'getOrCreateMyCalendar']);
    Route::post('/activity-events', [CalendarActivityController::class, 'storeActivityEvent']);
});
