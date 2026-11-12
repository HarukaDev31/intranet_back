<?php

use App\Http\Controllers\Verificacion\VerificacionContadoresController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'verificacion', 'middleware' => 'jwt.auth'], function () {
    Route::get('contadores', [VerificacionContadoresController::class, 'index']);
});
