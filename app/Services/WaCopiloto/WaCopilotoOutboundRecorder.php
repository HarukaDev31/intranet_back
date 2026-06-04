<?php

namespace App\Services\WaCopiloto;

use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Support\WhatsApp\WaCopilotoMime;
use App\Support\WhatsApp\WhatsappEnvironmentPhone;

/**
 * Persiste en wa_copiloto_* envíos de plantilla Meta que no pasaron por createOutboundPending.
 * Con suppress activo (SendService / dispatchMetaTemplate) no duplica filas existentes.
 */
class WaCopilotoOutboundRecorder
{
    /** @var int */
    private static $suppressDepth = 0;

    /** @var WaCopilotoSessionService */
    protected $sessionService;

    /** @var WaCopilotoConversationService */
    protected $conversationService;

    /** @var WaCopilotoMessageService */
    protected $messageService;

    /** @var WaCopilotoTemplateService */
    protected $templateService;

    public function __construct(
        WaCopilotoSessionService $sessionService,
        WaCopilotoConversationService $conversationService,
        WaCopilotoMessageService $messageService,
        WaCopilotoTemplateService $templateService
    ) {
        $this->sessionService = $sessionService;
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
        $this->templateService = $templateService;
    }

    public static function isSuppressed()
    {
        return self::$suppressDepth > 0;
    }

    /**
     * @param  callable  $fn
     * @return mixed
     */
    public static function runWhileSuppressed($fn)
    {
        self::$suppressDepth++;

        try {
            return $fn();
        } finally {
            self::$suppressDepth = max(0, self::$suppressDepth - 1);
        }
    }

    /**
     * @param  string  $phoneE164
     * @param  string  $templateName
     * @param  array<int, array<string, mixed>>  $bodyParameters
     * @param  array<string, mixed>|null  $header
     * @param  mixed  $metaResponse
     * @param  array<string, mixed>  $context
     * @return WaCopilotoMessage|null
     */
    public function recordTemplateAfterMetaSend(
        $phoneE164,
        $templateName,
        array $bodyParameters,
        $header,
        $metaResponse,
        array $context = []
    ) {
        if (self::isSuppressed()) {
            return null;
        }

        $phoneE164 = $this->normalizePhoneE164($phoneE164);
        if ($phoneE164 === '' || $templateName === '') {
            return null;
        }

        $metaId = $this->extractMetaMessageId($metaResponse);
        if ($metaId !== null && $metaId !== '') {
            $existing = WaCopilotoMessage::query()
                ->where('meta_message_id', $metaId)
                ->first();
            if ($existing !== null) {
                WaCopilotoLog::info('outboundRecorder.skip_duplicate_meta_id', [
                    'meta_message_id' => $metaId,
                    'message_id' => (int) $existing->id,
                ]);

                return $existing;
            }
        }

        $templateParams = $this->buildTemplateParams($bodyParameters, $header);
        if (!empty($context['template_params']) && is_array($context['template_params'])) {
            $templateParams = array_merge($templateParams, $context['template_params']);
        }
        if (!empty($context['source'])) {
            $templateParams['_source'] = (string) $context['source'];
        }

        $bodyPreview = trim((string) ($context['body_preview'] ?? ''));
        if ($bodyPreview === '') {
            $bodyPreview = $this->templateService->buildPreviewBody($templateName, $templateParams);
        }
        if ($bodyPreview === '') {
            $bodyPreview = '[Plantilla: ' . $templateName . ']';
        }

        $session = $this->sessionService->ensureDefaultSession();
        $contactName = isset($context['contact_name']) ? trim((string) $context['contact_name']) : '';
        if ($contactName === '') {
            $contactName = 'Cliente ' . substr($phoneE164, -4);
        }

        $conversation = $this->conversationService->findOrCreateConversation(
            $session,
            $phoneE164,
            null,
            $contactName
        );

        $headerMedia = CoordinacionMediaLink::templateHeaderMediaForDatabase(
            is_array($header) ? $header : null
        );
        $mediaUrl = $headerMedia['media_url'];
        $mediaMime = WaCopilotoMime::normalizeForStorage($headerMedia['media_mime']);
        if (is_array($header) && !empty($header['filename'])) {
            $templateParams['_media_filename'] = (string) $header['filename'];
        }

        $userId = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $preview = $mediaUrl !== null ? '[Plantilla con archivo]' : mb_substr($bodyPreview, 0, 200);

        $message = WaCopilotoMessage::create([
            'conversation_id' => $conversation->id,
            'session_id' => $conversation->session_id,
            'direction' => 'out',
            'body' => $bodyPreview,
            'message_type' => 'template',
            'template_name' => $templateName,
            'template_params' => $templateParams,
            'media_url' => $mediaUrl,
            'media_mime' => $mediaMime,
            'meta_message_id' => $metaId,
            'delivery_status' => 'sent',
            'failed_reason' => null,
            'sent_at' => now(),
            'sent_by_user_id' => $userId > 0 ? $userId : null,
        ]);

        $this->conversationService->refreshHeaderFromMessage($conversation, $message, false);
        $this->messageService->broadcastMessageCreated($message, $conversation);

        WaCopilotoLog::info('outboundRecorder.template_recorded', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'template' => $templateName,
            'phone_e164' => $phoneE164,
            'meta_message_id' => $metaId,
            'source' => isset($context['source']) ? (string) $context['source'] : 'meta_template',
        ]);

        return $message;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bodyParameters
     * @param  array<string, mixed>|null  $header
     * @return array<string, mixed>
     */
    private function buildTemplateParams(array $bodyParameters, $header)
    {
        $params = [];
        foreach ($bodyParameters as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['parameter_name']) ? (string) $row['parameter_name'] : '';
            if ($name !== '') {
                $params[$name] = (string) ($row['text'] ?? '');
            }
        }

        if (is_array($header) && $header !== []) {
            $params['_header'] = $header;
        }

        return $params;
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
        if (isset($response['messages'][0]['id'])) {
            return (string) $response['messages'][0]['id'];
        }

        return null;
    }

    /**
     * @param  string  $phone
     * @return string
     */
    private function normalizePhoneE164($phone)
    {
        if (strpos($phone, '@') !== false) {
            $phone = (string) WhatsappEnvironmentPhone::resolve($phone);
        }

        return $this->conversationService->normalizePhoneE164($phone);
    }
}
