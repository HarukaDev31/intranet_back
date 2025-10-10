<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Curso\CursoController;

/*
|--------------------------------------------------------------------------
| Rutas de Cursos
|--------------------------------------------------------------------------
|
| Rutas para gestión de cursos, pedidos y pagos
|
*/

Route::group(['prefix' => 'cursos', 'middleware' => 'jwt.auth'], function () {
    Route::post('change-estado-usuario-externo', [CursoController::class, 'crearUsuarioCursosMoodle']);
    Route::get('filters/options', [CursoController::class, 'filterOptions']);
    Route::get('/', [CursoController::class, 'index']);
    Route::put('cliente/{id}', [CursoController::class, 'actualizarDatosCliente']);
    Route::put('usuario-moodle/{id}', [CursoController::class, 'setUsuarioMoodle']);
    Route::put('pedido/{id}', [CursoController::class, 'actualizarPedido']);
    Route::post('pedido/{id}/generar-constancia', [CursoController::class, 'generarConstanciaPedido']);
    Route::put('pedido/{id}/importe', [CursoController::class, 'actualizarImportePedido']);
    Route::delete('pedido/{id}', [CursoController::class, 'eliminarPedido']);
    Route::get('pedido/{id}/cliente', [CursoController::class, 'getDatosClientePorPedido']);
    Route::get('pagos', [CursoController::class, 'getPagosCurso']);
    
    // Pagos curso
    Route::get('pedido/{id}/pagos', [CursoController::class, 'getPagosCursoPedido']);
    Route::post('pagos', [CursoController::class, 'saveClientePagosCurso']);
    Route::delete('pagos/{id}', [CursoController::class, 'borrarPagoCurso']);
    
    Route::post('change-tipo-curso', [CursoController::class, 'asignarTipoCurso']);
    Route::post('change-estado-pedido', [CursoController::class, 'asignarCampanaPedidoCurso']);
    Route::post('change-importe-pedido', [CursoController::class, 'changeImportePedido']);
    
    // Campañas
    Route::post('campana', [CursoController::class, 'crearCampana']);
    Route::put('campana/{id}', [CursoController::class, 'editarCampana']);
    Route::delete('campana/{id}', [CursoController::class, 'borrarCampana']);
    Route::get('campanas', [CursoController::class, 'getCampanas']);
    Route::get('campana/{id}', [CursoController::class, 'getCampanaById']);
    Route::get('campanas-activas', [CursoController::class, 'getCampanasActivas']);
    Route::put('pedido/{id}/campana', [CursoController::class, 'asignarCampanaPedido']);
    
    Route::delete('{id}', [CursoController::class, 'eliminarPedido']);
});
