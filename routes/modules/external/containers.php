<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\ContainersController;

// Rutas protegidas para usuarios externos - contenedores
Route::group(['prefix' => 'clientes', 'middleware' => 'jwt.external'], function () {
    Route::get('/containers', [ContainersController::class, 'index']);
});

