<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use BeyondCode\LaravelWebSockets\Facades\WebSocketsRouter;
use App\Http\Controllers\Broadcasting\BroadcastController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\BaseDatos\ProductosController;
use App\Http\Controllers\BaseDatos\Regulaciones\EntidadesController;
use App\Http\Controllers\BaseDatos\Regulaciones\ProductoRubroController;
use App\Http\Controllers\BaseDatos\Regulaciones\AntidumpingController;
use App\Http\Controllers\BaseDatos\Regulaciones\PermisoController;
use App\Http\Controllers\BaseDatos\Regulaciones\EtiquetadoController;
use App\Http\Controllers\BaseDatos\Regulaciones\DocumentosEspecialesController;
use App\Http\Controllers\BaseDatos\ClientesController;
use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Http\Controllers\CargaConsolidada\TipoClienteController;
use App\Http\Controllers\CargaConsolidada\CotizacionController;
use App\Http\Controllers\CargaConsolidada\PagosController;
use App\Http\Controllers\CargaConsolidada\ImportController;
use App\Http\Controllers\CargaConsolidada\CotizacionProveedorController;
use App\Http\Controllers\UsuarioGrupoController;
use App\Http\Controllers\ClientesHistorialController;
use App\Http\Controllers\Curso\CursoController;
use App\Http\Controllers\CargaConsolidada\Clientes\GeneralController;
use App\Http\Controllers\CargaConsolidada\Clientes\VariacionController;
use App\Http\Controllers\CargaConsolidada\Documentacion\DocumentacionController;
use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use App\Http\Controllers\CargaConsolidada\FacturaGuiaController;
use App\Http\Controllers\Commons\PaisController;
use App\Http\Controllers\CargaConsolidada\CotizacionPagosController;
use App\Http\Controllers\CargaConsolidada\AduanaController;
use App\Http\Controllers\CargaConsolidada\Clientes\PagosController as ClientesPagosController;
use App\Http\Controllers\CalculadoraImportacion\CalculadoraImportacionController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Rutas de autenticación
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);

    // Rutas protegidas con JWT
    Route::group(['middleware' => 'jwt.auth'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Rutas de Broadcasting
Route::group(['middleware' => ['jwt.auth']], function () {
    Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate']);
});

// Rutas de menús
Route::group(['prefix' => 'menu', 'middleware' => 'jwt.auth'], function () {
    Route::get('listar', [MenuController::class, 'listarMenu']);
    Route::get('get', [MenuController::class, 'getMenus']);
});
Route::group(['prefix' => 'consolidado', 'middleware' => 'jwt.auth'], function () {
    Route::group(['prefix' => 'cotizacion'], function () {

        Route::get('clientes-documentacion/{id}', [CotizacionController::class, 'showClientesDocumentacion']);
    });
});
Route::group(['prefix' => 'base-datos', 'middleware' => 'jwt.auth'], function () {
    Route::group(['prefix' => 'productos'], function () {
        Route::get('list-excels', [ProductosController::class, 'obtenerListExcel']);

        Route::get('/', [ProductosController::class, 'index']);
        Route::get('filters/options', [ProductosController::class, 'filterOptions']);
        Route::get('export', [ProductosController::class, 'export']);
        Route::post('import-excel', [ProductosController::class, 'importExcel']);
        Route::delete('delete-excel/{id}', [ProductosController::class, 'deleteExcel']);

        Route::get('{id}', [ProductosController::class, 'show']);
        Route::put('{id}', [ProductosController::class, 'update']);
    });

    Route::group(['prefix' => 'regulaciones'], function () {
        // Entidades y rubros
        Route::get('/entidades', [EntidadesController::class, 'getDropdown']);
        Route::post('/entidades', [EntidadesController::class, 'store']);
        Route::put('/entidades/{id}', [EntidadesController::class, 'update']);
        Route::get('/rubros', [ProductoRubroController::class, 'getDropdown']);
        Route::post('/rubros', [ProductoRubroController::class, 'store']);
        Route::put('/rubros/{id}', [ProductoRubroController::class, 'update']);
        // Regulaciones antidumping
        Route::group(['prefix' => 'antidumping'], function () {
            Route::get('/', [AntidumpingController::class, 'index']);
            Route::post('/', [AntidumpingController::class, 'store']); // Crear o actualizar
            Route::post('/test-validation', [AntidumpingController::class, 'testValidation']); // Ruta de prueba
            Route::get('/{id}', [AntidumpingController::class, 'show']);
            Route::delete('/{id}', [AntidumpingController::class, 'destroy']);
            Route::delete('/{id}/images/{imageId}', [AntidumpingController::class, 'deleteImage']);
        });

        // Regulaciones de permisos (CRUD + deleteDocument)
        Route::group(['prefix' => 'permisos'], function () {
            Route::get('/', [PermisoController::class, 'index']);
            Route::post('/', [PermisoController::class, 'store']); // Crear o actualizar
            Route::get('/{id}', [PermisoController::class, 'show']);
            Route::delete('/{id}', [PermisoController::class, 'destroy']);
            Route::delete('/{id}/documents/{documentId}', [PermisoController::class, 'deleteDocument']);
        });

        // Regulaciones de etiquetado (CRUD + deleteImage)
        Route::group(['prefix' => 'etiquetado'], function () {
            Route::get('/', [EtiquetadoController::class, 'index']);
            Route::post('/', [EtiquetadoController::class, 'store']); 
            Route::get('/{id}', [EtiquetadoController::class, 'show']);
            Route::delete('/{id}', [EtiquetadoController::class, 'destroy']);
            Route::delete('/{id}/images/{imageId}', [EtiquetadoController::class, 'deleteImage']);
        });

        // Regulaciones de documentos especiales (CRUD + deleteDocument)
        Route::group(['prefix' => 'documentos'], function () {
            Route::get('/', [DocumentosEspecialesController::class, 'index']);
            Route::post('/', [DocumentosEspecialesController::class, 'store']); // Crear o actualizar
            Route::get('/{id}', [DocumentosEspecialesController::class, 'show']);
            Route::delete('/{id}', [DocumentosEspecialesController::class, 'destroy']);
            Route::delete('/{id}/documents/{documentId}', [DocumentosEspecialesController::class, 'deleteDocument']);
        });
        Route::delete('/rubros/{id}', [ProductoRubroController::class, 'destroy']);
        Route::delete('/entidades/{id}', [EntidadesController::class, 'destroy']);
    });

    Route::group(['prefix' => 'usuarios-grupos'], function () {
        Route::get('usuario/{id}', [UsuarioGrupoController::class, 'getUsuarioConGrupos']);
        Route::get('grupo/{grupoId}', [UsuarioGrupoController::class, 'getUsuariosPorGrupo']);
        Route::post('verificar-pertenencia', [UsuarioGrupoController::class, 'verificarPertenencia']);
        Route::get('grupos-disponibles/{usuarioId}', [UsuarioGrupoController::class, 'getGruposDisponibles']);
        Route::get('estadisticas', [UsuarioGrupoController::class, 'getEstadisticas']);
    });

    // Rutas de clientes
    Route::group(['prefix' => 'clientes'], function () {

        Route::get('export', [ClientesController::class, 'export']);
        // Rutas de importación Excel
        Route::post('import-excel', [ClientesController::class, 'importExcel']);
        Route::post('descargar-plantilla', [ClientesController::class, 'descargarPlantilla']);
        Route::get('list-excels', [ClientesController::class, 'obtenerListExcel']);
        Route::delete('delete-excel/{id}', [ClientesController::class, 'deleteExcel']);
        // Rutas CRUD (deben ir después de las rutas específicas)
        Route::get('{id}', [ClientesController::class, 'show']);
        Route::delete('{id}', [ClientesController::class, 'destroy']);
        Route::put('{id}', [ClientesController::class, 'update']);
        Route::get('/', [ClientesController::class, 'index']);
        Route::post('/', [ClientesController::class, 'store']);
    });
});
// Rutas de carga consolidada
Route::group(['prefix' => 'cursos'], function () {
    Route::get('filters/options', [CursoController::class, 'filterOptions']);
    Route::get('/', [CursoController::class, 'index']);
    Route::put('cliente/{id}', [CursoController::class, 'actualizarDatosCliente']);
    Route::put('usuario-moodle/{id}', [CursoController::class, 'setUsuarioMoodle']);
    Route::put('pedido/{id}', [CursoController::class, 'actualizarPedido']);
    Route::put('pedido/{id}/importe', [CursoController::class, 'actualizarImportePedido']);
    Route::delete('pedido/{id}', [CursoController::class, 'eliminarPedido']);
    Route::get('pedido/{id}/cliente', [CursoController::class, 'getDatosClientePorPedido']);
    Route::put('cliente/{id}', [CursoController::class, 'actualizarDatosCliente']);

    //Pagos curso
    Route::get('pedido/{id}/pagos', [CursoController::class, 'getPagosCursoPedido']);
    Route::post('pedido/pago', [CursoController::class, 'saveClientePagosCurso']);
    Route::delete('pedido/pago/{id}', [CursoController::class, 'borrarPagoCurso']);

     // Campañas
    Route::post('campana', [CursoController::class, 'crearCampana']);
    Route::put('campana/{id}', [CursoController::class, 'editarCampana']);
    Route::delete('campana/{id}', [CursoController::class, 'borrarCampana']);
    Route::get('campanas', [CursoController::class, 'getCampanas']);
    Route::get('campana/{id}', [CursoController::class, 'getCampanaById']);
    Route::get('campanas-activas', [CursoController::class, 'getCampanasActivas']);
    Route::put('pedido/{id}/campana', [CursoController::class, 'asignarCampanaPedido']);
});
Route::group(['prefix' => 'carga-consolidada', 'middleware' => 'jwt.auth'], function () {

    // Rutas de contenedores
    Route::group(['prefix' => 'contenedor'], function () {
        Route::get('valid-containers', [ContenedorController::class, 'getValidContainers']);
        Route::get('cargas-disponibles', [ContenedorController::class, 'getCargasDisponibles']);
        Route::post('move-cotizacion', [ContenedorController::class, 'moveCotizacionToConsolidado']);
        Route::post('move-cotizacion-calculadora', [ContenedorController::class, 'moveCotizacionToCalculadora']);
        Route::get('/', [ContenedorController::class, 'index']);
        Route::get('pasos/{idContenedor}', [ContenedorController::class, 'getContenedorPasos']);
        Route::post('/', [ContenedorController::class, 'store']);
        Route::post('/estado-documentacion', [ContenedorController::class, 'updateEstadoDocumentacion']);
        Route::group(['prefix' => 'cotizaciones'], function () {
            Route::post('/{id}/refresh', [CotizacionController::class, 'refreshCotizacionFile']);
            Route::get('/{idContenedor}/headers', [CotizacionController::class, 'getHeadersData']);
            Route::get('/{idContenedor}', [CotizacionController::class, 'index']);
            Route::put('{id}', [CotizacionController::class, 'update']);
            Route::post('{id}/estado-cotizador', [CotizacionController::class, 'updateEstadoCotizacion']);

            Route::post('{id}/file', [CotizacionController::class, 'updateCotizacionFile']);
            Route::delete('{id}/file', [CotizacionController::class, 'deleteCotizacionFile']);
            Route::delete('{id}', [CotizacionController::class, 'destroy']);
            Route::post('/', [CotizacionController::class, 'store']);

            Route::get('filters/options', [CotizacionController::class, 'filterOptions']);
        }); 
        Route::group(['prefix' => 'clientes'], function () {
            Route::get('/general/{idContenedor}/headers', [GeneralController::class, 'getClientesHeader']);
            Route::get('/pagos/{idContenedor}', [ClientesPagosController::class, 'index']);
            Route::get('/general/{idContenedor}', [GeneralController::class, 'index']);
            Route::post('/general/estado-cliente', [GeneralController::class, 'updateEstadoCliente']);
            Route::get('/variacion/{idContenedor}', [VariacionController::class, 'index']);
            Route::post('/variacion/vol-selected', [VariacionController::class, 'updateVolSelected']);
            Route::post('/variacion/documentacion/proveedor/{idProveedor}/create', [DocumentacionController::class, 'createProveedorDocumentacionFolder']);
            Route::post('/general/status-cliente-doc', [GeneralController::class, 'updateStatusCliente']);
            Route::post('/variacion/documentacion/proveedor/{idProveedor}', [DocumentacionController::class, 'updateClienteDocumentacion']);
            Route::delete('/variacion/documentacion/proveedor/{idProveedor}/factura-comercial', [DocumentacionController::class, 'deleteProveedorFacturaComercial']);
            Route::delete('/variacion/documentacion/proveedor/{idProveedor}/excel-confirmacion', [DocumentacionController::class, 'deleteProveedorExcelConfirmacion']);
            Route::delete('/variacion/documentacion/proveedor/{idProveedor}/packing-list', [DocumentacionController::class, 'deleteProveedorPackingList']);
            Route::get('/variacion/documentacion/{idCotizacion}', [VariacionController::class, 'showClientesDocumentacion']);
            Route::post('/pagos', [ClientesPagosController::class, 'store']);
            Route::delete('/pagos/{id}', [ClientesPagosController::class, 'delete']);

        });
        Route::group(['prefix' => 'documentacion'], function () {
            Route::get('/download-factura-comercial/{idContenedor}', [DocumentacionController::class, 'downloadFacturaComercial']);
            Route::delete('/delete/{idFile}', [DocumentacionController::class, 'deleteFileDocumentation']);
            Route::post('/upload-file-documentation', [DocumentacionController::class, 'uploadFileDocumentation']);
            Route::post('/create-folder', [DocumentacionController::class, 'createDocumentacionFolder']);
            Route::get('/{id}', [DocumentacionController::class, 'getDocumentationFolderFiles']);
            Route::get('/download-zip/{idContenedor}', [DocumentacionController::class, 'downloadDocumentacionZip']);
        });
        Route::group(['prefix' => 'cotizacion-final'], function () {
            Route::options('/general/upload-plantilla-final', [CotizacionFinalController::class, 'handleOptions']);
            Route::post('/general/upload-plantilla-final', [CotizacionFinalController::class, 'generateMassiveExcelPayrolls']);
            Route::get('/general/check-temp-directory', [CotizacionFinalController::class, 'checkTempDirectory']);

            Route::put('/general/update-estado', [CotizacionFinalController::class, 'updateEstadoCotizacionFinal']);
            Route::post('/general/upload-factura-comercial', [CotizacionFinalController::class, 'uploadFacturaComercial']);
            Route::post('/general/generate-individual/{idContenedor}', [CotizacionFinalController::class, 'generateIndividualCotizacion']);
            Route::post('/general/process-excel-data', [CotizacionFinalController::class, 'processExcelData']);

            Route::get('/general/download-plantilla-general/{idContenedor}', [CotizacionFinalController::class, 'downloadPlantillaGeneral']);
            Route::get('/pagos/{idCotizacion}', [CotizacionFinalController::class, 'getCotizacionFinalDocumentacionPagos']);
            Route::get('/general/{idContenedor}', [CotizacionFinalController::class, 'getContenedorCotizacionesFinales']);
            Route::get('/general/{idContenedor}/headers', [CotizacionFinalController::class, 'getCotizacionFinalHeaders']);

        });
        Route::group(['prefix' => 'factura-guia'], function () {
            //get upload-guia-remision ,upload-factura-comercial
            Route::get('/general/{idContenedor}/headers', [FacturaGuiaController::class, 'getHeadersData']);
            Route::post('/general/upload-guia-remision', [FacturaGuiaController::class, 'uploadGuiaRemision']);
            Route::post('/general/upload-factura-comercial', [FacturaGuiaController::class, 'uploadFacturaComercial']);
            Route::get('/general/{idContenedor}', [FacturaGuiaController::class, 'getContenedorFacturaGuia']);

        });
        Route::group(['prefix' => 'aduana'], function () {
            Route::get('/{idContenedor}', [AduanaController::class, 'viewFormularioAduana']);
            Route::post('/', [AduanaController::class, 'saveFormularioAduana']);
        });
        Route::get('{id}', [ContenedorController::class, 'show']);
        Route::put('{id}', [ContenedorController::class, 'update']);
        Route::delete('{id}', [ContenedorController::class, 'destroy']);
        Route::get('filters/options', [ContenedorController::class, 'filterOptions']);
    });

    // Rutas de tipos de cliente
    Route::group(['prefix' => 'tipos-cliente'], function () {
        Route::get('/', [TipoClienteController::class, 'index']);
        Route::post('/', [TipoClienteController::class, 'store']);
        Route::get('{id}', [TipoClienteController::class, 'show']);
        Route::put('{id}', [TipoClienteController::class, 'update']);
        Route::delete('{id}', [TipoClienteController::class, 'destroy']);
    });


    // Rutas de cotizaciones con proveedores
    Route::group(['prefix' => 'cotizaciones-proveedores'], function () {
        Route::get('contenedor/{idContenedor}', [CotizacionProveedorController::class, 'getContenedorCotizacionProveedores']);
        Route::post('proveedor', [CotizacionProveedorController::class, 'updateProveedorData']);
        Route::delete('{idCotizacion}/proveedor/{idProveedor}', [CotizacionProveedorController::class, 'deleteCotizacion']);
        Route::post('proveedor/estado', [CotizacionProveedorController::class, 'updateEstadoCotizacionProveedor']);
        // Rutas para archivos y notas
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
    Route::group(['prefix' => 'cotizaciones-pagos'], function () {
        Route::get('{idContenedor}', [CotizacionPagosController::class, 'getClientesDocumentacionPagos']);
    }); 
    // Rutas de pagos
    Route::group(['prefix' => 'pagos'], function () {
        Route::get('consolidado', [PagosController::class, 'getConsolidadoPagos']);
        Route::get('consolidado/{idCotizacion}', [PagosController::class, 'getDetailsPagosConsolidado']);
        Route::put('consolidado/saveStatus/{idPago}', [PagosController::class, 'actualizarPagoCoordinacion']);
        Route::put('consolidado/updateNota/{idCotizacion}', [PagosController::class, 'updateNotaConsolidado']);
        Route::get('cursos', [PagosController::class, 'getCursosPagos']);
        Route::get('cursos/{idPedidoCurso}', [PagosController::class, 'getDetailsPagosCurso']);
        Route::put('cursos/saveStatus/{idPago}', [PagosController::class, 'updateStatusCurso']);
        Route::put('cursos/updateNota/{idPedidoCurso}', [PagosController::class, 'updateNotaCurso']);
    });

    // Rutas de importación
    Route::group(['prefix' => 'import'], function () {
        Route::post('excel', [ImportController::class, 'importExcel']);
        Route::get('template', [ImportController::class, 'downloadTemplate']);
        Route::get('stats', [ImportController::class, 'getImportStats']);
    });
   
});
Route::group(['prefix' => 'calculadora-importacion', 'middleware' => 'jwt.auth'], function () {
    Route::post('clientes', [CalculadoraImportacionController::class, 'getClientesByWhatsapp']);
    Route::get('tarifas', [CalculadoraImportacionController::class, 'getTarifas']);
    
    Route::post('duplicate/{id}', [CalculadoraImportacionController::class, 'duplicate']);
    Route::get('/', [CalculadoraImportacionController::class, 'index']);
    Route::post('/', [CalculadoraImportacionController::class, 'store']);
    Route::get('/{id}', [CalculadoraImportacionController::class, 'show']);
    Route::get('/cliente', [CalculadoraImportacionController::class, 'getCalculosPorCliente']);
    Route::post('/change-estado/{id}', [CalculadoraImportacionController::class, 'changeEstado']);
    Route::delete('/{id}', [CalculadoraImportacionController::class, 'destroy']);
});
Route::group(['prefix' => 'options', 'middleware' => 'jwt.auth'], function () {
    Route::get('paises', [PaisController::class, 'getPaisDropdown']);
});