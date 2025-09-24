<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;

/*
|--------------------------------------------------------------------------
| Rutas de Menús
|--------------------------------------------------------------------------
|
| Rutas para gestión de menús de usuarios internos y externos
|
*/

// Menús - Usuarios internos
Route::group(['prefix' => 'menu', 'middleware' => 'jwt.auth'], function () {
    Route::get('listar', [MenuController::class, 'listarMenu']);
    Route::get('get', [MenuController::class, 'getMenus']);
});

// Menús - Usuarios externos
Route::group(['prefix' => 'menu-external', 'middleware' => 'jwt.external'], function () {
    Route::get('get', [MenuController::class, 'getMenusExternal']);
});
