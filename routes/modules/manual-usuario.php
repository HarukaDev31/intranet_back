<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManualUsuario\ManualUsuarioController;
use App\Http\Controllers\ManualUsuario\ManualUsuarioAdminController;

/*
|--------------------------------------------------------------------------
| Manual de usuario (JWT internos)
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'manual-usuario', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [ManualUsuarioController::class, 'index']);
    Route::get('/me', [ManualUsuarioController::class, 'me']);
    Route::get('/me/pdf', [ManualUsuarioController::class, 'pdfMe']);
    Route::get('/pdf', [ManualUsuarioController::class, 'pdfGlobal']);
    Route::get('/roles/{slug}', [ManualUsuarioController::class, 'showRole']);
    Route::get('/roles/{slug}/pdf', [ManualUsuarioController::class, 'pdfRole']);
    Route::get('/assets/{path}', [ManualUsuarioController::class, 'asset'])
        ->where('path', '.*');

    // Media subida por CMS (lectura autenticada)
    Route::get('/media/{id}', [ManualUsuarioAdminController::class, 'showMedia'])
        ->whereNumber('id');

    // Mantenedor admin (solo root)
    Route::group(['prefix' => 'admin'], function () {
        Route::get('/meta', [ManualUsuarioAdminController::class, 'meta']);

        Route::get('/pages', [ManualUsuarioAdminController::class, 'indexPages']);
        Route::post('/pages', [ManualUsuarioAdminController::class, 'storePage']);
        Route::get('/pages/{id}', [ManualUsuarioAdminController::class, 'showPage'])->whereNumber('id');
        Route::put('/pages/{id}', [ManualUsuarioAdminController::class, 'updatePage'])->whereNumber('id');
        Route::post('/pages/{id}/copy', [ManualUsuarioAdminController::class, 'copyPage'])->whereNumber('id');
        Route::delete('/pages/{id}', [ManualUsuarioAdminController::class, 'destroyPage'])->whereNumber('id');

        Route::post('/pages/{paginaId}/bloques', [ManualUsuarioAdminController::class, 'storeBlock'])->whereNumber('paginaId');
        Route::post('/pages/{paginaId}/bloques/from-page-widget', [ManualUsuarioAdminController::class, 'storeBlockFromPageWidget'])->whereNumber('paginaId');
        Route::put('/bloques/{id}', [ManualUsuarioAdminController::class, 'updateBlock'])->whereNumber('id');
        Route::delete('/bloques/{id}', [ManualUsuarioAdminController::class, 'destroyBlock'])->whereNumber('id');
        Route::post('/bloques/reorder', [ManualUsuarioAdminController::class, 'reorderBlocks']);

        Route::get('/media', [ManualUsuarioAdminController::class, 'indexMedia']);
        Route::post('/media', [ManualUsuarioAdminController::class, 'storeMedia']);
        Route::delete('/media/{id}', [ManualUsuarioAdminController::class, 'destroyMedia'])->whereNumber('id');
        Route::get('/capturas', [ManualUsuarioAdminController::class, 'indexCapturas']);
        Route::post('/bloques/{id}/asignar-captura', [ManualUsuarioAdminController::class, 'assignCaptura'])->whereNumber('id');
    });
});
