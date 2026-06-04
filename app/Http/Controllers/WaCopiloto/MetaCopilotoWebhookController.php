<?php

namespace App\Http\Controllers\WaCopiloto;

use App\Http\Controllers\Controller;
use App\Jobs\WaCopiloto\ProcessWaCopilotoInboundJob;
use App\Models\WaCopiloto\WaCopilotoWebhookLog;
use App\Support\WhatsApp\WaCopilotoJobContext;
use Illuminate\Http\Request;

class MetaCopilotoWebhookController extends Controller
{
    /**
     * GET — verificación hub Meta (Copiloto / ventas).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = (string) config('meta_whatsapp_copiloto.webhook_verify_token');
        if ($expected === '') {
            $expected = (string) config('meta_whatsapp.webhook_verify_token');
        }

        if ($mode === 'subscribe' && $token === $expected) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — eventos Meta para números Copiloto.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function receive(Request $request)
    {
        if (!$this->isValidSignature($request)) {
            return response()->json(['success' => false, 'message' => 'Firma inválida'], 403);
        }

        $payload = $request->all();
        $log = WaCopilotoWebhookLog::create([
            'payload' => $payload,
            'processed_at' => null,
        ]);

        ProcessWaCopilotoInboundJob::dispatch($log->id, WaCopilotoJobContext::resolveJobDomain());

        return response()->json(['success' => true]);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function isValidSignature(Request $request)
    {
        $secret = (string) config('meta_whatsapp_copiloto.app_secret');
        if ($secret === '') {
            $secret = (string) config('meta_whatsapp.app_secret');
        }
        if ($secret === '') {
            return app()->environment('local');
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        if ($signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
