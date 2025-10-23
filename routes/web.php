<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebSocketController;
use App\Http\Controllers\Broadcasting\BroadcastController;
use App\Http\Controllers\FileController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Ruta de login básica para evitar errores de redirección
Route::get('/login', function () {
    return response()->json([
        'message' => 'Esta es una API. Use los endpoints de autenticación correspondientes.'
    ], 200);
})->name('login');

// WebSocket Dashboard Routes
Route::group(['prefix' => 'laravel-websockets'], function () {
    Route::get('/', [WebSocketController::class, 'showDashboard']);
    Route::post('statistics', function () {
        return app()->make('websockets.statistics')->store();
    });
});

// Broadcasting Authentication Route
Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware(['broadcasting.auth']);

// Ruta para servir archivos con CORS habilitado
Route::get('/files/{path}', [FileController::class, 'serveFile'])
    ->where('path', '.*')
    ->name('storage.file');

// Manejar requests OPTIONS para CORS preflight
Route::options('/files/{path}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', 'http://localhost:3001')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('path', '.*');