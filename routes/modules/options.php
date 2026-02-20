<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Commons\PaisController;
use App\Http\Controllers\Commons\EmpresaOrgController;

/*
|--------------------------------------------------------------------------
| Rutas de Opciones Generales
|--------------------------------------------------------------------------
|
| Rutas para opciones y configuraciones generales
|
*/

Route::group(['prefix' => 'options'], function () {
    Route::get('paises', [PaisController::class, 'getPaisDropdown']);
    // Helpers para selects de empresa, organización y grupo (requieren auth)
    Route::middleware('jwt.auth')->group(function () {
        Route::get('empresas', [EmpresaOrgController::class, 'getEmpresas']);
        Route::get('organizaciones', [EmpresaOrgController::class, 'getOrganizaciones']);
        Route::get('grupos', [EmpresaOrgController::class, 'getGrupos']);
    });
});
