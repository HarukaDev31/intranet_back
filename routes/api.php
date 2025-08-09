<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\UsuarioGrupoController;
use App\Http\Controllers\ClientesHistorialController;
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
// Rutas de base de datos
Route::group(['prefix' => 'base-datos', 'middleware' => 'jwt.auth'], function () {
    
    // Rutas de productos
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
    
    // Rutas de regulaciones
    Route::group(['prefix' => 'regulaciones'], function () {
        // Entidades y rubros
        Route::get('/entidades', [EntidadesController::class, 'getDropdown']);
        Route::post('/entidades', [EntidadesController::class, 'store']);
        Route::get('/rubros', [ProductoRubroController::class, 'getDropdown']);
        Route::post('/rubros', [ProductoRubroController::class, 'store']);
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
            Route::post('/', [EtiquetadoController::class, 'store']); // Crear o actualizar
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

    // Rutas de usuarios y grupos
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
Route::group(['prefix' => 'carga-consolidada', 'middleware' => 'jwt.auth'], function () {
    
    // Rutas de contenedores
    Route::group(['prefix' => 'contenedores'], function () {
        Route::get('/', [ContenedorController::class, 'index']);
        Route::post('/', [ContenedorController::class, 'store']);
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

    // Rutas de cotizaciones
    Route::group(['prefix' => 'cotizaciones'], function () {
        Route::get('/', [CotizacionController::class, 'index']);
        Route::post('/', [CotizacionController::class, 'store']);
        Route::get('{id}', [CotizacionController::class, 'show']);
        Route::put('{id}', [CotizacionController::class, 'update']);
        Route::delete('{id}', [CotizacionController::class, 'destroy']);
        Route::get('filters/options', [CotizacionController::class, 'filterOptions']);
    });

    // Rutas de pagos
    Route::group(['prefix' => 'pagos'], function () {
        Route::get('consolidado', [PagosController::class, 'getConsolidadoPagos']);
        Route::get('consolidado/{idCotizacion}', [PagosController::class, 'getDetailsPagosConsolidado']);
        Route::get('cursos', [PagosController::class, 'getCursosPagos']);
        Route::get('cursos/{idPedidoCurso}', [PagosController::class, 'getDetailsPagosCurso']);
    });

    // Rutas de importación
    Route::group(['prefix' => 'import'], function () {
        Route::post('excel', [ImportController::class, 'importExcel']);
        Route::get('template', [ImportController::class, 'downloadTemplate']);
        Route::get('stats', [ImportController::class, 'getImportStats']);
    });
});