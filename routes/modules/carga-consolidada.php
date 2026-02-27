<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Http\Controllers\CargaConsolidada\TipoClienteController;
use App\Http\Controllers\CargaConsolidada\CotizacionController;
use App\Http\Controllers\CargaConsolidada\PagosController;
use App\Http\Controllers\CargaConsolidada\ImportController;
use App\Http\Controllers\CargaConsolidada\CotizacionProveedorController;
use App\Http\Controllers\CargaConsolidada\Clientes\GeneralController;
use App\Http\Controllers\CargaConsolidada\Clientes\EmbarcadosController;
use App\Http\Controllers\CargaConsolidada\Clientes\VariacionController;
use App\Http\Controllers\CargaConsolidada\Documentacion\DocumentacionController;
use App\Http\Controllers\CargaConsolidada\CotizacionCotizadorDocumentacionController;
use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use App\Http\Controllers\CargaConsolidada\FacturaGuiaController;
use App\Http\Controllers\CargaConsolidada\CotizacionPagosController;
use App\Http\Controllers\CargaConsolidada\AduanaController;
use App\Http\Controllers\CargaConsolidada\Clientes\PagosController as ClientesPagosController;
use App\Http\Controllers\CargaConsolidada\EntregaController;
use App\Http\Controllers\CargaConsolidada\InspeccionadosController;
use App\Http\Controllers\Clientes\ComprobanteFormController;
use App\Http\Controllers\Commons\Google\SheetController;

/*
|--------------------------------------------------------------------------
| Rutas de Carga Consolidada
|--------------------------------------------------------------------------
|
| Rutas para gestión de contenedores, cotizaciones, documentación y pagos
|
*/

