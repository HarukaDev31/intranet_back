<?php

namespace App\Services\WhatsappInbox;

use App\Jobs\WhatsappInbox\SendWaInboxOutboundJob;
use App\Models\WhatsappInbox\WaInboxMessage;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Support\WhatsApp\WaInboxJobContext;
use App\Support\WhatsApp\WaInboxLog;
use App\Support\WhatsApp\WhatsappEnvironmentPhone;
use Illuminate\Support\Facades\Log;

/**
 * Envíos programáticos de coordinación (plantillas y legacy) → wa_inbox_* + cola Meta.
 * Envíos programáticos → wa_inbox_* + cola Meta (historial en el inbox).
 */
class WhatsappInboxCoordinacionOutboundService
{
    /** @var WhatsappInboxSessionService */
    protected $sessionService;

    /** @var WhatsappInboxConversationService */
    protected $conversationService;

    /** @var WhatsappInboxMessageService */
    protected $messageService;

    /** @var WhatsappInboxTemplateService */
    protected $templateService;

    public function __construct(
        WhatsappInboxSessionService $sessionService,
        WhatsappInboxConversationService $conversationService,
        WhatsappInboxMessageService $messageService,
        WhatsappInboxTemplateService $templateService
    ) {
        $this->sessionService = $sessionService;
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
        $this->templateService = $templateService;
    }

