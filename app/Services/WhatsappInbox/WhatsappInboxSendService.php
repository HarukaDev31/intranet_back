<?php

namespace App\Services\WhatsappInbox;

use App\Models\WhatsappInbox\WaInboxMessage;
use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaInboxLog;
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
        WaInboxLog::info('sendOutbound.start', ['message_id' => (int) $messageId]);

        $message = WaInboxMessage::query()->find($messageId);
        if (!$message || $message->direction !== 'out') {
            WaInboxLog::warning('sendOutbound.message_not_found', ['message_id' => (int) $messageId]);

            return ['success' => false, 'error' => 'Mensaje no encontrado'];
        }

        $conversation = $message->conversation;
        if (!$conversation) {
            WaInboxLog::warning('sendOutbound.conversation_not_found', ['message_id' => (int) $messageId]);

            return ['success' => false, 'error' => 'Conversación no encontrada'];
        }

        $phone = $conversation->phone_e164;
        if ($phone === '') {
            WaInboxLog::warning('sendOutbound.invalid_phone', [
                'message_id' => (int) $messageId,
                'conversation_id' => (int) $conversation->id,
            ]);

            return ['success' => false, 'error' => 'Teléfono inválido'];
        }

        WaInboxLog::info('sendOutbound.dispatch', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'phone_e164' => $phone,
            'message_type' => $message->message_type,
            'template_name' => $message->template_name,
            'has_template_header' => is_array($message->template_params)
                && isset($message->template_params['_header']),
        ]);

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

            WaInboxLog::info('sendOutbound.ok', [
                'message_id' => (int) $message->id,
                'meta_message_id' => $metaId,
            ]);

            return ['success' => true];
        }

        $message->delivery_status = 'failed';
        $message->failed_reason = isset($result['error']) ? (string) $result['error'] : 'Error Meta';
        $message->save();
        $this->messageService->broadcastMessageStatusUpdated($message);

        WaInboxLog::error('sendOutbound.failed', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'phone_e164' => $phone,
            'error' => $message->failed_reason,
            'meta_response' => WaInboxLog::sanitizePayload(isset($result['response']) ? $result['response'] : null),
        ]);

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
            WaInboxLog::warning('sendText.coordinacion_disabled', ['phone_e164' => $phoneE164]);

            return ['success' => false, 'error' => 'Meta coordinación deshabilitado'];
        }

        $token = (string) config('meta_whatsapp.access_token');
        if ($token === '') {
            WaInboxLog::error('sendText.missing_token', ['phone_e164' => $phoneE164]);

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
            $json = $response->json();
            WaInboxLog::error('sendText.meta_http_error', [
                'phone_e164' => $phoneE164,
                'status' => $response->status(),
                'body' => WaInboxLog::sanitizePayload(is_array($json) ? $json : $response->body()),
            ]);

            return [
                'success' => false,
                'error' => $this->formatMetaError($response->status(), $json),
                'response' => $json,
            ];
        }

        WaInboxLog::info('sendText.meta_ok', ['phone_e164' => $phoneE164]);

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

        /** @var WhatsappInboxTemplateService $templateService */
        $templateService = app(WhatsappInboxTemplateService::class);
        $requiredHeaderFormat = $templateService->getTemplateHeaderFormat($templateName);

        WaInboxLog::info('dispatchMetaTemplate.start', [
            'phone_e164' => $phoneE164,
            'template' => $templateName,
            'required_header_format' => $requiredHeaderFormat,
            'has_header' => is_array($header),
            'header_type_before_prepare' => is_array($header) && isset($header['type']) ? $header['type'] : null,
            'body_param_count' => count($bodyParams),
            'body_param_keys' => array_map(function ($p) {
                return isset($p['parameter_name']) ? $p['parameter_name'] : (isset($p['text']) ? 'text' : '?');
            }, $bodyParams),
        ]);

        if ($requiredHeaderFormat !== null && $header === null) {
            WaInboxLog::error('dispatchMetaTemplate.missing_header', [
                'phone_e164' => $phoneE164,
                'template' => $templateName,
            ]);

            return [
                'success' => false,
                'error' => 'Falta el archivo del encabezado requerido por la plantilla',
            ];
        }

        if (is_array($header)) {
            if ($requiredHeaderFormat === 'DOCUMENT') {
                $header['type'] = 'document';
            } elseif ($requiredHeaderFormat === 'IMAGE') {
                $header['type'] = 'image';
            } elseif ($requiredHeaderFormat === 'VIDEO') {
                $header['type'] = 'video';
            }
        }

        $header = CoordinacionMediaLink::prepareHeader($header);

        if ($header === null && ($requiredHeaderFormat !== null || $this->paramsHadHeaderMedia($templateParams))) {
            WaInboxLog::error('dispatchMetaTemplate.header_prepare_failed', [
                'phone_e164' => $phoneE164,
                'template' => $templateName,
                'required_header_format' => $requiredHeaderFormat,
            ]);

            return [
                'success' => false,
                'error' => 'No se pudo preparar el archivo del encabezado para Meta (URL pública)',
            ];
        }

        WaInboxLog::info('dispatchMetaTemplate.call_meta', [
            'phone_e164' => $phoneE164,
            'template' => $templateName,
            'requires_header_component' => $requiredHeaderFormat !== null,
            'header_type' => is_array($header) && isset($header['type']) ? $header['type'] : null,
        ]);

        $result = $this->metaService->sendMetaTemplate(
            $phoneE164,
            $templateName,
            (string) config('meta_whatsapp.default_language', 'es_PE'),
            $bodyParams,
            $header,
            $requiredHeaderFormat !== null
        );

        if (!empty($result['status'])) {
            WaInboxLog::info('dispatchMetaTemplate.ok', [
                'phone_e164' => $phoneE164,
                'template' => $templateName,
                'meta_message_id' => $this->extractMetaMessageId(isset($result['response']) ? $result['response'] : null),
            ]);

            return [
                'success' => true,
                'meta_message_id' => $this->extractMetaMessageId(isset($result['response']) ? $result['response'] : null),
                'response' => isset($result['response']) ? $result['response'] : null,
            ];
        }

        WaInboxLog::error('dispatchMetaTemplate.meta_failed', [
            'phone_e164' => $phoneE164,
            'template' => $templateName,
            'error' => isset($result['error']) ? $result['error'] : null,
            'response' => WaInboxLog::sanitizePayload(isset($result['response']) ? $result['response'] : null),
        ]);

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

    /**
     * @param  int  $httpStatus
     * @param  mixed  $json
     * @return string
     */
    private function formatMetaError($httpStatus, $json)
    {
        $msg = 'Meta API HTTP ' . $httpStatus;
        if (is_array($json) && isset($json['error']['message'])) {
            $msg .= ': ' . (string) $json['error']['message'];
            if (isset($json['error']['code'])) {
                $msg .= ' (#' . $json['error']['code'] . ')';
            }
        }

        return $msg;
    }
}
