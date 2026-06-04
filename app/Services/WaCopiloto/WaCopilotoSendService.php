<?php

namespace App\Services\WaCopiloto;

use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Services\WhatsApp\MetaWhatsAppCoordinacionService;
use App\Services\WaCopiloto\WaCopilotoOutboundRecorder;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Support\WhatsApp\WaCopilotoMetaError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaCopilotoSendService
{
    /** @var MetaWhatsAppCoordinacionService */
    protected $metaService;

    /** @var WaCopilotoMessageService */
    protected $messageService;

    public function __construct(
        MetaWhatsAppCoordinacionService $metaService,
        WaCopilotoMessageService $messageService
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
        WaCopilotoLog::info('sendOutbound.start', ['message_id' => (int) $messageId]);

        $message = WaCopilotoMessage::query()->find($messageId);
        if (!$message || $message->direction !== 'out') {
            WaCopilotoLog::warning('sendOutbound.message_not_found', ['message_id' => (int) $messageId]);

            return ['success' => false, 'error' => 'Mensaje no encontrado'];
        }

        $conversation = $message->conversation;
        if (!$conversation) {
            WaCopilotoLog::warning('sendOutbound.conversation_not_found', ['message_id' => (int) $messageId]);

            return ['success' => false, 'error' => 'Conversación no encontrada'];
        }

        $phone = $conversation->phone_e164;
        if ($phone === '') {
            WaCopilotoLog::warning('sendOutbound.invalid_phone', [
                'message_id' => (int) $messageId,
                'conversation_id' => (int) $conversation->id,
            ]);

            return ['success' => false, 'error' => 'Teléfono inválido'];
        }

        $phoneNumberId = $this->resolvePhoneNumberId($message);

        WaCopilotoLog::info('sendOutbound.dispatch', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'phone_e164' => $phone,
            'message_type' => $message->message_type,
            'template_name' => $message->template_name,
            'has_template_header' => is_array($message->template_params)
                && isset($message->template_params['_header']),
        ]);

        if ($message->message_type === 'template') {
            $result = $this->sendTemplateMessage($message, $phone, $phoneNumberId);
        } elseif (in_array($message->message_type, ['image', 'video', 'document', 'audio'], true)) {
            $result = $this->sendMediaMessage($message, $phone, $phoneNumberId);
        } else {
            $contextId = $this->extractReplyContextId($message);
            $result = $this->sendTextMessage($phone, (string) $message->body, $contextId, $phoneNumberId);
        }

        if (!empty($result['success'])) {
            $metaId = $this->extractMetaMessageId($result['response'] ?? null);
            $message->meta_message_id = $metaId;
            $message->delivery_status = 'sent';
            $message->failed_reason = null;
            $message->save();
            $this->messageService->broadcastMessageStatusUpdated($message);

            WaCopilotoLog::info('sendOutbound.ok', [
                'message_id' => (int) $message->id,
                'meta_message_id' => $metaId,
            ]);

            return ['success' => true];
        }

        $message->delivery_status = 'failed';
        $message->failed_reason = isset($result['error']) ? (string) $result['error'] : 'Error Meta';
        $message->save();
        $this->messageService->broadcastMessageStatusUpdated($message);

        WaCopilotoLog::error('sendOutbound.failed', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'phone_e164' => $phone,
            'error' => $message->failed_reason,
            'meta_response' => WaCopilotoLog::sanitizePayload(isset($result['response']) ? $result['response'] : null),
        ]);

        return ['success' => false, 'error' => $message->failed_reason];
    }

    /**
     * @param  string  $phoneE164
     * @param  string  $text
     * @return array{success: bool, response?: mixed, error?: string}
     */
    /**
     * @param  WaCopilotoMessage  $message
     * @return string
     */
    private function resolvePhoneNumberId(WaCopilotoMessage $message)
    {
        $session = $message->session;
        if ($session && trim((string) $session->phone_number_id) !== '') {
            return (string) $session->phone_number_id;
        }

        return (string) config('meta_whatsapp_copiloto.phone_number_id');
    }

    /**
     * @param  string  $phoneE164
     * @param  string  $text
     * @param  string|null  $replyToMetaMessageId
     * @param  string|null  $phoneNumberId
     * @return array{success: bool, response?: mixed, error?: string}
     */
    private function sendTextMessage($phoneE164, $text, $replyToMetaMessageId = null, $phoneNumberId = null)
    {
        if (!config('meta_whatsapp_copiloto.enabled')) {
            WaCopilotoLog::warning('sendText.copiloto_disabled', ['phone_e164' => $phoneE164]);

            return ['success' => false, 'error' => 'Meta Copiloto deshabilitado'];
        }

        $token = (string) config('meta_whatsapp_copiloto.access_token');
        if ($token === '') {
            WaCopilotoLog::error('sendText.missing_token', ['phone_e164' => $phoneE164]);

            return ['success' => false, 'error' => 'META_WHATSAPP_COPILOTO_ACCESS_TOKEN no configurado'];
        }

        $phoneNumberId = trim((string) ($phoneNumberId ?: config('meta_whatsapp_copiloto.phone_number_id')));
        $version = (string) config('meta_whatsapp_copiloto.graph_api_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneE164,
            'type' => 'text',
            'text' => ['body' => $text],
        ];
        if ($replyToMetaMessageId !== null && $replyToMetaMessageId !== '') {
            $payload['context'] = ['message_id' => $replyToMetaMessageId];
        }

        $response = Http::timeout(60)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (!$response->successful()) {
            $json = $response->json();
            WaCopilotoLog::error('sendText.meta_http_error', [
                'phone_e164' => $phoneE164,
                'status' => $response->status(),
                'body' => WaCopilotoLog::sanitizePayload(is_array($json) ? $json : $response->body()),
            ]);

            return [
                'success' => false,
                'error' => $this->formatMetaError($response->status(), $json),
                'response' => $json,
            ];
        }

        WaCopilotoLog::info('sendText.meta_ok', ['phone_e164' => $phoneE164]);

        return [
            'success' => true,
            'response' => $response->json(),
        ];
    }

    /**
     * @param  WaCopilotoMessage  $message
     * @param  string  $phoneE164
     * @return array{success: bool, response?: mixed, error?: string}
     */
    private function sendMediaMessage(WaCopilotoMessage $message, $phoneE164, $phoneNumberId = null)
    {
        $link = CoordinacionMediaLink::urlForMetaSend($message->media_url);
        if ($link === null || $link === '') {
            return ['success' => false, 'error' => 'URL de media no disponible'];
        }

        $type = (string) $message->message_type;
        $caption = trim((string) $message->body);
        $filename = $this->extractMediaFilename($message);
        $contextId = $this->extractReplyContextId($message);

        if (!config('meta_whatsapp_copiloto.enabled')) {
            return ['success' => false, 'error' => 'Meta Copiloto deshabilitado'];
        }

        $token = (string) config('meta_whatsapp_copiloto.access_token');
        if ($token === '') {
            return ['success' => false, 'error' => 'META_WHATSAPP_COPILOTO_ACCESS_TOKEN no configurado'];
        }

        $phoneNumberId = trim((string) ($phoneNumberId ?: $this->resolvePhoneNumberId($message)));
        $version = (string) config('meta_whatsapp_copiloto.graph_api_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneE164,
            'type' => $type,
        ];
        if ($contextId !== null && $contextId !== '') {
            $payload['context'] = ['message_id' => $contextId];
        }

        if ($type === 'image') {
            $media = ['link' => $link];
            if ($caption !== '') {
                $media['caption'] = $caption;
            }
            $payload['image'] = $media;
        } elseif ($type === 'video') {
            $media = ['link' => $link];
            if ($caption !== '') {
                $media['caption'] = $caption;
            }
            $payload['video'] = $media;
        } elseif ($type === 'document') {
            $media = [
                'link' => $link,
                'filename' => $filename !== '' ? $filename : 'documento',
            ];
            if ($caption !== '') {
                $media['caption'] = $caption;
            }
            $payload['document'] = $media;
        } elseif ($type === 'audio') {
            $payload['audio'] = ['link' => $link];
        } else {
            return ['success' => false, 'error' => 'Tipo de media no soportado'];
        }

        $response = Http::timeout(120)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (!$response->successful()) {
            $json = $response->json();
            WaCopilotoLog::error('sendMedia.meta_http_error', [
                'phone_e164' => $phoneE164,
                'type' => $type,
                'status' => $response->status(),
                'body' => WaCopilotoLog::sanitizePayload(is_array($json) ? $json : $response->body()),
            ]);

            return [
                'success' => false,
                'error' => $this->formatMetaError($response->status(), $json),
                'response' => $json,
            ];
        }

        WaCopilotoLog::info('sendMedia.meta_ok', ['phone_e164' => $phoneE164, 'type' => $type]);

        return [
            'success' => true,
            'response' => $response->json(),
        ];
    }

    /**
     * @param  WaCopilotoMessage  $message
     * @return string|null
     */
    private function extractReplyContextId(WaCopilotoMessage $message)
    {
        $params = is_array($message->template_params) ? $message->template_params : [];
        if (isset($params['_context']['message_id'])) {
            $id = trim((string) $params['_context']['message_id']);

            return $id !== '' ? $id : null;
        }

        return null;
    }

    /**
     * @param  WaCopilotoMessage  $message
     * @return string
     */
    private function extractMediaFilename(WaCopilotoMessage $message)
    {
        $params = is_array($message->template_params) ? $message->template_params : [];
        if (isset($params['_media_filename'])) {
            return trim((string) $params['_media_filename']);
        }
        if (isset($params['_header']['filename'])) {
            return trim((string) $params['_header']['filename']);
        }

        return '';
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

        /** @var WaCopilotoTemplateService $templateService */
        $templateService = app(WaCopilotoTemplateService::class);
        $requiredHeaderFormat = $templateService->getTemplateHeaderFormat($templateName);

        WaCopilotoLog::info('dispatchMetaTemplate.start', [
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
            WaCopilotoLog::error('dispatchMetaTemplate.missing_header', [
                'phone_e164' => $phoneE164,
                'template' => $templateName,
            ]);

            return [
                'success' => false,
                'error' => 'Falta el archivo del encabezado requerido por la plantilla',
            ];
        }

        if ($requiredHeaderFormat === null) {
            $header = null;
        } elseif (is_array($header)) {
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
            WaCopilotoLog::error('dispatchMetaTemplate.header_prepare_failed', [
                'phone_e164' => $phoneE164,
                'template' => $templateName,
                'required_header_format' => $requiredHeaderFormat,
            ]);

            return [
                'success' => false,
                'error' => 'No se pudo preparar el archivo del encabezado para Meta (URL pública)',
            ];
        }

        WaCopilotoLog::info('dispatchMetaTemplate.call_meta', [
            'phone_e164' => $phoneE164,
            'template' => $templateName,
            'requires_header_component' => $requiredHeaderFormat !== null,
            'header_type' => is_array($header) && isset($header['type']) ? $header['type'] : null,
        ]);

        $result = WaCopilotoOutboundRecorder::runWhileSuppressed(function () use (
            $phoneE164,
            $templateName,
            $bodyParams,
            $header,
            $requiredHeaderFormat
        ) {
            return $this->metaService->sendMetaTemplate(
                $phoneE164,
                $templateName,
                (string) config('meta_whatsapp_copiloto.default_language', 'es_PE'),
                $bodyParams,
                $header,
                $requiredHeaderFormat !== null
            );
        });

        if (!empty($result['status'])) {
            WaCopilotoLog::info('dispatchMetaTemplate.ok', [
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

        WaCopilotoLog::error('dispatchMetaTemplate.meta_failed', [
            'phone_e164' => $phoneE164,
            'template' => $templateName,
            'error' => isset($result['error']) ? $result['error'] : null,
            'response' => WaCopilotoLog::sanitizePayload(isset($result['response']) ? $result['response'] : null),
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
     * @param  WaCopilotoMessage  $message
     * @param  string  $phoneE164
     * @return array{success: bool, response?: mixed, error?: string}
     */
    private function sendTemplateMessage(WaCopilotoMessage $message, $phoneE164)
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
        return WaCopilotoMetaError::userMessage($httpStatus, $json);
    }
}
