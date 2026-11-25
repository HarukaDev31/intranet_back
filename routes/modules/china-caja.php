<?php

use App\Http\Controllers\ChinaCaja\ChinaCashController;
use App\Http\Controllers\ChinaCaja\ChinaContainerController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'china-caja', 'middleware' => 'jwt.auth'], function () {
    Route::get('pendientes', [ChinaContainerController::class, 'pending']);

    Route::get('contenedores', [ChinaContainerController::class, 'index']);
    Route::post('contenedores/{id}', [ChinaContainerController::class, 'update'])->whereNumber('id');
    Route::post('fletes', [ChinaContainerController::class, 'payFreight']);
    Route::delete('fletes/{id}', [ChinaContainerController::class, 'cancelFreight'])->whereNumber('id');

    Route::get('cierres/{year}', [ChinaCashController::class, 'history'])->where('year', '20[0-9]{2}');
    Route::get('meses/{month}', [ChinaCashController::class, 'show'])->where('month', '20[0-9]{2}-(0[1-9]|1[0-2])');
    Route::put('meses/{month}', [ChinaCashController::class, 'settings'])->where('month', '20[0-9]{2}-(0[1-9]|1[0-2])');
    Route::post('meses/{month}/semanas', [ChinaCashController::class, 'closeWeek'])->where('month', '20[0-9]{2}-(0[1-9]|1[0-2])');
    Route::post('meses/{month}/cerrar', [ChinaCashController::class, 'closeMonth'])->where('month', '20[0-9]{2}-(0[1-9]|1[0-2])');

    Route::post('costos-fijos', [ChinaCashController::class, 'storeFixed']);
    Route::put('costos-fijos/{id}', [ChinaCashController::class, 'updateFixed'])->whereNumber('id');
    Route::delete('costos-fijos/{id}', [ChinaCashController::class, 'destroyFixed'])->whereNumber('id');
    Route::post('inversiones', [ChinaCashController::class, 'storeInvestment']);
    Route::post('inversiones/{id}/pagar', [ChinaCashController::class, 'payInvestment'])->whereNumber('id');
    Route::delete('inversiones/{id}', [ChinaCashController::class, 'destroyInvestment'])->whereNumber('id');
});
