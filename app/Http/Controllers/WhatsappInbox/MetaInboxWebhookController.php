<?php

namespace App\Http\Controllers\WhatsappInbox;

use App\Http\Controllers\Controller;
use App\Services\WhatsappInbox\WhatsappInboxOrgConfigService;
use App\Support\WhatsApp\MetaWhatsappWebhookRouter;
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

        $configService = app(WhatsappInboxOrgConfigService::class);
        if ($mode === 'subscribe' && $configService->matchesVerifyToken($token)) {
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
        MetaWhatsappWebhookRouter::dispatch($payload, WaInboxJobContext::resolveJobDomain());

        return response()->json(['success' => true]);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function isValidSignature(Request $request)
    {
        $configService = app(WhatsappInboxOrgConfigService::class);

        return $configService->isValidWebhookSignature(
            $request->getContent(),
            (string) $request->header('X-Hub-Signature-256')
        );
    }
}
