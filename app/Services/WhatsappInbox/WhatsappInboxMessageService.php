<?php

namespace App\Services\WhatsappInbox;

use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaInboxMime;
use App\Events\WhatsappInbox\WaInboxMessageCreated;
use App\Events\WhatsappInbox\WaInboxMessageStatusUpdated;
use Illuminate\Broadcasting\BroadcastException;
use App\Models\WhatsappInbox\WaInboxConversation;
use App\Models\WhatsappInbox\WaInboxMessage;
use App\Models\WhatsappInbox\WaInboxWebhookLog;
use App\Support\WhatsApp\WaInboxLog;
use Carbon\Carbon;

class WhatsappInboxMessageService
{
    /** @var array<string, int> */
    private static $deliveryStatusRank = [
        'pending' => 0,
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
    ];

    /** @var WhatsappInboxSessionService */
    protected $sessionService;

    /** @var WhatsappInboxConversationService */
    protected $conversationService;

    public function __construct(
        WhatsappInboxSessionService $sessionService,
        WhatsappInboxConversationService $conversationService
    ) {
        $this->sessionService = $sessionService;
        $this->conversationService = $conversationService;
    }

    /**
     * @param  int  $conversationId
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function listMessages($conversationId, array $params = [])
    {
        $perPage = max(1, min(200, (int) ($params['per_page'] ?? 100)));
        $conversation = WaInboxConversation::query()->findOrFail($conversationId);

        $messages = WaInboxMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->paginate($perPage);

        $rows = [];
        foreach ($messages->items() as $msg) {
            $rows[] = $this->formatMessage($msg);
        }

        return [
            'success' => true,
            'data' => $rows,
            'conversation' => $this->conversationService->formatConversation($conversation),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ];
    }

    /**
     * @param  WaInboxMessage  $message
     * @return array<string, mixed>
     */
    public function formatMessage(WaInboxMessage $message)
    {
        $isTemplate = $message->message_type === 'template' || !empty($message->template_name);
        $params = is_array($message->template_params) ? $message->template_params : [];
        $mediaFilename = '';
        if (isset($params['_media_filename'])) {
            $mediaFilename = trim((string) $params['_media_filename']);
        } elseif (isset($params['_header']['filename'])) {
            $mediaFilename = trim((string) $params['_header']['filename']);
        }
        $replyToMetaId = null;
        if (isset($params['_context']['message_id'])) {
            $rid = trim((string) $params['_context']['message_id']);
            $replyToMetaId = $rid !== '' ? $rid : null;
        }

        $reactionInboundEmoji = null;
        if (isset($params['_reaction_in']['emoji'])) {
            $emoji = trim((string) $params['_reaction_in']['emoji']);
            $reactionInboundEmoji = $emoji !== '' ? $emoji : null;
        }

        $mediaSizeBytes = null;
        if (isset($params['_media_size_bytes'])) {
            $mediaSizeBytes = (int) $params['_media_size_bytes'];
            if ($mediaSizeBytes <= 0) {
                $mediaSizeBytes = null;
            }
        }

        return [
            'id' => (int) $message->id,
            'direction' => $message->direction,
            'body' => $message->body,
            'sent_at' => $message->sent_at,
            'time_label' => $message->sent_at ? Carbon::parse($message->sent_at)->format('H:i') : '',
            'delivery_status' => $message->delivery_status,
            'failed_reason' => $message->failed_reason,
            'is_template' => $isTemplate,
            'template_name' => $message->template_name,
            'template_params' => $this->formatResendableTemplateParams($params),
            'message_type' => $message->message_type,
            'meta_message_id' => $message->meta_message_id,
            'media_url' => CoordinacionMediaLink::urlForDisplay($message->media_url),
            'media_mime' => $message->media_mime,
            'media_filename' => $mediaFilename !== '' ? $mediaFilename : null,
            'media_size_bytes' => $mediaSizeBytes,
            'reply_to_meta_message_id' => $replyToMetaId,
            'reaction_inbound_emoji' => $reactionInboundEmoji,
        ];
    }

