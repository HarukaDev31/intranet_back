<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebSocketController extends Controller
{
    public function showDashboard()
    {
        return view('websockets.dashboard', [
            'port' => config('websockets.dashboard.port', 6001),
            'host' => config('websockets.dashboard.host', '127.0.0.1'),
            'key' => config('websockets.apps.0.key'),
            'secret' => config('websockets.apps.0.secret'),
            'app_id' => config('websockets.apps.0.id'),
            'app_name' => config('websockets.apps.0.name'),
            'enable_client_messages' => config('websockets.apps.0.enable_client_messages', false),
            'enable_statistics' => config('websockets.apps.0.enable_statistics', true),
            'protocol' => config('websockets.ssl.local_cert', false) ? 'wss' : 'ws'
        ]);
    }
}
