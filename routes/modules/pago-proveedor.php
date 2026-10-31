<?php

use App\Http\Controllers\PagoProveedor\PagoProveedorCotizacionController;
use App\Http\Controllers\PagoProveedor\PagoProveedorEtapa1Controller;
use App\Http\Controllers\PagoProveedor\PagoProveedorSolicitudController;
use App\Http\Controllers\PagoProveedor\PagoProveedorAprobacionController;
use App\Http\Controllers\PagoProveedor\PagoProveedorEjecucionController;
use App\Http\Controllers\PagoProveedor\PagoProveedorReembolsoController;
use App\Http\Controllers\PagoProveedor\PagoProveedorYuanController;
use App\Http\Controllers\PagoProveedor\PagoProveedorVerificacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('pago-proveedor/publico')->group(function () {
    Route::get('formulario-tt/{token}', [PagoProveedorEtapa1Controller::class, 'verFormulario']);
    Route::post('formulario-tt/{token}', [PagoProveedorEtapa1Controller::class, 'guardarFormulario']);
});

Route::group(['prefix' => 'pago-proveedor', 'middleware' => 'jwt.auth'], function () {
    Route::get('configuracion', [PagoProveedorCotizacionController::class, 'configuracion']);
    Route::put('configuracion/vigencia', [PagoProveedorCotizacionController::class, 'actualizarVigencia']);

    Route::get('cotizaciones-cc/{codigo}', [PagoProveedorCotizacionController::class, 'buscarConsolidada']);
    Route::get('cotizaciones', [PagoProveedorCotizacionController::class, 'index']);
    Route::post('cotizaciones', [PagoProveedorCotizacionController::class, 'store']);
    Route::get('cotizaciones/{id}', [PagoProveedorCotizacionController::class, 'show']);
    Route::put('cotizaciones/{id}', [PagoProveedorCotizacionController::class, 'update']);
    Route::delete('cotizaciones/{id}', [PagoProveedorCotizacionController::class, 'destroy']);
    Route::post('cotizaciones/{id}/renovar', [PagoProveedorCotizacionController::class, 'renovar']);
    Route::post('cotizaciones/{id}/proveedores/{proveedorId}/proforma', [PagoProveedorCotizacionController::class, 'proforma']);
    Route::post('cotizaciones/{id}/vista-previa-whatsapp', [PagoProveedorCotizacionController::class, 'whatsapp']);
    Route::get('cotizaciones/{id}/pdf', [PagoProveedorCotizacionController::class, 'pdf']);
    Route::post('cotizaciones/{id}/solicitud', [PagoProveedorSolicitudController::class, 'storeDesdeCotizacion']);

    Route::get('solicitudes', [PagoProveedorSolicitudController::class, 'index']);
    Route::get('solicitudes/{id}', [PagoProveedorSolicitudController::class, 'show'])->whereNumber('id');
    Route::post('solicitudes/{id}/pedir-datos', [PagoProveedorEtapa1Controller::class, 'pedirDatos'])->whereNumber('id');
    Route::post('solicitudes/{id}/cuentas', [PagoProveedorEtapa1Controller::class, 'cuentas'])->whereNumber('id');
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/datos-rmb', [PagoProveedorEtapa1Controller::class, 'guardarRmb'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/enviar-formulario', [PagoProveedorEtapa1Controller::class, 'enviarFormulario'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/llenar-tt', [PagoProveedorEtapa1Controller::class, 'llenarPorVentas'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/solicitar-validacion', [PagoProveedorEtapa1Controller::class, 'solicitarValidacion'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/anular', [PagoProveedorEtapa1Controller::class, 'anularProveedor'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/cuota-2', [PagoProveedorEtapa1Controller::class, 'decidirCuota'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/tipo-cambio', [PagoProveedorEtapa1Controller::class, 'confirmarTipoCambio'])->whereNumber('id');
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/pago-2', [PagoProveedorEtapa1Controller::class, 'decidirPago'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/diferencia-cambio', [PagoProveedorEtapa1Controller::class, 'registrarDiferencia'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/levantar-china', [PagoProveedorEtapa1Controller::class, 'levantarChina'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/reembolso', [PagoProveedorEtapa1Controller::class, 'solicitarReembolso'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/abonos/{abonoId}/continuan', [PagoProveedorEtapa1Controller::class, 'elegirContinuacion'])->whereNumber(['id', 'abonoId']);

    Route::get('reembolsos', [PagoProveedorReembolsoController::class, 'index']);
    Route::post('reembolsos/{id}/aprobar', [PagoProveedorReembolsoController::class, 'approve'])->whereNumber('id');
    Route::post('reembolsos/{id}/rechazar', [PagoProveedorReembolsoController::class, 'reject'])->whereNumber('id');
    Route::post('yuanes/manual', [PagoProveedorYuanController::class, 'storeManual']);
    Route::post('solicitudes/{id}/abonos', [PagoProveedorEtapa1Controller::class, 'registrarAbono'])->whereNumber('id');
    Route::post('solicitudes/{id}/abonos/{abonoId}', [PagoProveedorEtapa1Controller::class, 'actualizarAbono'])->whereNumber(['id', 'abonoId']);
    Route::post('solicitudes/{id}/abonos/{abonoId}/anular', [PagoProveedorEtapa1Controller::class, 'anularAbono'])->whereNumber(['id', 'abonoId']);
    Route::post('solicitudes/{id}/abonos/{abonoId}/excedente', [PagoProveedorEtapa1Controller::class, 'decidirExcedente'])->whereNumber(['id', 'abonoId']);

    Route::get('verificacion/depositos', [PagoProveedorVerificacionController::class, 'index']);
    Route::get('verificacion/depositos/{id}', [PagoProveedorVerificacionController::class, 'show'])->whereNumber('id');
    Route::put('verificacion/depositos/{id}', [PagoProveedorVerificacionController::class, 'update'])->whereNumber('id');
    Route::post('verificacion/depositos/{id}/validar', [PagoProveedorVerificacionController::class, 'validateDeposit'])->whereNumber('id');
    Route::post('verificacion/depositos/{id}/observar', [PagoProveedorVerificacionController::class, 'observe'])->whereNumber('id');
    Route::post('verificacion/depositos/{id}/deshacer', [PagoProveedorVerificacionController::class, 'undo'])->whereNumber('id');

    Route::get('aprobaciones', [PagoProveedorAprobacionController::class, 'index']);
    Route::put('aprobaciones/plazos', [PagoProveedorAprobacionController::class, 'updateSettings']);
    Route::get('aprobaciones/tarifas', [PagoProveedorAprobacionController::class, 'tariffs']);
    Route::put('aprobaciones/tarifas', [PagoProveedorAprobacionController::class, 'updateTariffs']);
    Route::get('aprobaciones/{id}', [PagoProveedorAprobacionController::class, 'show'])->whereNumber('id');
    Route::post('aprobaciones/{id}/aprobar', [PagoProveedorAprobacionController::class, 'approve'])->whereNumber('id');
    Route::post('aprobaciones/{id}/cobrar', [PagoProveedorAprobacionController::class, 'collect'])->whereNumber('id');
    Route::post('aprobaciones/{id}/extender', [PagoProveedorAprobacionController::class, 'extend'])->whereNumber('id');
    Route::post('aprobaciones/{id}/anular', [PagoProveedorAprobacionController::class, 'cancel'])->whereNumber('id');

    Route::get('ejecuciones', [PagoProveedorEjecucionController::class, 'index']);
    Route::post('ejecuciones/{providerId}', [PagoProveedorEjecucionController::class, 'store'])->whereNumber('providerId');

    Route::get('yuanes', [PagoProveedorYuanController::class, 'index']);
    Route::post('yuanes/{providerId}/observar', [PagoProveedorYuanController::class, 'observe'])->whereNumber('providerId');
    Route::post('yuanes/{providerId}', [PagoProveedorYuanController::class, 'store'])->whereNumber('providerId');
});
