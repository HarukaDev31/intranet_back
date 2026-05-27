<?php

use App\Http\Controllers\Copiloto\CopilotoController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'copiloto', 'middleware' => 'jwt.auth'], function () {
    Route::get('/leads', [CopilotoController::class, 'leads']);
    Route::get('/conversacion/{phone}', [CopilotoController::class, 'conversacion']);
    Route::get('/ficha/{phone}', [CopilotoController::class, 'ficha']);
    Route::post('/responder', [CopilotoController::class, 'responder']);
    Route::get('/sync/estado', [CopilotoController::class, 'syncEstado']);
});