    /**
     * Misma firma que MetaWhatsAppCoordinacionService::process para SendCoordinacionWhatsAppJob.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, error?: string, response?: mixed, inbox_message_id?: int}
     */
    public function process(array $payload)
    {
        $type = (string) ($payload['type'] ?? 'template');

        if ($type === 'legacy_message') {
            return $this->processLegacyMessage($payload);
        }

        if ($type === 'legacy_media') {
            return $this->processLegacyMedia($payload);
        }

        return $this->processTemplate($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, error?: string, inbox_message_id?: int}
     */
    private function processTemplate(array $payload)
    {
        if (!config('meta_whatsapp.coordinacion_enabled')) {
            return ['status' => false, 'error' => 'Meta coordinación deshabilitado'];
        }

        $phone = $this->normalizePhoneE164((string) ($payload['phone'] ?? ''));
        if ($phone === '') {
            return ['status' => false, 'error' => 'Teléfono inválido'];
        }

        $templateName = (string) ($payload['template'] ?? '');
        if ($templateName === '') {
            return ['status' => false, 'error' => 'template requerido'];
        }

        if (!CoordinacionWhatsappPayload::refreshDocsExcelLinkPayload($payload)) {
            return ['status' => false, 'error' => 'Sin enlace Google Drive válido para Excel de confirmación'];
        }

        $rawHeader = isset($payload['header']) && is_array($payload['header']) ? $payload['header'] : null;
        $header = CoordinacionMediaLink::prepareHeader($rawHeader);
        if ($rawHeader !== null && !empty($rawHeader['type']) && $header === null) {
            WaInboxLog::error('inboxCoordinacion.template_header_upload_failed', [
                'template' => $templateName,
                'path' => $rawHeader['path'] ?? null,
            ]);

            return ['status' => false, 'error' => 'No se pudo subir el archivo del encabezado a S3'];
        }

        $templateParams = $this->buildTemplateParams($payload, $header);
        $fallbackPreview = trim((string) ($payload['chat_preview'] ?? $payload['bitrix_message'] ?? ''));
        $body = $this->templateService->resolvePreviewText(
            $templateName,
            $templateParams,
            $fallbackPreview !== '' ? $fallbackPreview : null
        );
        if (trim($body) === '') {
            return ['status' => false, 'error' => 'No se pudo resolver el texto de la plantilla para el inbox'];
        }

        $session = $this->sessionService->ensureDefaultSession();
        $contactName = $this->resolveContactName($payload, $phone);
        $conversation = $this->conversationService->findOrCreateConversation(
            $session,
            $phone,
            null,
            $contactName
        );

        $userId = isset($payload['sent_by_user_id']) ? (int) $payload['sent_by_user_id'] : null;
        $requiresHeader = $this->templateService->templateRequiresHeaderMedia($templateName)
            || $header !== null;

        WaInboxLog::info('inboxCoordinacion.template', [
            'conversation_id' => (int) $conversation->id,
            'template' => $templateName,
            'phone_e164' => $phone,
            'requires_header' => $requiresHeader,
        ]);

        if ($requiresHeader && $header !== null) {
            $sync = $this->messageService->sendTemplateWithHeaderSync(
                $conversation,
                $body,
                $userId,
                $templateName,
                $templateParams
            );

            if (empty($sync['success'])) {
                return [
                    'status' => false,
                    'error' => isset($sync['error']) ? (string) $sync['error'] : 'Error al enviar plantilla',
                ];
            }

            /** @var WaInboxMessage $message */
            $message = $sync['message'];

            return [
                'status' => true,
                'inbox_message_id' => (int) $message->id,
            ];
        }

        $message = $this->messageService->createOutboundPending(
            $conversation,
            $body,
            $userId,
            'template',
            $templateName,
            $templateParams
        );

        $this->dispatchOutboundJob($message->id, $payload);

        return [
            'status' => true,
            'inbox_message_id' => (int) $message->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, error?: string, inbox_message_id?: int}
     */
    private function processLegacyMessage(array $payload)
    {
        if (!config('meta_whatsapp.coordinacion_enabled')) {
            return ['status' => false, 'error' => 'Meta coordinación deshabilitado'];
        }

        $phone = $this->normalizePhoneE164((string) ($payload['phone'] ?? ''));
        $text = trim((string) ($payload['message'] ?? ''));
        if ($phone === '' || $text === '') {
            return ['status' => false, 'error' => 'Teléfono o mensaje vacío'];
        }

        $session = $this->sessionService->ensureDefaultSession();
        $conversation = $this->conversationService->findOrCreateConversation(
            $session,
            $phone,
            null,
            $this->resolveContactName($payload, $phone)
        );

        $message = $this->messageService->createOutboundPending(
            $conversation,
            $text,
            null,
            'text'
        );

        $this->dispatchOutboundJob($message->id, $payload);

        return ['status' => true, 'inbox_message_id' => (int) $message->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, error?: string, inbox_message_id?: int}
     */
    private function processLegacyMedia(array $payload)
    {
        if (!config('meta_whatsapp.coordinacion_enabled')) {
            return ['status' => false, 'error' => 'Meta coordinación deshabilitado'];
        }

        $phone = $this->normalizePhoneE164((string) ($payload['phone'] ?? ''));
        $path = (string) ($payload['path'] ?? '');
        if ($phone === '' || $path === '' || !is_readable($path)) {
            return ['status' => false, 'error' => 'Teléfono inválido o archivo no legible'];
        }

        $mime = (string) ($payload['mimeType'] ?? '');
        $kind = strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : 'document');
        $storageKey = CoordinacionMediaLink::META_TEMP_PREFIX . '/inbox/legacy/'
            . date('Y/m/d') . '/' . basename($path);

        $url = CoordinacionMediaLink::uploadLocalFile($path, $storageKey);
        if ($url === null || $url === '') {
            return ['status' => false, 'error' => 'No se pudo subir el archivo a S3'];
        }

        $session = $this->sessionService->ensureDefaultSession();
        $conversation = $this->conversationService->findOrCreateConversation(
            $session,
            $phone,
            null,
            $this->resolveContactName($payload, $phone)
        );

        $caption = trim((string) ($payload['caption'] ?? ''));
        $filename = (string) ($payload['fileName'] ?? basename($path));
        $templateParams = ['_media_filename' => $filename];

        $message = $this->messageService->createOutboundPending(
            $conversation,
            $caption !== '' ? $caption : $filename,
            null,
            $kind,
            null,
            $templateParams,
            $storageKey,
            $mime !== '' ? $mime : null
        );

        $this->dispatchOutboundJob($message->id, $payload);

        return ['status' => true, 'inbox_message_id' => (int) $message->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $header
     * @return array<string, mixed>
     */
    private function buildTemplateParams(array $payload, $header)
    {
        $params = [];
        $bodyParams = isset($payload['body_parameters']) && is_array($payload['body_parameters'])
            ? $payload['body_parameters']
            : [];

        $isList = array_keys($bodyParams) === range(0, count($bodyParams) - 1);
        if ($isList) {
            foreach ($bodyParams as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = isset($row['parameter_name']) ? (string) $row['parameter_name'] : '';
                if ($name !== '') {
                    $params[$name] = (string) ($row['text'] ?? '');
                }
            }
        } else {
            foreach ($bodyParams as $name => $text) {
                $params[(string) $name] = (string) $text;
            }
        }

        if ($header !== null) {
            $params['_header'] = $header;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $phoneE164
     * @return string|null
     */
    private function resolveContactName(array $payload, $phoneE164)
    {
        $name = trim((string) ($payload['contact_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $preview = trim((string) ($payload['chat_preview'] ?? $payload['bitrix_message'] ?? ''));
        if ($preview !== '' && preg_match('/Hola\s+([^,!\n]+)/iu', $preview, $m)) {
            $guess = trim($m[1]);
            if (mb_strlen($guess) >= 2 && mb_strlen($guess) <= 80) {
                return $guess;
            }
        }

        return 'Cliente ' . substr($phoneE164, -4);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchOutboundJob($messageId, array $payload)
    {
        $coordBatchId = isset($payload['_coordinacion_batch_id'])
            ? (int) $payload['_coordinacion_batch_id']
            : 0;

        if ($coordBatchId > 0) {
            WaInboxLog::info('inboxCoordinacion.outbound_deferred_to_batch', [
                'message_id' => (int) $messageId,
                'coordinacion_batch_id' => $coordBatchId,
            ]);

            return;
        }

        $domain = isset($payload['_domain']) ? (string) $payload['_domain'] : null;
        SendWaInboxOutboundJob::dispatch(
            (int) $messageId,
            WaInboxJobContext::resolveJobDomain($domain)
        );
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
