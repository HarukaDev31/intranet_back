<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BitrixWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Bitrix webhook recibido', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'ts' => now()->toISOString(),
        ]);

        return response()->json(['ok' => true]);
    }
}
