<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CargaConsolidada\CotizacionProveedorController;
use App\Http\Controllers\Clientes\ImportacionesController;

// Rutas externas (algunas sin JWT para acceso por enlace)
Route::group(['prefix' => 'contenedor/external' ], function () {
    Route::get('inspeccion/{uuid}', [ImportacionesController::class, 'getInspeccionByUuidPublic']);
    Route::get('cotizacion-proveedor/{uuid}', [CotizacionProveedorController::class, 'getContenedorCotizacionProveedoresByUuid']);
    Route::put('cotizacion-proveedor/{uuid}', [CotizacionProveedorController::class, 'updateContenedorCotizacionProveedoresByUuid']);
    Route::post('cotizacion/sign-service-contract/{uuid}', [CotizacionProveedorController::class, 'signServiceContract']);
    Route::get('cotizacion/get-service-contract/{uuid}', [CotizacionProveedorController::class, 'getUnsignedServiceContract']);
});
