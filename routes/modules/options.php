<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Commons\PaisController;

/*
|--------------------------------------------------------------------------
| Rutas de Opciones Generales
|--------------------------------------------------------------------------
|
| Rutas para opciones y configuraciones generales
|
*/

Route::group(['prefix' => 'options', 'middleware' => 'jwt.auth'], function () {
    Route::get('paises', [PaisController::class, 'getPaisDropdown']);
});
