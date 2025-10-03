<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\Commons\LocationController;

// Rutas protegidas para usuarios externos de importaciones
Route::group(['prefix' => 'clientes/ubicacion' ], function () {
    //get department 
    Route::get('/departamentos', [LocationController::class, 'getDepartamentos']);
    //get provincias
    Route::get('/provincias/{idDepartamento}', [LocationController::class, 'getProvincias']);
    //get distritos
    Route::get('/distritos/{idProvincia}', [LocationController::class, 'getDistritos']);
    //get all provincias
    Route::get('/provincias', [LocationController::class, 'getAllProvincias']);
});
