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

    Route::get('cotizaciones-cc', [PagoProveedorCotizacionController::class, 'sugerirConsolidada']);
    Route::get('cotizaciones-cc/{codigo}', [PagoProveedorCotizacionController::class, 'buscarConsolidada']);
    Route::get('cotizaciones', [PagoProveedorCotizacionController::class, 'index']);
    Route::post('cotizaciones', [PagoProveedorCotizacionController::class, 'store']);
    Route::post('cotizaciones/resumen/pdf', [PagoProveedorCotizacionController::class, 'pdfResumen']);
    Route::post('cotizaciones/resumen/vista-previa-whatsapp', [PagoProveedorCotizacionController::class, 'vistaPreviaResumen']);
    Route::post('cotizaciones/resumen/enviar-whatsapp', [PagoProveedorCotizacionController::class, 'enviarResumen']);
    Route::get('cotizaciones/{id}', [PagoProveedorCotizacionController::class, 'show']);
    Route::put('cotizaciones/{id}', [PagoProveedorCotizacionController::class, 'update']);
    Route::delete('cotizaciones/{id}', [PagoProveedorCotizacionController::class, 'destroy']);
    Route::post('cotizaciones/{id}/renovar', [PagoProveedorCotizacionController::class, 'renovar']);
    Route::post('cotizaciones/{id}/vista-previa-whatsapp', [PagoProveedorCotizacionController::class, 'whatsapp']);
    Route::post('cotizaciones/{id}/enviar-whatsapp', [PagoProveedorCotizacionController::class, 'enviarWhatsapp']);
    Route::get('cotizaciones/{id}/pdf', [PagoProveedorCotizacionController::class, 'pdf']);
    Route::post('cotizaciones/{id}/solicitud', [PagoProveedorSolicitudController::class, 'storeDesdeCotizacion']);

    Route::get('solicitudes', [PagoProveedorSolicitudController::class, 'index']);
    Route::get('solicitudes/{id}', [PagoProveedorSolicitudController::class, 'show'])->whereNumber('id');
    Route::get('solicitudes/{id}/pedir-datos', [PagoProveedorEtapa1Controller::class, 'candidatosPedirDatos'])->whereNumber('id');
    Route::post('solicitudes/{id}/pedir-datos', [PagoProveedorEtapa1Controller::class, 'pedirDatos'])->whereNumber('id');
    Route::post('solicitudes/{id}/cuentas', [PagoProveedorEtapa1Controller::class, 'cuentas'])->whereNumber('id');
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/datos-rmb', [PagoProveedorEtapa1Controller::class, 'guardarRmb'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/enviar-formulario', [PagoProveedorEtapa1Controller::class, 'enviarFormulario'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/llenar-tt', [PagoProveedorEtapa1Controller::class, 'llenarPorVentas'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/solicitar-validacion', [PagoProveedorEtapa1Controller::class, 'solicitarValidacion'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/anular', [PagoProveedorEtapa1Controller::class, 'anularProveedor'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/observar', [PagoProveedorEtapa1Controller::class, 'observarProveedor'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/levantar-observacion', [PagoProveedorEtapa1Controller::class, 'levantarObservacion'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/cuota-2', [PagoProveedorEtapa1Controller::class, 'decidirCuota'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/tipo-cambio', [PagoProveedorEtapa1Controller::class, 'confirmarTipoCambio'])->whereNumber('id');
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/pago-2', [PagoProveedorEtapa1Controller::class, 'decidirPago'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/diferencia-cambio', [PagoProveedorEtapa1Controller::class, 'registrarDiferencia'])->whereNumber(['id', 'proveedorId']);
    Route::get('solicitudes/{id}/proveedores/{proveedorId}/balance/vista-previa', [PagoProveedorEtapa1Controller::class, 'vistaPreviaBalance'])->whereNumber(['id', 'proveedorId']);
    Route::get('solicitudes/{id}/proveedores/{proveedorId}/balance', [PagoProveedorEtapa1Controller::class, 'descargarBalance'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/balance', [PagoProveedorEtapa1Controller::class, 'enviarBalance'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/levantar-china', [PagoProveedorEtapa1Controller::class, 'levantarChina'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/reembolso', [PagoProveedorEtapa1Controller::class, 'solicitarReembolso'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/proveedores/{proveedorId}/completar', [PagoProveedorEtapa1Controller::class, 'completarProveedor'])->whereNumber(['id', 'proveedorId']);
    Route::post('solicitudes/{id}/abonos/{abonoId}/continuan', [PagoProveedorEtapa1Controller::class, 'elegirContinuacion'])->whereNumber(['id', 'abonoId']);

    Route::get('reembolsos', [PagoProveedorReembolsoController::class, 'index']);
    Route::post('reembolsos/{id}/aprobar', [PagoProveedorReembolsoController::class, 'approve'])->whereNumber('id');
    Route::post('reembolsos/{id}/rechazar', [PagoProveedorReembolsoController::class, 'reject'])->whereNumber('id');
    Route::post('yuanes/manual', [PagoProveedorYuanController::class, 'storeManual']);
    Route::get('yuanes/socios/paises', [PagoProveedorYuanController::class, 'countries']);
    Route::post('yuanes/socios', [PagoProveedorYuanController::class, 'storeOrganization']);
    Route::post('yuanes/solicitudes/{requestId}/editar', [PagoProveedorYuanController::class, 'updateManual'])->whereNumber('requestId');
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
    Route::post('verificacion/depositos/{id}/extender', [PagoProveedorVerificacionController::class, 'extend'])->whereNumber('id');
    Route::post('verificacion/depositos/{id}/anular-pago', [PagoProveedorVerificacionController::class, 'cancelPayment'])->whereNumber('id');

    Route::get('aprobaciones', [PagoProveedorAprobacionController::class, 'index']);
    Route::put('aprobaciones/plazos', [PagoProveedorAprobacionController::class, 'updateSettings']);
    Route::get('aprobaciones/tarifas', [PagoProveedorAprobacionController::class, 'tariffs']);
    Route::put('aprobaciones/tarifas', [PagoProveedorAprobacionController::class, 'updateTariffs']);
    Route::get('aprobaciones/{id}', [PagoProveedorAprobacionController::class, 'show'])->whereNumber('id');
    Route::post('aprobaciones/{id}/aprobar', [PagoProveedorAprobacionController::class, 'approve'])->whereNumber('id');
    Route::post('aprobaciones/{id}/cobrar', [PagoProveedorAprobacionController::class, 'collect'])->whereNumber('id');
    Route::post('aprobaciones/{id}/error-registro', [PagoProveedorAprobacionController::class, 'registrationError'])->whereNumber('id');
    Route::post('aprobaciones/{id}/extender', [PagoProveedorAprobacionController::class, 'extend'])->whereNumber('id');
    Route::post('aprobaciones/{id}/anular', [PagoProveedorAprobacionController::class, 'cancel'])->whereNumber('id');

    Route::get('ejecuciones', [PagoProveedorEjecucionController::class, 'index']);
    Route::get('ejecuciones/{providerId}/aviso', [PagoProveedorEjecucionController::class, 'notice'])->whereNumber('providerId');
    Route::post('ejecuciones/{providerId}/aviso', [PagoProveedorEjecucionController::class, 'notice'])->whereNumber('providerId');
    Route::post('ejecuciones/{providerId}', [PagoProveedorEjecucionController::class, 'store'])->whereNumber('providerId');

    Route::get('yuanes', [PagoProveedorYuanController::class, 'index']);
    Route::get('yuanes/{providerId}/aviso', [PagoProveedorYuanController::class, 'notice'])->whereNumber('providerId');
    Route::post('yuanes/{providerId}/aviso', [PagoProveedorYuanController::class, 'notice'])->whereNumber('providerId');
    Route::post('yuanes/{providerId}/observar', [PagoProveedorYuanController::class, 'observe'])->whereNumber('providerId');
    Route::post('yuanes/{providerId}/levantar', [PagoProveedorYuanController::class, 'lift'])->whereNumber('providerId');
    Route::post('yuanes/{providerId}/deshacer', [PagoProveedorYuanController::class, 'undo'])->whereNumber('providerId');
    Route::post('yuanes/solicitudes/{requestId}/ingresos', [PagoProveedorYuanController::class, 'storeIncome'])->whereNumber('requestId');
    Route::delete('yuanes/ingresos/{incomeId}', [PagoProveedorYuanController::class, 'destroyIncome'])->whereNumber('incomeId');
    Route::post('yuanes/{providerId}', [PagoProveedorYuanController::class, 'store'])->whereNumber('providerId');
});
