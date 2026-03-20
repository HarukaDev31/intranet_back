<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\Commons\LocationController;

/*
| Ubigeo / países para formularios web públicos (sin JWT).
| Misma lógica que clientes/ubicacion pero expuesta bajo /api/public/ubicacion/*
*/
Route::prefix('public/ubicacion')->group(function () {
    Route::get('/paises', [LocationController::class, 'getPaises']);
    Route::get('/departamentos', [LocationController::class, 'getDepartamentos']);
    Route::get('/provincias/{idDepartamento}', [LocationController::class, 'getProvincias']);
    Route::get('/distritos/{idProvincia}', [LocationController::class, 'getDistritos']);
});
