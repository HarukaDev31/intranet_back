<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PanelAcceso\GrupoController;
use App\Http\Controllers\PanelAcceso\UsuarioAdminController;
use App\Http\Controllers\PanelAcceso\MenuAccesoController;
use App\Http\Controllers\PanelAcceso\MenuCatalogoController;

/*
|--------------------------------------------------------------------------
| Rutas de Panel de Acceso
|--------------------------------------------------------------------------
|
| Rutas para gestión de cargos (grupos), usuarios y permisos de menú.
| Protegidas con JWT. La autorización por permisos se implementará
| en el futuro con middleware tipo canUse() sin necesidad de cambiar el frontend.
|
*/

Route::group(['prefix' => 'panel-acceso', 'middleware' => 'jwt.auth'], function () {

    // -----------------------------------------------------------------------
    // Cargos / Grupos
    // -----------------------------------------------------------------------
    Route::get('grupos', [GrupoController::class, 'index']);
    Route::post('grupos', [GrupoController::class, 'store']);
    Route::get('grupos/{id}', [GrupoController::class, 'show']);
    Route::put('grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('grupos/{id}', [GrupoController::class, 'destroy']);
    Route::patch('grupos/{id}/notificacion', [GrupoController::class, 'updateNotificacion']);

    // -----------------------------------------------------------------------
    // Usuarios (panel admin)
    // -----------------------------------------------------------------------
    Route::get('usuarios', [UsuarioAdminController::class, 'index']);
    Route::post('usuarios', [UsuarioAdminController::class, 'store']);
    Route::get('usuarios/{id}', [UsuarioAdminController::class, 'show']);
    Route::put('usuarios/{id}', [UsuarioAdminController::class, 'update']);
    Route::delete('usuarios/{id}', [UsuarioAdminController::class, 'destroy']);

    // -----------------------------------------------------------------------
    // Permisos de Menú
    // -----------------------------------------------------------------------
    Route::get('menu-acceso/{empresaId}/{orgId}/{grupoId}', [MenuAccesoController::class, 'getMenuPorGrupo']);
    Route::post('menu-acceso', [MenuAccesoController::class, 'guardarPermisos']);

    // -----------------------------------------------------------------------
    // Catálogo de Menús (Mantenedor)
    // -----------------------------------------------------------------------
    Route::get('menus', [MenuCatalogoController::class, 'index']);
    // icon-upload ANTES de /{id} para evitar conflicto de rutas
    Route::post('menus/icon-upload', [MenuCatalogoController::class, 'uploadIcon']);
    Route::post('menus', [MenuCatalogoController::class, 'store']);
    Route::put('menus/{id}', [MenuCatalogoController::class, 'update']);
    Route::delete('menus/{id}', [MenuCatalogoController::class, 'destroy']);
    Route::get('menus/{id}/grupos', [MenuCatalogoController::class, 'getGruposConAcceso']);
});
