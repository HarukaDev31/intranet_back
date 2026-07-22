<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CargaConsolidada\CotizacionProveedorController;
use App\Http\Controllers\Clientes\ImportacionesController;
use App\Http\Controllers\PublicSite\ExcelConfirmacionController;

// Rutas externas (algunas sin JWT para acceso por enlace)
Route::group(['prefix' => 'contenedor/external' ], function () {
    Route::get('inspeccion/{uuid}', [ImportacionesController::class, 'getInspeccionByUuidPublic']);
    Route::get('cotizacion-proveedor/{uuid}', [CotizacionProveedorController::class, 'getContenedorCotizacionProveedoresByUuid']);
    Route::put('cotizacion-proveedor/{uuid}', [CotizacionProveedorController::class, 'updateContenedorCotizacionProveedoresByUuid']);
    Route::post('cotizacion/sign-service-contract/{uuid}', [CotizacionProveedorController::class, 'signServiceContract']);
    Route::get('cotizacion/get-service-contract/{uuid}', [CotizacionProveedorController::class, 'getUnsignedServiceContract']);
    Route::get('cotizacion/service-contract-pdf/{uuid}', [CotizacionProveedorController::class, 'streamServiceContractPdf']);

    Route::get('excel-confirmacion/labels', [ExcelConfirmacionController::class, 'labels']);
    Route::get('excel-confirmacion/{uuid}', [ExcelConfirmacionController::class, 'show']);
    Route::match(['put', 'post'], 'excel-confirmacion/{uuid}', [ExcelConfirmacionController::class, 'update']);
});
