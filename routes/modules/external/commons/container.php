<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CargaConsolidada\CotizacionProveedorController;

// Rutas protegidas para usuarios externos de importaciones
Route::group(['prefix' => 'contenedor/external' ], function () {
    Route::get('cotizacion-proveedor/{uuid}', [CotizacionProveedorController::class, 'getContenedorCotizacionProveedoresByUuid']);
    Route::put('cotizacion-proveedor/{uuid}', [CotizacionProveedorController::class, 'updateContenedorCotizacionProveedoresByUuid']);
});
