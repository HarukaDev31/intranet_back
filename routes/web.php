<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebSocketController;
use App\Http\Controllers\Broadcasting\BroadcastController;

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