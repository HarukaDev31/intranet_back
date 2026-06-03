<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsappInbox\WhatsappInboxCoordinacionOutboundService;
use App\Services\WhatsappInbox\WhatsappInboxOutboundRecorder;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaInboxLog;
use App\Support\WhatsApp\WaInboxMetaError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío directo a Meta Graph API (plantillas). El historial operativo vive en wa_inbox_*.
 */
class MetaWhatsAppCoordinacionService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, response?: mixed, error?: string, inbox_message_id?: int}
     */
    public function process(array $payload): array
    {
        return app(WhatsappInboxCoordinacionOutboundService::class)->process($payload);
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
     * @param  array<string, mixed>  $inboxContext
     * @return array{status: bool, response?: mixed, error?: string}
     */
    public function sendMetaTemplate(
        string $phoneE164,
        string $templateName,
        string $languageCode,
        array $bodyParameters,
        ?array $header = null,
        bool $requiresHeaderComponent = false,
        array $inboxContext = []
    ): array {
        if (is_array($header) && !empty($header['type'])) {
            $preparedHeader = CoordinacionMediaLink::prepareHeader($header);
            if ($preparedHeader !== null) {
                $header = $preparedHeader;
            }
        }

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
            $errorMsg = WaInboxMetaError::userMessage($response->status(), $json);

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

        if (!WhatsappInboxOutboundRecorder::isSuppressed()) {
            try {
                app(WhatsappInboxOutboundRecorder::class)->recordTemplateAfterMetaSend(
                    $phoneE164,
                    $templateName,
                    $bodyParameters,
                    $header,
                    $jsonOk,
                    $inboxContext
                );
            } catch (\Exception $e) {
                WaInboxLog::error('metaTemplate.inbox_record_failed', [
                    'template' => $templateName,
                    'phone_e164' => $phoneE164,
                    'error' => $e->getMessage(),
                ]);
                Log::error('MetaWhatsAppCoordinacion: no se pudo registrar plantilla en wa_inbox', [
                    'template' => $templateName,
                    'phone_e164' => $phoneE164,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
}
