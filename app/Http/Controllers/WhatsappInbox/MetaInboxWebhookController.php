<?php

namespace App\Http\Controllers\WhatsappInbox;

use App\Http\Controllers\Controller;
use App\Jobs\WhatsappInbox\ProcessWaInboxInboundJob;
use App\Models\WhatsappInbox\WaInboxWebhookLog;
use App\Support\WhatsApp\WaInboxJobContext;
use Illuminate\Http\Request;

class MetaInboxWebhookController extends Controller
{
    /**
     * GET — verificación hub Meta.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('meta_whatsapp.webhook_verify_token')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — eventos Meta.
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
        $log = WaInboxWebhookLog::create([
            'payload' => $payload,
            'processed_at' => null,
        ]);

        ProcessWaInboxInboundJob::dispatch($log->id, WaInboxJobContext::resolveJobDomain());

        return response()->json(['success' => true]);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function isValidSignature(Request $request)
    {
        $secret = (string) config('meta_whatsapp.app_secret');
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