    /**
     * @param  int  $webhookLogId
     */
    public function processWebhookLog($webhookLogId)
    {
        $log = WaInboxWebhookLog::query()->find($webhookLogId);
        if (!$log || !is_array($log->payload)) {
            return;
        }

        $payload = $log->payload;
        $entries = isset($payload['entry']) && is_array($payload['entry']) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = isset($change['value']) && is_array($change['value']) ? $change['value'] : [];
                $phoneNumberId = isset($value['metadata']['phone_number_id'])
                    ? (string) $value['metadata']['phone_number_id']
                    : '';

                $session = $phoneNumberId !== ''
                    ? $this->sessionService->findByPhoneNumberId($phoneNumberId)
                    : $this->sessionService->ensureDefaultSession();

                if (!$session) {
                    continue;
                }

                $session->last_webhook_at = now();
                $session->save();

                $contacts = [];
                if (isset($value['contacts']) && is_array($value['contacts'])) {
                    foreach ($value['contacts'] as $c) {
                        $waId = isset($c['wa_id']) ? (string) $c['wa_id'] : '';
                        if ($waId !== '') {
                            $contacts[$waId] = isset($c['profile']['name']) ? (string) $c['profile']['name'] : '';
                        }
                    }
                }

                if (isset($value['messages']) && is_array($value['messages'])) {
                    foreach ($value['messages'] as $msg) {
                        $this->persistInboundMessage($session, $value, $msg, $contacts);
                    }
                }

                if (isset($value['statuses']) && is_array($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        $this->updateDeliveryStatus($status);
                    }
                }
            }
        }

        $log->processed_at = now();
        $log->save();
    }

    /**
     * @param  mixed  $session
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $msg
     * @param  array<string, string>  $contacts
     */
    private function persistInboundMessage($session, array $value, array $msg, array $contacts)
    {
        $from = isset($msg['from']) ? (string) $msg['from'] : '';
        if ($from === '') {
            return;
        }

        $type = isset($msg['type']) ? (string) $msg['type'] : 'text';

        if ($type === 'reaction') {
            $this->persistInboundReaction($msg);

            return;
        }

        $phoneE164 = $this->conversationService->normalizePhoneE164($from);
        $waId = $from;
        $contactName = isset($contacts[$waId]) ? $contacts[$waId] : null;

        $conversation = $this->conversationService->findOrCreateConversation(
            $session,
            $phoneE164,
            $waId,
            $contactName
        );

        $metaId = isset($msg['id']) ? (string) $msg['id'] : '';
        if ($metaId !== '' && WaInboxMessage::query()->where('meta_message_id', $metaId)->exists()) {
            return;
        }

        $messageType = $type;
        $body = '';
        $mediaUrl = null;
        $mediaMime = null;
        $templateParams = null;

        if ($type === 'text' && isset($msg['text']['body'])) {
            $body = (string) $msg['text']['body'];
        } elseif ($type === 'button' && isset($msg['button']['text'])) {
            $body = (string) $msg['button']['text'];
        } elseif ($type === 'interactive') {
            $body = '[Mensaje interactivo]';
        } elseif (in_array($type, ['image', 'video', 'document', 'audio', 'sticker'], true)) {
            /** @var WaInboxInboundMediaService $inboundMedia */
            $inboundMedia = app(WaInboxInboundMediaService::class);
            $stored = $inboundMedia->downloadAndStore($msg, (int) $conversation->id);
            if (is_array($stored)) {
                $mediaUrl = (string) $stored['path'];
                $mediaMime = isset($stored['mime']) ? $stored['mime'] : null;
                $messageType = isset($stored['message_type']) ? (string) $stored['message_type'] : $messageType;
                $templateParams = isset($stored['template_params']) && is_array($stored['template_params'])
                    ? $stored['template_params']
                    : null;
                if (!empty($stored['body'])) {
                    $body = (string) $stored['body'];
                } elseif (in_array($messageType, ['image', 'video', 'audio'], true)) {
                    $body = '';
                } elseif ($messageType === 'document') {
                    $body = isset($stored['filename']) ? (string) $stored['filename'] : '';
                } else {
                    $body = '[' . $messageType . ']';
                }
            } else {
                $body = in_array($messageType, ['image', 'video', 'audio'], true) ? '' : '[' . $messageType . ']';
            }
        } else {
            $body = '[' . $type . ']';
        }

        $timestamp = isset($msg['timestamp']) ? (int) $msg['timestamp'] : time();
        $sentAt = Carbon::createFromTimestamp($timestamp);

        $contextParams = $this->extractInboundContextParams($msg);
        if ($contextParams !== null) {
            $templateParams = is_array($templateParams)
                ? array_merge($templateParams, $contextParams)
                : $contextParams;
        }

        $message = WaInboxMessage::create([
            'conversation_id' => $conversation->id,
            'session_id' => $session->id,
            'direction' => 'in',
            'body' => $body,
            'message_type' => $messageType,
            'template_params' => $templateParams,
            'media_url' => $mediaUrl,
            'media_mime' => $mediaMime,
            'meta_message_id' => $metaId !== '' ? $metaId : null,
            'delivery_status' => 'delivered',
            'sent_at' => $sentAt,
        ]);

        $this->conversationService->refreshHeaderFromMessage($conversation, $message, true);
        $this->broadcastMessageCreated($message, $conversation);
    }

    /**
     * Reacción entrante: se guarda en el mensaje objetivo, no como fila aparte.
     *
     * @param  array<string, mixed>  $msg
     */
    private function persistInboundReaction(array $msg)
    {
        $reaction = isset($msg['reaction']) && is_array($msg['reaction']) ? $msg['reaction'] : [];
        $targetMetaId = isset($reaction['message_id']) ? trim((string) $reaction['message_id']) : '';
        if ($targetMetaId === '') {
            return;
        }

        $target = WaInboxMessage::query()->where('meta_message_id', $targetMetaId)->first();
        if (!$target) {
            return;
        }

        $emoji = isset($reaction['emoji']) ? trim((string) $reaction['emoji']) : '';
        $params = is_array($target->template_params) ? $target->template_params : [];

        if ($emoji === '') {
            unset($params['_reaction_in']);
        } else {
            $params['_reaction_in'] = [
                'emoji' => $emoji,
                'at' => isset($msg['timestamp']) ? (int) $msg['timestamp'] : time(),
            ];
        }

        $target->template_params = $params;
        $target->save();

        $conversation = WaInboxConversation::query()->find($target->conversation_id);
        if ($conversation) {
            $this->broadcastMessageCreated($target, $conversation);
        }
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>|null
     */
    private function extractInboundContextParams(array $msg)
    {
        $contextId = isset($msg['context']['id']) ? trim((string) $msg['context']['id']) : '';
        if ($contextId === '') {
            return null;
        }

        return ['_context' => ['message_id' => $contextId]];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function updateDeliveryStatus(array $status)
    {
        $metaId = isset($status['id']) ? (string) $status['id'] : '';
        $state = isset($status['status']) ? (string) $status['status'] : '';
        if ($metaId === '' || $state === '') {
            return;
        }

        $message = WaInboxMessage::query()->where('meta_message_id', $metaId)->first();
        if (!$message) {
            return;
        }

        $map = [
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
        ];

        if (!isset($map[$state])) {
            return;
        }

        $newStatus = $map[$state];
        $currentStatus = (string) $message->delivery_status;

        if ($newStatus !== 'failed' && $currentStatus !== 'failed') {
            $currentRank = isset(self::$deliveryStatusRank[$currentStatus])
                ? self::$deliveryStatusRank[$currentStatus]
                : -1;
            $newRank = isset(self::$deliveryStatusRank[$newStatus])
                ? self::$deliveryStatusRank[$newStatus]
                : -1;
            if ($newRank < $currentRank) {
                return;
            }
        }

        if ($newStatus === $currentStatus && $state !== 'failed') {
            return;
        }

        $message->delivery_status = $newStatus;
        if ($state === 'failed') {
            $err = isset($status['errors'][0]) && is_array($status['errors'][0])
                ? $status['errors'][0]
                : [];
            $parts = [];
            if (!empty($err['message'])) {
                $parts[] = (string) $err['message'];
            } elseif (!empty($err['title'])) {
                $parts[] = (string) $err['title'];
            }
            if (!empty($err['code'])) {
                $parts[] = '#' . $err['code'];
            }
            if (!empty($err['error_data']['details'])) {
                $parts[] = (string) $err['error_data']['details'];
            }
            $message->failed_reason = $parts !== []
                ? implode(' — ', $parts)
                : 'Error de entrega WhatsApp';

            WaInboxLog::error('webhook.delivery_failed', [
                'message_id' => (int) $message->id,
                'meta_message_id' => $metaId,
                'conversation_id' => (int) $message->conversation_id,
                'failed_reason' => $message->failed_reason,
                'status' => WaInboxLog::sanitizePayload($status),
            ]);
        }

        $message->save();
        $this->conversationService->syncLastMessageDeliveryStatus($message);
        $this->broadcastMessageStatusUpdated($message);
    }

    /**
     * @param  WaInboxConversation  $conversation
     * @param  string  $body
     * @param  int|null  $userId
     * @param  string  $messageType
     * @param  string|null  $templateName
     * @param  array<string, mixed>|null  $templateParams
     * @return WaInboxMessage
     */
    public function createOutboundPending(
        WaInboxConversation $conversation,
        $body,
        $userId = null,
        $messageType = 'text',
        $templateName = null,
        $templateParams = null,
        $mediaUrl = null,
        $mediaMime = null
    ) {
        if ($mediaUrl === null && $messageType === 'template' && is_array($templateParams)) {
            $header = isset($templateParams['_header']) && is_array($templateParams['_header'])
                ? $templateParams['_header']
                : null;
            $mediaUrl = $this->resolveTemplateHeaderMediaPath($header, $mediaMime);
            if ($header && !empty($header['filename']) && empty($templateParams['_media_filename'])) {
                $templateParams['_media_filename'] = (string) $header['filename'];
            }
        }

        $preview = $messageType === 'template'
            ? ($mediaUrl !== null ? '[Plantilla con archivo]' : '[Template enviado]')
            : ($this->isMediaMessageType($messageType)
                ? '[' . $messageType . '] ' . mb_substr(trim((string) $body), 0, 200)
                : mb_substr(trim((string) $body), 0, 500));

        if ($mediaUrl !== null && $mediaUrl !== '') {
            $storedPath = CoordinacionMediaLink::storagePathForDatabase($mediaUrl);
            $mediaUrl = $storedPath !== null ? $storedPath : $mediaUrl;
        }
        $mediaMime = WaInboxMime::normalizeForStorage($mediaMime);

        $message = WaInboxMessage::create([
            'conversation_id' => $conversation->id,
            'session_id' => $conversation->session_id,
            'direction' => 'out',
            'body' => $body,
            'message_type' => $messageType,
            'template_name' => $templateName,
            'template_params' => $templateParams,
            'media_url' => $mediaUrl,
            'media_mime' => $mediaMime,
            'delivery_status' => 'pending',
            'sent_at' => now(),
            'sent_by_user_id' => $userId,
        ]);

        $this->conversationService->refreshHeaderFromMessage($conversation, $message, false);
        $this->broadcastMessageCreated($message, $conversation);

        WaInboxLog::info('createOutboundPending', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'message_type' => $messageType,
            'template_name' => $templateName,
        ]);

        return $message;
    }

    /**
     * @param  string  $messageType
     * @return bool
     */
    private function isMediaMessageType($messageType)
    {
        return in_array($messageType, ['image', 'video', 'document', 'audio'], true);
    }

    /**
     * Plantilla con media en encabezado: envío Meta + persistencia solo si tuvo éxito.
     *
     * @param  WaInboxConversation  $conversation
     * @param  string  $body
     * @param  int|null  $userId
     * @param  string  $templateName
     * @param  array<string, mixed>  $templateParams
     * @return array{success: bool, message?: WaInboxMessage, error?: string}
     */
    public function sendTemplateWithHeaderSync(
        WaInboxConversation $conversation,
        $body,
        $userId,
        $templateName,
        array $templateParams
    ) {
        /** @var WhatsappInboxSendService $sendService */
        $sendService = app(WhatsappInboxSendService::class);
        WaInboxLog::info('sendTemplateWithHeaderSync.start', [
            'conversation_id' => (int) $conversation->id,
            'phone_e164' => $conversation->phone_e164,
            'template' => $templateName,
        ]);

        $result = $sendService->dispatchMetaTemplate(
            $conversation->phone_e164,
            $templateName,
            $templateParams
        );

        if (empty($result['success'])) {
            WaInboxLog::error('sendTemplateWithHeaderSync.failed', [
                'conversation_id' => (int) $conversation->id,
                'template' => $templateName,
                'error' => isset($result['error']) ? (string) $result['error'] : null,
            ]);

            return [
                'success' => false,
                'error' => isset($result['error']) ? (string) $result['error'] : 'Error al enviar plantilla por Meta',
            ];
        }

        $header = isset($templateParams['_header']) && is_array($templateParams['_header'])
            ? $templateParams['_header']
            : null;
        $mediaMime = null;
        $mediaUrl = $this->resolveTemplateHeaderMediaPath($header, $mediaMime);
        if ($header && !empty($header['filename'])) {
            $templateParams['_media_filename'] = (string) $header['filename'];
        }

        $preview = $mediaUrl !== null ? '[Plantilla con archivo]' : '[Template enviado]';
        if ($mediaUrl !== null && $mediaUrl !== '') {
            $storedPath = CoordinacionMediaLink::storagePathForDatabase($mediaUrl);
            $mediaUrl = $storedPath !== null ? $storedPath : $mediaUrl;
        }
        $mediaMime = WaInboxMime::normalizeForStorage($mediaMime);

        $message = WaInboxMessage::create([
            'conversation_id' => $conversation->id,
            'session_id' => $conversation->session_id,
            'direction' => 'out',
            'body' => $body,
            'message_type' => 'template',
            'template_name' => $templateName,
            'template_params' => $templateParams,
            'media_url' => $mediaUrl,
            'media_mime' => $mediaMime,
            'meta_message_id' => isset($result['meta_message_id']) ? $result['meta_message_id'] : null,
            'delivery_status' => 'sent',
            'failed_reason' => null,
            'sent_at' => now(),
            'sent_by_user_id' => $userId,
        ]);

        $this->conversationService->refreshHeaderFromMessage($conversation, $message, false);
        $this->broadcastMessageCreated($message, $conversation);

        return ['success' => true, 'message' => $message];
    }

    /**
     * @param  WaInboxConversation  $conversation
     */
    public function syncConversationPreviewFromLastMessage(WaInboxConversation $conversation)
    {
        $last = WaInboxMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return;
        }

        $this->conversationService->refreshHeaderFromMessage($conversation, $last, false);
    }

    /**
     * @param  WaInboxMessage  $message
     * @param  WaInboxConversation  $conversation
     */
    public function broadcastMessageCreated(WaInboxMessage $message, WaInboxConversation $conversation)
    {
        if (!config('meta_whatsapp.inbox_broadcast_enabled', true)) {
            return;
        }

        $conversation->refresh();

        try {
            event(new WaInboxMessageCreated(
                $this->formatMessage($message),
                $this->conversationService->formatConversation($conversation)
            ));
        } catch (BroadcastException $e) {
            WaInboxLog::warning('broadcastMessageCreated.failed', [
                'message_id' => (int) $message->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            WaInboxLog::warning('broadcastMessageCreated.failed', [
                'message_id' => (int) $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  WaInboxMessage  $message
     */
    public function broadcastMessageStatusUpdated(WaInboxMessage $message)
    {
        if (!config('meta_whatsapp.inbox_broadcast_enabled', true)) {
            return;
        }

        try {
            event(new WaInboxMessageStatusUpdated(
                (int) $message->conversation_id,
                (int) $message->id,
                (string) $message->delivery_status,
                $this->formatMessage($message)
            ));
        } catch (BroadcastException $e) {
            WaInboxLog::warning('broadcastMessageStatusUpdated.failed', [
                'message_id' => (int) $message->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            WaInboxLog::warning('broadcastMessageStatusUpdated.failed', [
                'message_id' => (int) $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $header
     * @param  string|null  $mediaMime
     * @return string|null
     */
    private function resolveTemplateHeaderMediaPath($header, &$mediaMime = null)
    {
        $resolved = CoordinacionMediaLink::templateHeaderMediaForDatabase($header);
        if ($resolved['media_mime'] !== null) {
            $mediaMime = $resolved['media_mime'];
        }

        return $resolved['media_url'];
    }

    /**
     * Parámetros de plantilla reutilizables al reenviar (sin metadatos internos _*).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>|null
     */
    private function formatResendableTemplateParams(array $params)
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (!is_string($key) || $key === '' || $key[0] === '_') {
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = (string) $value;
            }
        }

        return $out !== [] ? $out : null;
    }
}
