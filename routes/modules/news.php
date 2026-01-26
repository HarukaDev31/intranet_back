<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewsController;

/*
|--------------------------------------------------------------------------
| Rutas de Noticias del Sistema
|--------------------------------------------------------------------------
|
| Rutas para gestión de noticias y actualizaciones del sistema
|
*/

// Rutas públicas (solo noticias publicadas)
Route::group(['prefix' => 'news'], function () {
    Route::get('/', [NewsController::class, 'index']);
    Route::get('/{id}', [NewsController::class, 'show']);
});

// Rutas de administración (requieren autenticación y permisos de admin)
Route::group(['prefix' => 'admin/news', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [NewsController::class, 'adminIndex']);
    Route::post('/', [NewsController::class, 'store']);
    Route::put('/{id}', [NewsController::class, 'update']);
    Route::delete('/{id}', [NewsController::class, 'destroy']);
});

