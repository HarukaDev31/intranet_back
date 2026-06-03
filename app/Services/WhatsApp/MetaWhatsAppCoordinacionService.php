<?php

namespace App\Services\WhatsApp;

use App\Jobs\WhatsApp\ProcessWhatsAppCoordinacionBitrixRegistroJob;
use App\Models\WhatsAppCoordinacionBitrixRegistro;
use App\Services\Crm\Bitrix\BitrixCrmClient;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Support\WhatsApp\WaInboxLog;
use App\Support\WhatsApp\WhatsappEnvironmentPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppCoordinacionService
{
    /**
     * Flujo: Meta template primero; registro en Bitrix (open line) se encola en tabla + job aparte.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, response?: mixed, error?: string}
     */
    public function process(array $payload): array
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
     */
    private function processTemplate(array $payload): array
    {
        if (!config('meta_whatsapp.coordinacion_enabled')) {
            return ['status' => false, 'error' => 'Meta coordinación deshabilitado'];
        }

        $token = (string) config('meta_whatsapp.access_token');
        if ($token === '') {
            return ['status' => false, 'error' => 'META_WHATSAPP_ACCESS_TOKEN no configurado'];
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

        $bitrixMessage = (string) ($payload['bitrix_message'] ?? '');
        if ($bitrixMessage === '') {
            return ['status' => false, 'error' => 'bitrix_message requerido (texto con variables reemplazadas)'];
        }

        $header = CoordinacionMediaLink::prepareHeader(
            isset($payload['header']) && is_array($payload['header']) ? $payload['header'] : null
        );
        if (isset($payload['header']) && is_array($payload['header']) && !empty($payload['header']['type']) && $header === null) {
            Log::error('MetaWhatsAppCoordinacion: no se pudo publicar media del encabezado en S3', [
                'template' => $templateName,
                'path' => $payload['header']['path'] ?? null,
            ]);

            return ['status' => false, 'error' => 'No se pudo subir el archivo del encabezado a almacenamiento público'];
        }

        $metaResult = $this->sendMetaTemplate(
            $phone,
            $templateName,
            (string) ($payload['language'] ?? config('meta_whatsapp.default_language', 'es_PE')),
            $this->normalizeBodyParameters($payload['body_parameters'] ?? []),
            $header
        );

        $metaOk = !empty($metaResult['status']);

        $bitrixRegistroId = $this->enqueueBitrixRegistration(
            $phone,
            $payload,
            $bitrixMessage,
            $templateName,
            $metaOk,
            $metaResult
        );

        return array_merge($metaResult, [
            'bitrix_registro_id' => $bitrixRegistroId,
        ]);
    }

    /**
     * Procesa un registro encolado (contacto, chat, intercept, message.add).
     *
     * @throws \Throwable si Bitrix no pudo registrar y debe reintentarse el job
     */
    public function processQueuedBitrixRegistration(WhatsAppCoordinacionBitrixRegistro $registro): void
    {
        if (!$registro->isProcessable()) {
            return;
        }

        $phone = (string) ($registro->phone_e164 ?? '');
        $payload = is_array($registro->payload_extra) ? $registro->payload_extra : [];

        $contactId = $registro->bitrix_contact_id;
        if ($contactId === null && $phone !== '') {
            $contactId = $this->resolveBitrixContactId($phone, $payload);
            if ($contactId !== null) {
                $registro->bitrix_contact_id = $contactId;
                $registro->save();
            }
        }

        if ($contactId === null) {
            throw new \RuntimeException('Contacto no encontrado en Bitrix para teléfono ' . $phone);
        }

        $chatId = $registro->bitrix_chat_id ?? $this->getOpenLineChatId((int) $contactId);
        if ($chatId !== null && $registro->bitrix_chat_id === null) {
            $registro->bitrix_chat_id = $chatId;
            $registro->save();
        }

        $lineMessage = $this->formatBitrixOpenlineMessage(
            $registro->bitrix_message,
            $registro->template_name,
            (bool) $registro->meta_ok
        );

        if (!$registro->meta_ok) {
            $lineMessage .= "\n\n⚠️ No se entregó por Meta API: "
                . (string) ($registro->meta_error ?? 'error desconocido');
        }

        if ($chatId === null) {
            if ($this->shouldUseBitrixTimelineFallback()) {
                $this->registerBitrixTimelineComment((int) $contactId, $lineMessage, $registro->template_name);
                Log::info('MetaWhatsAppCoordinacion: sin CHAT_ID open line, texto guardado en timeline CRM', [
                    'contact_id' => $contactId,
                    'template' => $registro->template_name,
                ]);

                return;
            }

            throw new \RuntimeException('Sin CHAT_ID de coordinación en Bitrix para contacto ' . $contactId);
        }

        $this->tryInterceptSession($chatId);
        $this->registerBitrixMessage((int) $contactId, $chatId, $lineMessage);

        if ($registro->include_timeline) {
            $this->registerBitrixTimelineComment((int) $contactId, $lineMessage, $registro->template_name);
        }
    }

    private function shouldUseBitrixTimelineFallback(): bool
    {
        return (bool) config('meta_whatsapp.bitrix_fallback_timeline_when_no_chat', true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metaResult
     */
    private function enqueueBitrixRegistration(
        string $phoneE164,
        array $payload,
        string $bitrixMessage,
        string $templateName,
        bool $metaOk,
        array $metaResult
    ): ?int {
        if ($metaOk && !config('meta_whatsapp.bitrix_register_openline_message', true)) {
            return null;
        }

        if (!$metaOk && !config('meta_whatsapp.bitrix_register_openline_on_meta_failure', true)) {
            return null;
        }

        if (!config('services.bitrix.webhook_url')) {
            return null;
        }

        $extra = [];
        if (!empty($payload['bitrix_contact_id'])) {
            $extra['bitrix_contact_id'] = (int) $payload['bitrix_contact_id'];
        }
        if (!empty($payload['_domain'])) {
            $extra['_domain'] = (string) $payload['_domain'];
        }

        try {
            $registro = WhatsAppCoordinacionBitrixRegistro::query()->create([
                'phone_e164' => $phoneE164,
                'bitrix_contact_id' => $extra['bitrix_contact_id'] ?? null,
                'template_name' => $templateName,
                'bitrix_message' => $bitrixMessage,
                'meta_ok' => $metaOk,
                'meta_error' => $metaOk ? null : mb_substr((string) ($metaResult['error'] ?? 'error desconocido'), 0, 500),
                'include_timeline' => $metaOk && config('meta_whatsapp.bitrix_timeline_log', false),
                'status' => WhatsAppCoordinacionBitrixRegistro::STATUS_PENDING,
                'max_attempts' => (int) config('meta_whatsapp.bitrix_register_max_attempts', WhatsAppCoordinacionBitrixRegistro::MAX_ATTEMPTS),
                'payload_extra' => $extra !== [] ? $extra : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MetaWhatsAppCoordinacion: no se pudo encolar registro Bitrix en BD', [
                'template' => $templateName,
                'meta_ok' => $metaOk,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        ProcessWhatsAppCoordinacionBitrixRegistroJob::dispatch(
            $registro->id,
            isset($extra['_domain']) ? (string) $extra['_domain'] : null
        );

        Log::info('MetaWhatsAppCoordinacion: registro Bitrix encolado', [
            'registro_id' => $registro->id,
            'template' => $templateName,
            'meta_ok' => $metaOk,
        ]);

        return $registro->id;
    }

    /**
     * @param  array<int, array<string, string>>|array<string, string>  $params
     * @return array<int, array{type: string, parameter_name?: string, text: string}>
     */
    public function normalizeBodyParameters($params): array
    {
        if (!is_array($params)) {
            return [];
        }

        $out = [];
        $isList = array_keys($params) === range(0, count($params) - 1);

        if ($isList) {
            foreach ($params as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = isset($row['parameter_name']) ? (string) $row['parameter_name'] : '';
                $text = $this->normalizeTemplateParameterText(isset($row['text']) ? (string) $row['text'] : '');
                $item = ['type' => 'text', 'text' => $text];
                if ($name !== '') {
                    $item['parameter_name'] = $name;
                }
                $out[] = $item;
            }

            return $out;
        }

        foreach ($params as $name => $text) {
            $out[] = [
                'type' => 'text',
                'parameter_name' => (string) $name,
                'text' => $this->normalizeTemplateParameterText((string) $text),
            ];
        }

        return $out;
    }

    private function normalizeTemplateParameterText(string $text): string
    {
        // Meta API: sin saltos de línea/tabs ni más de 4 espacios consecutivos.
        $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/ {5,}/', '    ', $text) ?? $text;
        $text = trim($text);

        if ($text !== '') {
            return $text;
        }

        return (string) config('meta_whatsapp.empty_body_parameter_placeholder', '—');
    }

    /**
     * @param  array<int, array{type: string, parameter_name?: string, text: string}>  $bodyParameters
     * @param  array<string, mixed>|null  $header
     */
    public function sendMetaTemplate(
        string $phoneE164,
        string $templateName,
        string $languageCode,
        array $bodyParameters,
        ?array $header = null,
        bool $requiresHeaderComponent = false
    ): array {
        $phoneNumberId = (string) config('meta_whatsapp.phone_number_id');
        $version = (string) config('meta_whatsapp.graph_api_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $components = [];
        $headerComponent = null;
        if (is_array($header) && !empty($header['type'])) {
            $headerComponent = $this->buildHeaderComponent($header);
            if ($headerComponent !== null) {
                $components[] = $headerComponent;
            }
        }

        if ($requiresHeaderComponent && $headerComponent === null) {
            WaInboxLog::error('metaTemplate.header_component_missing', [
                'template' => $templateName,
                'phone_e164' => $phoneE164,
                'header' => WaInboxLog::sanitizePayload($header),
            ]);

            return [
                'status' => false,
                'error' => 'Encabezado multimedia inválido (Meta esperaba DOCUMENT/IMAGE/VIDEO)',
            ];
        }
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneE164,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];
        if ($components !== []) {
            $body['template']['components'] = $components;
        }

        WaInboxLog::info('metaTemplate.request', [
            'template' => $templateName,
            'phone_e164' => $phoneE164,
            'language' => $languageCode,
            'requires_header' => $requiresHeaderComponent,
            'component_types' => array_map(function ($c) {
                return isset($c['type']) ? $c['type'] : '?';
            }, $components),
            'payload' => WaInboxLog::sanitizePayload($body),
        ]);

        $response = Http::timeout(60)
            ->withToken((string) config('meta_whatsapp.access_token'))
            ->acceptJson()
            ->asJson()
            ->post($url, $body);

        if (!$response->successful()) {
            $json = $response->json();
            $errorMsg = 'Meta API HTTP ' . $response->status();
            if (is_array($json) && isset($json['error']['message'])) {
                $errorMsg .= ': ' . (string) $json['error']['message'];
                if (isset($json['error']['code'])) {
                    $errorMsg .= ' (#' . $json['error']['code'] . ')';
                }
                if (isset($json['error']['error_data']['details'])) {
                    $errorMsg .= ' — ' . (string) $json['error']['error_data']['details'];
                }
            }

            WaInboxLog::error('metaTemplate.graph_error', [
                'status' => $response->status(),
                'template' => $templateName,
                'phone_e164' => $phoneE164,
                'body' => WaInboxLog::sanitizePayload(is_array($json) ? $json : $response->body()),
            ]);

            Log::error('MetaWhatsAppCoordinacion: error Graph API', [
                'status' => $response->status(),
                'body' => $json ?? $response->body(),
                'template' => $templateName,
            ]);

            return [
                'status' => false,
                'error' => $errorMsg,
                'response' => $json,
            ];
        }

        $jsonOk = $response->json();
        WaInboxLog::info('metaTemplate.ok', [
            'template' => $templateName,
            'phone_e164' => $phoneE164,
            'wamid' => is_array($jsonOk) && isset($jsonOk['messages'][0]['id'])
                ? $jsonOk['messages'][0]['id']
                : null,
        ]);

        return [
            'status' => true,
            'response' => $jsonOk,
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function buildHeaderComponent(array $header): ?array
    {
        $type = strtolower((string) ($header['type'] ?? ''));
        if ($type === 'document') {
            $link = $this->resolvePublicUrl($header);
            if ($link === null) {
                return null;
            }
            $filename = (string) ($header['filename'] ?? basename(parse_url($link, PHP_URL_PATH) ?: 'document.pdf'));

            return [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'document',
                    'document' => [
                        'link' => $link,
                        'filename' => $filename,
                    ],
                ]],
            ];
        }

        if ($type === 'image') {
            $link = $this->resolvePublicUrl($header);
            if ($link === null) {
                return null;
            }

            return [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'image',
                    'image' => ['link' => $link],
                ]],
            ];
        }

        if ($type === 'video') {
            $link = $this->resolvePublicUrl($header);
            if ($link === null) {
                return null;
            }

            return [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'video',
                    'video' => ['link' => $link],
                ]],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function resolvePublicUrl(array $header): ?string
    {
        if (!empty($header['link']) && filter_var($header['link'], FILTER_VALIDATE_URL)) {
            return (string) $header['link'];
        }

        $path = (string) ($header['path'] ?? '');
        if ($path === '') {
            return null;
        }

        $filename = isset($header['filename']) ? (string) $header['filename'] : null;
        $url = CoordinacionMediaLink::resolveForMetaHeader($path, $filename);
        if ($url === null) {
            Log::warning('MetaWhatsAppCoordinacion: no se pudo resolver URL pública', [
                'path' => $path,
            ]);
        }

        return $url;
    }

    public function normalizePhoneE164(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (strlen($phone) === 9) {
            $phone = '51' . $phone;
        }

        return $phone;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveBitrixContactId(string $phoneE164, array $payload): ?int
    {
        if (!empty($payload['bitrix_contact_id'])) {
            return (int) $payload['bitrix_contact_id'];
        }

        $client = BitrixCrmClient::fromConfig();
        if ($client === null) {
            return null;
        }

        try {
            $byDuplicate = $client->findContactIdByPhone($phoneE164);
            if ($byDuplicate !== null) {
                return $byDuplicate;
            }
        } catch (\Throwable $e) {
            Log::debug('Bitrix duplicate.findbycomm: ' . $e->getMessage());
        }

        try {
            $json = $client->call('crm.contact.list', [
                'FILTER' => ['PHONE' => $phoneE164],
                'SELECT' => ['ID'],
            ]);
            $first = $json['result'][0]['ID'] ?? null;

            return is_numeric($first) ? (int) $first : null;
        } catch (\Throwable $e) {
            Log::warning('Bitrix crm.contact.list: ' . $e->getMessage());

            return null;
        }
    }

    private function getOpenLineChatId(int $contactId): ?int
    {
        $client = BitrixCrmClient::fromConfig();
        if ($client === null) {
            return null;
        }

        try {
            $json = $client->call('imopenlines.crm.chat.get', [
                'CRM_ENTITY_TYPE' => 'contact',
                'CRM_ENTITY' => $contactId,
                'ACTIVE_ONLY' => 'N',
            ]);
            $chats = $json['result'] ?? [];
            if (!is_array($chats)) {
                return null;
            }

            $matchId = $this->pickCoordinacionChatId($chats);
            if ($matchId !== null) {
                return $matchId;
            }

            $titles = array_map(static function ($chat) {
                return is_array($chat) ? (string) ($chat['CONNECTOR_TITLE'] ?? '') : '';
            }, $chats);
            Log::warning('MetaWhatsAppCoordinacion: ningún chat CRM coincide con canal coordinación', [
                'contact_id' => $contactId,
                'line_id_config' => (int) config('meta_whatsapp.bitrix_line_id', 39),
                'connector_titles' => $titles,
                'connector_match' => config('meta_whatsapp.bitrix_connector_match'),
                'connector_exclude' => config('meta_whatsapp.bitrix_connector_exclude'),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Bitrix crm.chat.get: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $chats
     */
    private function pickCoordinacionChatId(array $chats): ?int
    {
        $include = config('meta_whatsapp.bitrix_connector_match', []);
        $exclude = config('meta_whatsapp.bitrix_connector_exclude', []);
        if (!is_array($include)) {
            $include = [];
        }
        if (!is_array($exclude)) {
            $exclude = [];
        }

        $lineId = (int) config('meta_whatsapp.bitrix_line_id', 39);

        foreach ($chats as $chat) {
            if (!is_array($chat)) {
                continue;
            }
            $title = (string) ($chat['CONNECTOR_TITLE'] ?? '');
            if ($title === '' || $this->connectorTitleIsExcluded($title, $exclude)) {
                continue;
            }
            if ($include === [] || $this->connectorTitleMatches($title, $include)) {
                $chatId = $this->extractChatIdFromOpenlineRow($chat);
                if ($chatId !== null) {
                    return $chatId;
                }
            }
        }

        foreach ($chats as $chat) {
            if (!is_array($chat)) {
                continue;
            }
            $title = (string) ($chat['CONNECTOR_TITLE'] ?? '');
            if ($title !== '' && $this->connectorTitleIsExcluded($title, $exclude)) {
                continue;
            }
            if ($this->chatLineId($chat) === $lineId) {
                $chatId = $this->extractChatIdFromOpenlineRow($chat);
                if ($chatId !== null) {
                    Log::info('MetaWhatsAppCoordinacion: chat Bitrix por LINE_ID', [
                        'line_id' => $lineId,
                        'connector_title' => $title,
                        'chat_id' => $chatId,
                    ]);

                    return $chatId;
                }
            }
        }

        foreach ($chats as $chat) {
            if (!is_array($chat)) {
                continue;
            }
            $title = (string) ($chat['CONNECTOR_TITLE'] ?? '');
            if ($title === '' || $this->connectorTitleIsExcluded($title, $exclude)) {
                continue;
            }
            if ($this->connectorTitleLooksLikeWhatsApp($title)) {
                $chatId = $this->extractChatIdFromOpenlineRow($chat);
                if ($chatId !== null) {
                    Log::info('MetaWhatsAppCoordinacion: chat Bitrix por fallback WhatsApp', [
                        'connector_title' => $title,
                        'chat_id' => $chatId,
                    ]);

                    return $chatId;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function extractChatIdFromOpenlineRow(array $chat): ?int
    {
        $chatId = $chat['CHAT_ID'] ?? null;

        return is_numeric($chatId) ? (int) $chatId : null;
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function chatLineId(array $chat): ?int
    {
        foreach (['LINE_ID', 'line_id', 'OPEN_LINE_ID', 'open_line_id'] as $key) {
            if (isset($chat[$key]) && is_numeric($chat[$key])) {
                return (int) $chat[$key];
            }
        }

        return null;
    }

    private function connectorTitleLooksLikeWhatsApp(string $title): bool
    {
        return stripos($title, 'whatsapp') !== false
            || stripos($title, 'powerapp') !== false
            || stripos($title, 'whatcrm') !== false;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function connectorTitleMatches(string $title, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && stripos($title, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function connectorTitleIsExcluded(string $title, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && stripos($title, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function tryInterceptSession(?int $chatId): void
    {
        if ($chatId === null) {
            return;
        }

        $interceptBase = rtrim((string) config('meta_whatsapp.bitrix_webhook_intercept', ''), '/');
        if ($interceptBase === '') {
            return;
        }

        try {
            Http::timeout((int) config('services.bitrix.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post($interceptBase . '/imopenlines.session.intercept', [
                    'CHAT_ID' => $chatId,
                    'USER_ID' => (int) config('meta_whatsapp.bitrix_user_id', 181),
                ]);
        } catch (\Throwable $e) {
            Log::info('Bitrix session.intercept (puede ignorarse si ya abierta): ' . $e->getMessage());
        }
    }

    private function formatBitrixOpenlineMessage(string $bitrixMessage, string $templateName, bool $metaOk): string
    {
        $prefix = $metaOk
            ? '[WhatsApp Meta · ' . $templateName . "]\n\n"
            : '[WhatsApp · ' . $templateName . " · sin entrega Meta]\n\n";

        return $prefix . $bitrixMessage;
    }

    /**
     * Registra en el chat de la línea abierta (39) vía imopenlines.crm.message.add.
     */
    private function registerBitrixMessage(int $contactId, ?int $chatId, string $message): void
    {
        $client = BitrixCrmClient::fromConfig();
        if ($client === null) {
            throw new \RuntimeException('BITRIX_WEBHOOK_URL no configurado');
        }

        if ($chatId === null) {
            throw new \RuntimeException('Sin CHAT_ID de coordinación para contacto ' . $contactId);
        }

        $body = [
            'CRM_ENTITY_TYPE' => 'contact',
            'CRM_ENTITY' => $contactId,
            'MESSAGE' => $message,
            'USER_ID' => (int) config('meta_whatsapp.bitrix_user_id', 181),
            'CHAT_ID' => $chatId,
        ];

        $client->call('imopenlines.crm.message.add', $body);
    }

    /**
     * Historial en CRM sin reenviar WhatsApp (solo cuando Meta ya entregó la plantilla).
     */
    private function registerBitrixTimelineComment(int $contactId, string $message, string $templateName): void
    {
        $client = BitrixCrmClient::fromConfig();
        if ($client === null) {
            throw new \RuntimeException('BITRIX_WEBHOOK_URL no configurado');
        }

        $comment = '[WhatsApp Meta · ' . $templateName . "]\n" . $message;

        $client->call('crm.timeline.comment.add', [
            'fields' => [
                'ENTITY_ID' => $contactId,
                'ENTITY_TYPE' => 'contact',
                'COMMENT' => $comment,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processLegacyMessage(array $payload): array
    {
        return $this->legacyTraitCall('/messageV2', [
            'message' => (string) ($payload['message'] ?? ''),
            'phoneNumberId' => $this->legacyPhoneId($payload),
            'sleep' => 0,
            'fromNumber' => 'consolidado',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processLegacyMedia(array $payload): array
    {
        $path = (string) ($payload['path'] ?? '');
        if ($path === '' || !is_readable($path)) {
            return ['status' => false, 'error' => 'Archivo no legible'];
        }

        $content = base64_encode((string) file_get_contents($path));

        $result = $this->legacyTraitCall('/mediaV2', [
            'fileContent' => $content,
            'fileName' => (string) ($payload['fileName'] ?? basename($path)),
            'mimeType' => $payload['mimeType'] ?? null,
            'message' => $payload['caption'] ?? null,
            'phoneNumberId' => $this->legacyPhoneId($payload),
            'sleep' => 0,
            'fromNumber' => 'consolidado',
        ]);

        return is_array($result) ? $result : ['status' => (bool) $result];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function legacyPhoneId(array $payload): string
    {
        $phone = (string) ($payload['phone'] ?? '');
        if (strpos($phone, '@') !== false) {
            return (string) WhatsappEnvironmentPhone::resolve($phone);
        }
        $e164 = $this->normalizePhoneE164($phone);
        $legacy = $e164 !== '' ? $e164 . '@c.us' : '';

        return (string) WhatsappEnvironmentPhone::resolve($legacy !== '' ? $legacy : null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: bool, response?: mixed}
     */
    private function legacyTraitCall(string $endpoint, array $data): array
    {
        $url = 'https://redis.probusiness.pe/api/whatsapp' . $endpoint;
        $response = Http::timeout(120)
            ->acceptJson()
            ->asJson()
            ->post($url, $data);

        return [
            'status' => $response->successful(),
            'response' => $response->json() ?? $response->body(),
        ];
    }
}
