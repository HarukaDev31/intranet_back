<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Broadcasting\BroadcastController;
use App\Http\Controllers\UserBusinessController;
use App\Http\Controllers\UserProfileController;

/*
|--------------------------------------------------------------------------
| Rutas de Autenticación
|--------------------------------------------------------------------------
|
| Rutas para autenticación de usuarios internos y externos
|
*/

// Autenticación - Usuarios internos
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    
    // Rutas protegidas con JWT para usuarios internos
    Route::group(['middleware' => 'jwt.auth'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Autenticación - Usuarios externos (clientes)
Route::group(['prefix' => 'auth/clientes'], function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'loginCliente'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:10,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    
    // Rutas protegidas para usuarios externos
    Route::group(['middleware' => 'jwt.external'], function () {
        Route::get('me', [AuthController::class, 'meExternal']);
        Route::post('logout', [AuthController::class, 'logoutExternal']);
        Route::post('refresh', [AuthController::class, 'refreshExternal']);
        
        // Rutas de perfil de usuario
        Route::get('profile', [UserProfileController::class, 'show']);
        Route::post('profile', [UserProfileController::class, 'update']);
        
        // Rutas de empresa
        Route::get('business', [UserBusinessController::class, 'show']);
        Route::post('business', [UserBusinessController::class, 'storeOrUpdate']);
        Route::put('business', [UserBusinessController::class, 'storeOrUpdate']);
    });
});

// Broadcasting (usuarios internos)
Route::group(['middleware' => 'jwt.auth'], function () {
    Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate']);
});
