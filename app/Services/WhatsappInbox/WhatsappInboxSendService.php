<?php

namespace App\Services\WhatsappInbox;

use App\Models\WhatsappInbox\WaInboxMessage;
use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use App\Support\WhatsApp\CoordinacionMediaLink;
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
     * Envío directo a Meta (sin fila previa en BD). Usado para plantillas con archivo en encabezado.
     *
     * @param  string  $phoneE164
     * @param  string  $templateName
     * @param  array<string, mixed>  $templateParams
     * @return array{success: bool, meta_message_id?: string|null, error?: string, response?: mixed}
     */
    public function dispatchMetaTemplate($phoneE164, $templateName, array $templateParams)
    {
        $params = $templateParams;
        $header = isset($params['_header']) && is_array($params['_header']) ? $params['_header'] : null;
        unset($params['_header']);
        $bodyParams = $this->metaService->normalizeBodyParameters($params);
        $header = CoordinacionMediaLink::prepareHeader($header);

        if ($header === null && $this->paramsHadHeaderMedia($templateParams)) {
            return [
                'success' => false,
                'error' => 'No se pudo preparar el archivo del encabezado para Meta',
            ];
        }

        $result = $this->metaService->sendMetaTemplate(
            $phoneE164,
            $templateName,
            (string) config('meta_whatsapp.default_language', 'es_PE'),
            $bodyParams,
            $header
        );

        if (!empty($result['status'])) {
            return [
                'success' => true,
                'meta_message_id' => $this->extractMetaMessageId(isset($result['response']) ? $result['response'] : null),
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
     * @param  array<string, mixed>  $templateParams
     * @return bool
     */
    private function paramsHadHeaderMedia(array $templateParams)
    {
        return isset($templateParams['_header']) && is_array($templateParams['_header']);
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

        $result = $this->dispatchMetaTemplate($phoneE164, $templateName, $params);

        if (!empty($result['success'])) {
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
