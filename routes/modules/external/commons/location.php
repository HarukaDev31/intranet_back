<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Clientes\Commons\LocationController;

// Ubicación portal clientes — requieren X-Org-Key
Route::group(['prefix' => 'clientes/ubicacion', 'middleware' => 'org.key'], function () {
    Route::get('/paises', [LocationController::class, 'getPaises']);
    //get department 
    Route::get('/departamentos', [LocationController::class, 'getDepartamentos']);
    //get provincias
    Route::get('/provincias/{idDepartamento}', [LocationController::class, 'getProvincias']);
    // Búsqueda paginada de distritos (autocomplete Nuxt UI) — debe ir antes de /distritos/{idProvincia}
    Route::get('/distritos/search', [LocationController::class, 'searchDistritos']);
    // get distritos por provincia (solo id numérico; evita que "search" caiga aquí)
    Route::get('/distritos/{idProvincia}', [LocationController::class, 'getDistritos'])
        ->where('idProvincia', '[0-9]+');
    //get all provincias
    Route::get('/provincias', [LocationController::class, 'getAllProvincias']);
    //get all distritos
    Route::get('/distritos', [LocationController::class, 'getAllDistritos']);
});
