<?php

use App\Http\Controllers\PagoProveedor\PagoProveedorCotizacionController;
use App\Http\Controllers\PagoProveedor\PagoProveedorEtapa1Controller;
use App\Http\Controllers\PagoProveedor\PagoProveedorSolicitudController;
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
    Route::post('solicitudes/{id}/abonos', [PagoProveedorEtapa1Controller::class, 'registrarAbono'])->whereNumber('id');
    Route::post('solicitudes/{id}/abonos/{abonoId}', [PagoProveedorEtapa1Controller::class, 'actualizarAbono'])->whereNumber(['id', 'abonoId']);
    Route::post('solicitudes/{id}/abonos/{abonoId}/anular', [PagoProveedorEtapa1Controller::class, 'anularAbono'])->whereNumber(['id', 'abonoId']);
    Route::post('solicitudes/{id}/abonos/{abonoId}/excedente', [PagoProveedorEtapa1Controller::class, 'decidirExcedente'])->whereNumber(['id', 'abonoId']);
});
