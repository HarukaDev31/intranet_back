<?php

namespace App\Services\WhatsappInbox;

use App\Models\WhatsappInbox\WaInboxMessage;
use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappInboxSendService
{
    /** @var MetaWhatsAppCoordinacionService */
    protected $metaService;

    /** @var WhatsappInboxMessageService */
    protected $messageService;

    public function __construct(
        MetaWhatsAppCoordinacionService $metaService,
        WhatsappInboxMessageService $messageService
    ) {
        $this->metaService = $metaService;
        $this->messageService = $messageService;
    }

    /**
     * @param  int  $messageId
     * @return array{success: bool, error?: string}
     */
    public function sendOutboundMessage($messageId)
    {
        $message = WaInboxMessage::query()->find($messageId);
        if (!$message || $message->direction !== 'out') {
            return ['success' => false, 'error' => 'Mensaje no encontrado'];
        }

        $conversation = $message->conversation;
        if (!$conversation) {
            return ['success' => false, 'error' => 'Conversación no encontrada'];
        }

        $phone = $conversation->phone_e164;
        if ($phone === '') {
            return ['success' => false, 'error' => 'Teléfono inválido'];
        }

        if ($message->message_type === 'template') {
            $result = $this->sendTemplateMessage($message, $phone);
        } else {
            $result = $this->sendTextMessage($phone, (string) $message->body);
        }

        if (!empty($result['success'])) {
            $metaId = $this->extractMetaMessageId($result['response'] ?? null);
            $message->meta_message_id = $metaId;
            $message->delivery_status = 'sent';
            $message->failed_reason = null;
            $message->save();
            $this->messageService->broadcastMessageStatusUpdated($message);

            return ['success' => true];
        }

        $message->delivery_status = 'failed';
        $message->failed_reason = isset($result['error']) ? (string) $result['error'] : 'Error Meta';
        $message->save();
        $this->messageService->broadcastMessageStatusUpdated($message);

        return ['success' => false, 'error' => $message->failed_reason];
    }

    /**
     * @param  string  $phoneE164
     * @param  string  $text
     * @return array{success: bool, response?: mixed, error?: string}
     */
    private function sendTextMessage($phoneE164, $text)
    {
        if (!config('meta_whatsapp.coordinacion_enabled')) {
            return ['success' => false, 'error' => 'Meta coordinación deshabilitado'];
        }

        $token = (string) config('meta_whatsapp.access_token');
        if ($token === '') {
            return ['success' => false, 'error' => 'META_WHATSAPP_ACCESS_TOKEN no configurado'];
        }

        $phoneNumberId = (string) config('meta_whatsapp.phone_number_id');
        $version = (string) config('meta_whatsapp.graph_api_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $response = Http::timeout(60)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $phoneE164,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);

        if (!$response->successful()) {
            Log::error('WhatsappInboxSend: error texto Meta', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Meta API HTTP ' . $response->status(),
                'response' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'response' => $response->json(),
        ];
    }

    /**
     * @param  WaInboxMessage  $message
     * @param  string  $phoneE164
     * @return array{success: bool, response?: mixed, error?: string}
     */
    private function sendTemplateMessage(WaInboxMessage $message, $phoneE164)
    {
        $templateName = (string) $message->template_name;
        $params = is_array($message->template_params) ? $message->template_params : [];
        $bodyParams = $this->metaService->normalizeBodyParameters($params);

        $result = $this->metaService->sendMetaTemplate(
            $phoneE164,
            $templateName,
            (string) config('meta_whatsapp.default_language', 'es_PE'),
            $bodyParams,
            null
        );

        if (!empty($result['status'])) {
            return [
                'success' => true,
                'response' => isset($result['response']) ? $result['response'] : null,
            ];
        }

        return [
            'success' => false,
            'error' => isset($result['error']) ? (string) $result['error'] : 'Error plantilla Meta',
            'response' => isset($result['response']) ? $result['response'] : null,
        ];
    }

    /**
     * @param  mixed  $response
     * @return string|null
     */
    private function extractMetaMessageId($response)
    {
        if (!is_array($response)) {
            return null;
        }

        $messages = isset($response['messages']) && is_array($response['messages'])
            ? $response['messages']
            : [];

        if (isset($messages[0]['id'])) {
            return (string) $messages[0]['id'];
        }

        return null;
    }
}
