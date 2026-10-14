<?php

use App\Http\Controllers\Api\Integration\CotizacionResumenIntegracionController;
use App\Http\Controllers\Api\Integration\ProveedorCodigoIntegracionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de integración para otras organizaciones
|--------------------------------------------------------------------------
| Base: /api/integration/v1
| Autenticación: Authorization: Bearer pbk_... (ver AuthenticateApiClient).
| Cada ruta exige un scope; cada token pertenece a UNA organización y solo ve/modifica
| lo suyo. El middleware aplica límite por minuto, cuota diaria y registra la auditoría.
| Sin sesión web/JWT: no mezclar con los grupos jwt.auth.
*/
Route::prefix('integration/v1')->group(function () {
    Route::get('cotizaciones-resumen', [CotizacionResumenIntegracionController::class, 'listar'])
        ->middleware('api.client:cotizaciones-resumen:read')
        ->name('integration.cotizaciones-resumen.listar');

    Route::post('cotizaciones-resumen', [CotizacionResumenIntegracionController::class, 'crear'])
        ->middleware('api.client:cotizaciones-resumen:create')
        ->name('integration.cotizaciones-resumen.crear');

    Route::put('proveedores/{id}/code-supplier', [ProveedorCodigoIntegracionController::class, 'actualizar'])
        ->whereNumber('id')
        ->middleware('api.client:proveedores:update-code')
        ->name('integration.proveedores.code-supplier');
});
