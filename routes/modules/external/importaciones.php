<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\ImportacionesController;

// Rutas protegidas para usuarios externos de importaciones
Route::group(['prefix' => 'clientes/importaciones', 'middleware' => 'jwt.external'], function () {
    Route::group(['prefix' => 'trayecto'], function () {
        Route::get('/', [ImportacionesController::class, 'getTrayectos']);
    });
});