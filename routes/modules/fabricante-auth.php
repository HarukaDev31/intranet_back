<?php

use App\Http\Controllers\Fabricante\FabricanteAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/fabricante')->group(function () {
    Route::post('register', [FabricanteAuthController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('login', [FabricanteAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('login/firebase', [FabricanteAuthController::class, 'loginFirebase'])
        ->middleware('throttle:10,1');
    Route::post('verify-email', [FabricanteAuthController::class, 'verifyEmail'])
        ->middleware('throttle:10,1');
    Route::post('resend-verification', [FabricanteAuthController::class, 'resendVerification'])
        ->middleware('throttle:5,1');

    Route::middleware('fabricante.token')->group(function () {
        Route::get('me', [FabricanteAuthController::class, 'me']);
        Route::post('logout', [FabricanteAuthController::class, 'logout']);
        Route::post('logout-all', [FabricanteAuthController::class, 'logoutAll']);
        Route::get('sessions', [FabricanteAuthController::class, 'sessions']);
        Route::delete('sessions/{sessionId}', [FabricanteAuthController::class, 'revokeSession']);
        Route::put('fcm-token', [FabricanteAuthController::class, 'updateFcmToken']);
        Route::post('profile', [FabricanteAuthController::class, 'updateProfile']);
    });
});
