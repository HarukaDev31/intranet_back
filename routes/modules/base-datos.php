<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseDatos\ProductosController;
use App\Http\Controllers\BaseDatos\Regulaciones\EntidadesController;
use App\Http\Controllers\BaseDatos\Regulaciones\ProductoRubroController;
use App\Http\Controllers\BaseDatos\Regulaciones\AntidumpingController;
use App\Http\Controllers\BaseDatos\Regulaciones\PermisoController;
use App\Http\Controllers\BaseDatos\Regulaciones\EtiquetadoController;
use App\Http\Controllers\BaseDatos\Regulaciones\DocumentosEspecialesController;
use App\Http\Controllers\BaseDatos\ClientesController;
use App\Http\Controllers\UsuarioGrupoController;

/*
|--------------------------------------------------------------------------
| Rutas de Base de Datos
|--------------------------------------------------------------------------
|
| Rutas para gestión de productos, regulaciones, clientes y usuarios
|
*/

Route::group(['prefix' => 'base-datos', 'middleware' => 'jwt.auth'], function () {
    
    // Productos
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

    // Regulaciones
    Route::group(['prefix' => 'regulaciones'], function () {
        // Entidades y rubros
        Route::get('/entidades', [EntidadesController::class, 'getDropdown']);
        Route::post('/entidades', [EntidadesController::class, 'store']);
        Route::put('/entidades/{id}', [EntidadesController::class, 'update']);
        Route::delete('/entidades/{id}', [EntidadesController::class, 'destroy']);
        Route::get('/rubros', [ProductoRubroController::class, 'getDropdown']);
        Route::post('/rubros', [ProductoRubroController::class, 'store']);
        Route::put('/rubros/{id}', [ProductoRubroController::class, 'update']);
        Route::delete('/rubros/{id}', [ProductoRubroController::class, 'destroy']);
        
        // Regulaciones antidumping
        Route::group(['prefix' => 'antidumping'], function () {
            Route::get('/', [AntidumpingController::class, 'index']);
            Route::post('/', [AntidumpingController::class, 'store']);
            Route::post('/test-validation', [AntidumpingController::class, 'testValidation']);
            Route::get('/{id}', [AntidumpingController::class, 'show']);
            Route::delete('/{id}', [AntidumpingController::class, 'destroy']);
            Route::delete('/{id}/images/{imageId}', [AntidumpingController::class, 'deleteImage']);
        });

        // Regulaciones de permisos
        Route::group(['prefix' => 'permisos'], function () {
            Route::get('/', [PermisoController::class, 'index']);
            Route::post('/', [PermisoController::class, 'store']);
            Route::get('/{id}', [PermisoController::class, 'show']);
            Route::delete('/{id}', [PermisoController::class, 'destroy']);
            Route::delete('/{id}/documents/{documentId}', [PermisoController::class, 'deleteDocument']);
        });

        // Regulaciones de etiquetado
        Route::group(['prefix' => 'etiquetado'], function () {
            Route::get('/', [EtiquetadoController::class, 'index']);
            Route::post('/', [EtiquetadoController::class, 'store']); 
            Route::get('/{id}', [EtiquetadoController::class, 'show']);
            Route::delete('/{id}', [EtiquetadoController::class, 'destroy']);
            Route::delete('/{id}/images/{imageId}', [EtiquetadoController::class, 'deleteImage']);
        });

        // Regulaciones de documentos especiales
        Route::group(['prefix' => 'documentos'], function () {
            Route::get('/', [DocumentosEspecialesController::class, 'index']);
            Route::post('/', [DocumentosEspecialesController::class, 'store']);
            Route::get('/{id}', [DocumentosEspecialesController::class, 'show']);
            Route::delete('/{id}', [DocumentosEspecialesController::class, 'destroy']);
            Route::delete('/{id}/documents/{documentId}', [DocumentosEspecialesController::class, 'deleteDocument']);
        });
    });

    // Usuarios y grupos
    Route::group(['prefix' => 'usuarios-grupos'], function () {
        Route::get('usuario/{id}', [UsuarioGrupoController::class, 'getUsuarioConGrupos']);
        Route::get('grupo/{grupoId}', [UsuarioGrupoController::class, 'getUsuariosPorGrupo']);
        Route::post('verificar-pertenencia', [UsuarioGrupoController::class, 'verificarPertenencia']);
        Route::get('grupos-disponibles/{usuarioId}', [UsuarioGrupoController::class, 'getGruposDisponibles']);
        Route::get('estadisticas', [UsuarioGrupoController::class, 'getEstadisticas']);
    });

    // Clientes
    Route::group(['prefix' => 'clientes'], function () {
        Route::get('export', [ClientesController::class, 'export']);
        Route::post('import-excel', [ClientesController::class, 'importExcel']);
        Route::post('descargar-plantilla', [ClientesController::class, 'descargarPlantilla']);
        Route::get('list-excels', [ClientesController::class, 'obtenerListExcel']);
        Route::delete('delete-excel/{id}', [ClientesController::class, 'deleteExcel']);
        Route::get('{id}', [ClientesController::class, 'show']);
        Route::delete('{id}', [ClientesController::class, 'destroy']);
        Route::put('{id}', [ClientesController::class, 'update']);
        Route::get('/', [ClientesController::class, 'index']);
        Route::post('/', [ClientesController::class, 'store']);
    });
});