Route::group(['prefix' => 'carga-consolidada', 'middleware' => 'jwt.auth'], function () {
    
    // Commons
    Route::prefix('commons')->group(function () {
        Route::post('/force-send-inspection', [CotizacionProveedorController::class, 'forceSendInspection']);
        Route::post('/force-send-rotulado', [CotizacionProveedorController::class, 'forceSendRotulado']);
        Route::post('/force-send-cobranza', [CotizacionProveedorController::class, 'forceSendCobrando']);
        Route::post('/force-send-move', [CotizacionProveedorController::class, 'forceSendMove']);
        Route::post('/force-send-recordatorio-datos-proveedor', [CotizacionProveedorController::class, 'forceSendRecordatorioDatosProveedor']);
    });

    // Dashboard ventas
    Route::prefix('dashboard-ventas')->group(function () {
        Route::get('/resumen', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getResumenVentas']);
        Route::get('/por-vendedor', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getVentasPorVendedor']);
        Route::get('/filtros/contenedores', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getContenedoresFiltro']);
        Route::get('/filtros/vendedores', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getVendedoresFiltro']);
        Route::get('/evolucion/{idContenedor}', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getEvolucionContenedor']);
        Route::get('/evolucion-total', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getEvolucionTotal']);
        Route::get('/cotizaciones-confirmadas-por-vendedor-por-dia', [App\Http\Controllers\CargaConsolidada\DashboardVentasController::class, 'getCotizacionesConfirmadasPorVendedorPorDia']);
    });

    // Contenedores
    Route::group(['prefix' => 'contenedor'], function () {
        Route::post('packing-list', [ContenedorController::class, 'uploadPackingList']);
        Route::get('valid-containers', [ContenedorController::class, 'getValidContainers']);
        Route::get('valid-containers-documentacion', [ContenedorController::class, 'getValidContainersDocumentacion']);
        Route::get('cargas-disponibles', [ContenedorController::class, 'getCargasDisponibles']);
        Route::get('cargas-disponibles-dropdown', [ContenedorController::class, 'getCargasDisponiblesDropdown']);
        Route::get('vendedores-dropdown', [ContenedorController::class, 'getVendedoresDropdown']);
        Route::post('move-cotizacion', [ContenedorController::class, 'moveCotizacionToConsolidado']);
        Route::post('move-cotizacion-calculadora', [ContenedorController::class, 'moveCotizacionToCalculadora']);
        Route::get('/', [ContenedorController::class, 'index']);
        Route::get('pasos/{idContenedor}', [ContenedorController::class, 'getContenedorPasos']);
        Route::post('/', [ContenedorController::class, 'store']);
        Route::post('/estado-documentacion', [ContenedorController::class, 'updateEstadoDocumentacion']);
        Route::delete('packing-list/{idContenedor}', [ContenedorController::class, 'deletePackingList']);
        Route::post('update-fecha-documentacion/{idContenedor}', [ContenedorController::class, 'updateFechaDocumentacionMax']);

        // Cotizaciones
        Route::group(['prefix' => 'cotizaciones'], function () {
            Route::get('{id}/exportar', [CotizacionController::class, 'exportarCotizacion']);
            Route::post('/{id}/refresh', [CotizacionController::class, 'refreshCotizacionFile']);
            Route::get('/{idContenedor}/headers', [CotizacionController::class, 'getHeadersData']);
            Route::get('/{idContenedor}', [CotizacionController::class, 'index']);
            Route::put('{id}', [CotizacionController::class, 'update']);
            Route::post('{id}/estado-cotizador', [CotizacionController::class, 'updateEstadoCotizacion']);
            Route::post('{id}/file', [CotizacionController::class, 'updateCotizacionFile']);
            Route::delete('{id}/file', [CotizacionController::class, 'deleteCotizacionFile']);
            Route::post('{id}/send-recordatorio-firma', [CotizacionController::class, 'sendRecordatorioFirmaContrato']);
            Route::delete('{id}', [CotizacionController::class, 'destroy']);
            Route::post('/', [CotizacionController::class, 'store']);
            Route::get('filters/options', [CotizacionController::class, 'filterOptions']);
        });

        // Documentación cotizador (cotizaciones prospectos)
        Route::prefix('cotizador-documentacion')->group(function () {
            Route::get('{idCotizacion}', [CotizacionCotizadorDocumentacionController::class, 'show']);
            Route::post('sync', [CotizacionCotizadorDocumentacionController::class, 'sync']);
            Route::post('batch-delete', [CotizacionCotizadorDocumentacionController::class, 'batchDelete']);
            Route::post('upload', [CotizacionCotizadorDocumentacionController::class, 'uploadDocumento']);
            Route::delete('documento/{id}', [CotizacionCotizadorDocumentacionController::class, 'deleteDocumento']);
            Route::post('proveedor/upload', [CotizacionCotizadorDocumentacionController::class, 'uploadProveedorDocumento']);
            Route::delete('proveedor-documento/{id}', [CotizacionCotizadorDocumentacionController::class, 'deleteProveedorDocumento']);
        });
        
        // Clientes
        Route::group(['prefix' => 'clientes'], function () {
            Route::post('/general/recordatorios-documentos', [GeneralController::class, 'recordatoriosDocumentos']);
            Route::post('/general/solicitar-documentos', [GeneralController::class, 'solicitarDocumentos']);
            Route::get('/general/{idCotizacion}/proveedores-items', [GeneralController::class, 'getProveedoresItemsCotizacion']);
            Route::get('/general/{idCotizacion}/proveedores-pending-documents', [GeneralController::class, 'getProveedorPendingDocuments']);
            Route::get('/general/{idContenedor}/export', [GeneralController::class, 'exportarClientes']);
            Route::get('/general/{idContenedor}/headers', [GeneralController::class, 'getClientesHeader']);
            Route::get('/general/{idContenedor}', [GeneralController::class, 'index']);
            Route::post('/general/estado-cliente', [GeneralController::class, 'updateEstadoCliente']);
            Route::post('/general/status-cliente-doc', [GeneralController::class, 'updateStatusCliente']);
            Route::get('/embarcados/{idContenedor}', [EmbarcadosController::class, 'getEmbarcados']);
            Route::post('embarcados/{idProveedor}/factura-comercial', [EmbarcadosController::class, 'uploadFacturaComercial']);
            Route::post('embarcados/{idProveedor}/packing-list', [EmbarcadosController::class, 'uploadPackingList']);
            Route::post('embarcados/{idProveedor}/excel-confirmacion', [EmbarcadosController::class, 'uploadExcelConfirmacion']);
            Route::delete('embarcados/{idProveedor}/factura-comercial', [EmbarcadosController::class, 'deleteFacturaComercial']);
            Route::delete('embarcados/{idProveedor}/packing-list', [EmbarcadosController::class, 'deletePackingList']);
            Route::delete('embarcados/{idProveedor}/excel-confirmacion', [EmbarcadosController::class, 'deleteExcelConfirmacion']);
            Route::get('/variacion/{idContenedor}', [VariacionController::class, 'index']);
            Route::post('/variacion/vol-selected', [VariacionController::class, 'updateVolSelected']);
            Route::post('/variacion/documentacion/proveedor/{idProveedor}/create', [DocumentacionController::class, 'createProveedorDocumentacionFolder']);
            Route::post('/variacion/documentacion/proveedor/{idProveedor}', [DocumentacionController::class, 'updateClienteDocumentacion']);
            Route::delete('/variacion/documentacion/proveedor/{idProveedor}/factura-comercial', [DocumentacionController::class, 'deleteProveedorFacturaComercial']);
            Route::delete('/variacion/documentacion/proveedor/{idProveedor}/excel-confirmacion', [DocumentacionController::class, 'deleteProveedorExcelConfirmacion']);
            Route::delete('/variacion/documentacion/proveedor/{idProveedor}/packing-list', [DocumentacionController::class, 'deleteProveedorPackingList']);
            Route::delete('/variacion/documentacion/archivo/{idFile}', [CotizacionProveedorController::class, 'deleteFileDocumentationPeru']);
            Route::get('/variacion/documentacion/{idCotizacion}', [VariacionController::class, 'showClientesDocumentacion']);
            Route::get('/pagos/{idContenedor}', [ClientesPagosController::class, 'index']);
            Route::post('/pagos', [ClientesPagosController::class, 'store']);
            Route::delete('/pagos/{id}', [ClientesPagosController::class, 'delete']);
        });
        
        // Documentación
        Route::group(['prefix' => 'documentacion'], function () {
            Route::get('/download-factura-comercial/{idContenedor}', [DocumentacionController::class, 'downloadFacturaComercial']);
            Route::delete('/delete/{idFile}', [DocumentacionController::class, 'deleteFileDocumentation']);
            Route::post('/upload-file-documentation', [DocumentacionController::class, 'uploadFileDocumentation']);
            Route::post('/create-folder', [DocumentacionController::class, 'createDocumentacionFolder']);
            Route::get('/download-zip-administracion/{idContenedor}', [DocumentacionController::class, 'downloadZipAdministracion']);
            Route::get('/{id}', [DocumentacionController::class, 'getDocumentationFolderFiles']);
            Route::get('/download-zip/{idContenedor}', [DocumentacionController::class, 'downloadDocumentacionZip']);
        });
        
        // Cotización final
        Route::group(['prefix' => 'cotizacion-final'], function () {
            Route::post('/pagos', [CotizacionFinalController::class, 'store']);
            Route::options('/general/upload-plantilla-final', [CotizacionFinalController::class, 'handleOptions']);
            Route::post('/general/upload-plantilla-final', [CotizacionFinalController::class, 'generateMassiveExcelPayrolls']);
            Route::get('/general/check-temp-directory', [CotizacionFinalController::class, 'checkTempDirectory']);
            Route::get('/general/download-cotizacion-final-pdf/{idCotizacion}', [CotizacionFinalController::class, 'downloadCotizacionFinalPdf']);
            Route::delete('/general/delete-cotizacion-final-file/{idCotizacion}', [CotizacionFinalController::class, 'deleteCotizacionFinalFile']);
            Route::put('/general/update-estado', [CotizacionFinalController::class, 'updateEstadoCotizacionFinal']);
            Route::post('/general/upload-factura-comercial', [CotizacionFinalController::class, 'uploadFacturaComercial']);
            Route::post('/general/upload-cotizacion-final/{idCotizacion}', [CotizacionFinalController::class, 'uploadCotizacionFinalFile']);
            Route::post('/general/generate-individual/{idContenedor}', [CotizacionFinalController::class, 'generateIndividualCotizacion']);
            Route::post('/general/process-excel-data', [CotizacionFinalController::class, 'processExcelData']);
            Route::get('/general/download-plantilla-general/{idContenedor}', [CotizacionFinalController::class, 'downloadPlantillaGeneral']);
            Route::get('/general/download-cotizacion-excel/{idCotizacion}', [CotizacionFinalController::class, 'downloadCotizacionFinalExcel']);
            Route::get('/pagos/{idCotizacion}', [CotizacionFinalController::class, 'getCotizacionFinalDocumentacionPagos']);
            Route::get('/general/{idContenedor}', [CotizacionFinalController::class, 'getContenedorCotizacionesFinales']);
            Route::get('/general/{idContenedor}/headers', [CotizacionFinalController::class, 'getCotizacionFinalHeaders']);
            Route::post('/general/{idContenedor}/send-reminder-pago', [CotizacionFinalController::class, 'sendReminderPago']);
        });
        
        // Factura y guía
        Route::group(['prefix' => 'factura-guia'], function () {
            Route::get('/general/get-facturas-comerciales/{idCotizacion}', [FacturaGuiaController::class, 'getFacturasComerciales']);

            Route::get('/general/{idContenedor}/headers', [FacturaGuiaController::class, 'getHeadersData']);
            Route::post('/general/upload-guia-remision', [FacturaGuiaController::class, 'uploadGuiaRemision']);
            Route::post('/general/upload-factura-comercial', [FacturaGuiaController::class, 'uploadFacturaComercial']);
            Route::get('/general/{idContenedor}', [FacturaGuiaController::class, 'getContenedorFacturaGuia']);
            Route::delete('/general/delete-factura-comercial/{idContenedor}', [FacturaGuiaController::class, 'deleteFacturaComercial']);
            Route::delete('/general/delete-guia-remision/{idContenedor}', [FacturaGuiaController::class, 'deleteGuiaRemision']);
            Route::post('/send-factura/{idCotizacion}', [FacturaGuiaController::class, 'sendFactura']);
            Route::post('/send-guia/{idCotizacion}', [FacturaGuiaController::class, 'sendGuia']);

            // Contabilidad — comprobantes, constancias de pago, detalle, enviar formulario
            Route::prefix('contabilidad')->group(function () {
                Route::post('/upload-comprobante', [FacturaGuiaController::class, 'uploadComprobante']);
                Route::post('/upload-constancia/{comprobanteId}', [FacturaGuiaController::class, 'uploadConstancia']);
                Route::delete('/delete-comprobante/{id}', [FacturaGuiaController::class, 'deleteComprobante']);
                Route::delete('/delete-constancia/{id}', [FacturaGuiaController::class, 'deleteDetraccion']);
                Route::get('/detalle/{idCotizacion}', [FacturaGuiaController::class, 'getContabilidadDetalle']);
                Route::put('/nota/{idCotizacion}', [FacturaGuiaController::class, 'saveNotaContabilidad']);
                Route::get('/clientes/{idContenedor}', [FacturaGuiaController::class, 'getClientesContenedor']);
                Route::post('/enviar-formulario/{idContenedor}', [FacturaGuiaController::class, 'enviarFormulario']);
                // Envío individual por cotización (desde menú hamburguesa de la tabla)
                Route::post('/send-comprobantes/{idCotizacion}', [FacturaGuiaController::class, 'sendComprobantesContabilidad']);
                Route::post('/send-guias/{idCotizacion}', [FacturaGuiaController::class, 'sendGuiasContabilidad']);
                Route::post('/send-detracciones/{idCotizacion}', [FacturaGuiaController::class, 'sendDetraccionesContabilidad']);
                Route::post('/send-formulario/{idCotizacion}', [FacturaGuiaController::class, 'sendFormularioContabilidad']);
                // Formulario comprobante enviado por el cliente
                Route::get('/comprobante-form/{idCotizacion}', [ComprobanteFormController::class, 'getFormByCotizacion']);
            });
        });
        
        // Aduana
        Route::group(['prefix' => 'aduana'], function () {
            Route::get('/{idContenedor}', [AduanaController::class, 'viewFormularioAduana']);
            Route::post('/', [AduanaController::class, 'saveFormularioAduana']);
            Route::delete('/files/{idFile}', [AduanaController::class, 'deleteFileAduana']);
        });
        
        Route::get('{id}', [ContenedorController::class, 'show']);
        Route::put('{id}', [ContenedorController::class, 'update']);
        Route::delete('{id}', [ContenedorController::class, 'destroy']);
        Route::get('filters/options', [ContenedorController::class, 'filterOptions']);

        // Entrega
        Route::group(['prefix' => 'entrega'], function () {
            Route::post('/horarios', [EntregaController::class, 'storeHorarios']);
            Route::post('/horarios/edit', [EntregaController::class, 'editHorarios']);
            Route::post('/horarios/seleccion', [EntregaController::class, 'seleccionHorarios']);
            Route::delete('/horarios/delete', [EntregaController::class, 'deleteHorarios']);
            Route::get('/agencias', [EntregaController::class, 'getAgencias']);
            Route::post('/entregas/conformidad', [EntregaController::class, 'uploadConformidad']);
            Route::delete('/entregas/conformidad/{id}', [EntregaController::class, 'deleteConformidad']);
            Route::get('/cargo-entrega-pdf/{idContenedor}/{idCotizacion}', [EntregaController::class, 'getCargoEntregaPdf']);
            Route::post('/cargo-entrega-firmar', [EntregaController::class, 'signCargoEntrega']);

            Route::get('/{idContenedor}/horarios-disponibles', [EntregaController::class, 'getHorariosDisponibles']);
            // CRUD fechas
            Route::post('/{idContenedor}/fechas', [EntregaController::class, 'createFecha']);
            Route::delete('/{idContenedor}/fechas/{idFecha}', [EntregaController::class, 'deleteFecha']);
            // CRUD rangos (horarios) por fecha
            Route::post('/{idContenedor}/fechas/{idFecha}/rangos', [EntregaController::class, 'createRango']);
            Route::put('/{idContenedor}/fechas/{idFecha}/rangos/{idRango}', [EntregaController::class, 'updateRango']);
            Route::delete('/{idContenedor}/fechas/{idFecha}/rangos/{idRango}', [EntregaController::class, 'deleteRango']);
            Route::get('/{idContenedor}/headers', [EntregaController::class, 'getHeaders']);
            Route::get('/clientes/{idContenedor}/export-excel', [EntregaController::class, 'exportClientesEntregaExcel']);
            Route::get('/clientes/{idContenedor}', [EntregaController::class, 'getClientesEntrega']);
            // Export ROTULADO PARED
            Route::get('/{idContenedor}/rotulado-pared', [EntregaController::class, 'downloadPlantillas']);
            Route::post('/clientes/{idContenedor}/sendForm', [EntregaController::class, 'sendForm']);
            Route::get('/entregas/{idContenedor}', [EntregaController::class, 'getEntregas']);
            Route::get('/entregas/detalle/{idCotizacion}', [EntregaController::class, 'getEntregasDetalle']);
            // Alias POST sin sufijo /update para compatibilidad con clientes existentes
            Route::post('/entregas/detalle/{idCotizacion}', [EntregaController::class, 'updateEntregasDetalle']);
            Route::post('/entregas/detalle/{idCotizacion}/update', [EntregaController::class, 'updateEntregasDetalle']);
            Route::delete('/entregas/detalle/{idCotizacion}', [EntregaController::class, 'deleteEntregasDetalle']);
            // Conformidad de entrega (fotos)
            // Alias: permitir POST con id de cotización en la URL (para clientes que no envían id_cotizacion en body)
            Route::post('/entregas/conformidad/{idCotizacion}', [EntregaController::class, 'uploadConformidadForCotizacion']);
            Route::get('/entregas/conformidad/{idCotizacion}', [EntregaController::class, 'getConformidad']);
            Route::post('/entregas/conformidad/{id}/update', [EntregaController::class, 'updateConformidad']);
            Route::post('/entregas/updateStatus/{idCotizacion}', [EntregaController::class, 'updateStatusEntrega']);
            //delivery pagos /id
            Route::delete('/delivery/pagos/{idCotizacion}',  [ClientesPagosController::class, 'delete']);
            Route::post('/delivery/importe', [EntregaController::class, 'saveImporteDelivery']);
            Route::post('/delivery/servicio', [EntregaController::class, 'saveServicioDelivery']);
            Route::post('/delivery/pagos', [EntregaController::class, 'savePagosDelivery']);
            Route::post('/delivery/send-message/{idCotizacion}', [EntregaController::class, 'sendMessageDelivery']);
            Route::post('/delivery/recordatorio-formulario/{idCotizacion}', [EntregaController::class, 'sendRecordatorioFormularioDelivery']);
            Route::post('/delivery/cobro-cotizacion-final/{idCotizacion}', [EntregaController::class, 'sendCobroCotizacionFinalDelivery']);
            Route::post('/delivery/cobro-delivery/{idCotizacion}', [EntregaController::class, 'sendCobroDeliveryDelivery']);
            Route::get('/delivery/all', [EntregaController::class, 'getAllDelivery']);

            Route::get('/delivery/{idContenedor}', [EntregaController::class, 'getDelivery']);
        });
    });

    // Tipos de cliente
    Route::group(['prefix' => 'tipos-cliente'], function () {
        Route::get('/', [TipoClienteController::class, 'index']);
        Route::post('/', [TipoClienteController::class, 'store']);
        Route::get('{id}', [TipoClienteController::class, 'show']);
        Route::put('{id}', [TipoClienteController::class, 'update']);
        Route::delete('{id}', [TipoClienteController::class, 'destroy']);
    });

    // Cotizaciones con proveedores
    Route::group(['prefix' => 'cotizaciones-proveedores'], function () {
        //send-rotulado
        Route::post('proveedor/send-rotulado', [CotizacionProveedorController::class, 'sendRotulado']);
        Route::get('proveedor/get-google-sheet-values', [SheetController::class, 'getMergedRanges']);
        Route::get('proveedor/cotizacion/{idCotizacion}', [CotizacionProveedorController::class, 'getCotizacionProveedorByIdCotizacion']);
        Route::get('contenedor/{idContenedor}', [CotizacionProveedorController::class, 'getContenedorCotizacionProveedores']);
        Route::patch('proveedor/{idProveedor}/arrive-date', [CotizacionProveedorController::class, 'updateArriveDate']);
        Route::post('proveedor', [CotizacionProveedorController::class, 'updateProveedorData']);
        Route::delete('{idCotizacion}/proveedor/{idProveedor}', [CotizacionProveedorController::class, 'deleteCotizacion']);
        Route::post('proveedor/estado', [CotizacionProveedorController::class, 'updateEstadoCotizacionProveedor']);
        Route::get('proveedor/download-embarque/{idContenedor}', [CotizacionProveedorController::class, 'downloadEmbarque']);
        Route::post('proveedor/refresh-rotulado-status/{id}', [CotizacionProveedorController::class, 'refreshRotuladoStatus']);
        Route::delete('proveedor/documento/{idFile}', [CotizacionProveedorController::class, 'deleteFileDocumentation']);
        Route::delete('proveedor/inspeccion/{idFile}', [CotizacionProveedorController::class, 'deleteFileInspection']);
        Route::post('proveedor/documento', [CotizacionProveedorController::class, 'saveDocumentation']);
        Route::post('proveedor/inspeccion', [CotizacionProveedorController::class, 'saveInspection']);
        Route::get('proveedor/documentos/{idProveedor}', [CotizacionProveedorController::class, 'getFilesAlmacenDocument']);
        Route::get('proveedor/inspeccion/{idProveedor}', [CotizacionProveedorController::class, 'getFilesAlmacenInspection']);
        Route::post('proveedor/inspeccion/enviar', [CotizacionProveedorController::class, 'validateToSendInspectionMessage']);
        Route::post('proveedor/inspeccion/test', [CotizacionProveedorController::class, 'testSendMediaInspection']);
        Route::get('proveedor/notas/{idProveedor}', [CotizacionProveedorController::class, 'getNotes']);
        Route::post('proveedor/notas', [CotizacionProveedorController::class, 'addNote']);
        Route::get('proveedor/{idProveedor}', [CotizacionProveedorController::class, 'getCotizacionProveedor']);
    });
    
    // Cotizaciones pagos
    Route::group(['prefix' => 'cotizaciones-pagos'], function () {
        Route::get('{idContenedor}', [CotizacionPagosController::class, 'getClientesDocumentacionPagos']);
    }); 
    
    // Pagos
    Route::group(['prefix' => 'pagos'], function () {
        Route::get('consolidado', [PagosController::class, 'getConsolidadoPagos']);
        Route::get('consolidado/delivery/{idCotizacion}', [PagosController::class, 'getDetailsPagosConsolidadoDelivery']);
        Route::get('consolidado/{idCotizacion}', [PagosController::class, 'getDetailsPagosConsolidado']);
        Route::put('consolidado/saveStatus/{idPago}', [PagosController::class, 'actualizarPagoCoordinacion']);
        Route::put('consolidado/updateNota/{idCotizacion}', [PagosController::class, 'updateNotaConsolidado']);
        Route::get('cursos', [PagosController::class, 'getCursosPagos']);
        Route::get('cursos/{idPedidoCurso}', [PagosController::class, 'getDetailsPagosCurso']);
        Route::put('cursos/saveStatus/{idPago}', [PagosController::class, 'updateStatusCurso']);
        Route::put('cursos/updateNota/{idPedidoCurso}', [PagosController::class, 'updateNotaCurso']);
    });

    // Inspeccionados (vista global Contabilidad)
    Route::get('inspeccionados', [InspeccionadosController::class, 'index']);

    // Importación
    Route::group(['prefix' => 'import'], function () {
        Route::post('excel', [ImportController::class, 'importExcel']);
        Route::get('template', [ImportController::class, 'downloadTemplate']);
        Route::get('stats', [ImportController::class, 'getImportStats']);
    });
});

// Ruta legacy de consolidado
Route::group(['prefix' => 'consolidado', 'middleware' => 'jwt.auth'], function () {
    Route::group(['prefix' => 'cotizacion'], function () {
        Route::get('clientes-documentacion/{id}', [CotizacionController::class, 'showClientesDocumentacion']);
    });
});
