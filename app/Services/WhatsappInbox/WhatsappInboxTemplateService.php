<?php

namespace App\Services\WhatsappInbox;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappInboxTemplateService
{
    /**
     * Plantillas conocidas con encabezado DOCUMENT (por si el caché no trae components).
     *
     * @var array<int, string>
     */
    private $knownDocumentHeaderTemplates = [
        'pb_docs_consideraciones_doc_v1',
    ];

    /**
     * Plantillas frecuentes (fallback si no hay WABA_ID o falla Graph).
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates()
    {
        return $this->withParamDefs([
            [
                'name' => 'pb_proveedor_llegada_china_v1',
                'label' => 'Proveedor — No llegó a almacén',
                'language' => 'es_PE',
                'text' => 'Hola 👋 {{nombre_cliente}} la carga de tu proveedor {{codigo_proveedor}} aun no llega a nuestro almacen de China, ¿tienes alguna noticia por parte de tu proveedor?',
                'params' => ['nombre_cliente', 'codigo_proveedor'],
            ],
            [
                'name' => 'pb_inspeccion_llegada_v1',
                'label' => 'Inspección — Llegó a almacén',
                'language' => 'es_PE',
                'text' => "📦 Cliente: {{nombre_cliente}} — Proveedor {{codigo_proveedor}} — {{cantidad_cajas}} boxes.\n\nTu carga llegó a nuestro almacén de Yiwu.",
                'params' => ['nombre_cliente', 'codigo_proveedor', 'cantidad_cajas'],
            ],
            [
                'name' => 'pb_entrega_recordatorio_v1',
                'label' => 'Recordatorio general',
                'language' => 'es_PE',
                'text' => "📩 Recordatorio:\n\n{{mensaje}}\n\n🙌",
                'params' => ['mensaje'],
            ],
        ]);
    }

    /**
     * @param  string  $templateName
     * @return bool
     */
    /**
     * DOCUMENT | IMAGE | VIDEO | null
     *
     * @param  string  $templateName
     * @return string|null
     */
    public function getTemplateHeaderFormat($templateName)
    {
        $list = $this->listTemplates();
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];

        foreach ($templates as $tpl) {
            if (!is_array($tpl) || ($tpl['name'] ?? '') !== $templateName) {
                continue;
            }
            $format = strtoupper((string) ($tpl['header_format'] ?? ''));
            if (in_array($format, ['DOCUMENT', 'IMAGE', 'VIDEO'], true)) {
                return $format;
            }
        }

        if (in_array($templateName, $this->knownDocumentHeaderTemplates, true)) {
            return 'DOCUMENT';
        }

        return null;
    }

    public function templateRequiresHeaderMedia($templateName)
    {
        if ($this->getTemplateHeaderFormat($templateName) !== null) {
            return true;
        }

        $list = $this->listTemplates();
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];

        foreach ($templates as $tpl) {
            if (!is_array($tpl) || ($tpl['name'] ?? '') !== $templateName) {
                continue;
            }
            $defs = isset($tpl['param_defs']) && is_array($tpl['param_defs']) ? $tpl['param_defs'] : [];
            foreach ($defs as $def) {
                if (is_array($def) && ($def['type'] ?? '') === 'file') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Texto legible en el chat (sustituye {{param}}).
     *
     * @param  string  $templateName
     * @param  array<string, mixed>  $params
     * @return string
     */
    public function buildPreviewBody($templateName, array $params)
    {
        $list = $this->listTemplates();
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];
        $text = '[' . $templateName . ']';
        foreach ($templates as $tpl) {
            if (is_array($tpl) && isset($tpl['name']) && $tpl['name'] === $templateName) {
                $text = isset($tpl['text']) ? (string) $tpl['text'] : $text;
                break;
            }
        }

        foreach ($params as $key => $val) {
            if ($key === '_header' || is_array($val)) {
                continue;
            }
            $text = str_replace('{{' . $key . '}}', (string) $val, $text);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    public function listTemplates()
    {
        $cacheKey = 'wa_inbox_meta_templates_v2';

        $templates = Cache::remember($cacheKey, 3600, function () {
            return $this->fetchFromMetaOrDefault();
        });

        return [
            'success' => true,
            'data' => $templates,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromMetaOrDefault()
    {
        $wabaId = (string) config('meta_whatsapp.waba_id');
        $token = (string) config('meta_whatsapp.access_token');
        if ($wabaId === '' || $token === '') {
            return $this->defaultTemplates();
        }

        $version = (string) config('meta_whatsapp.graph_api_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->acceptJson()
                ->get($url, ['limit' => 100]);

            if (!$response->successful()) {
                Log::warning('WhatsappInboxTemplate: Graph list failed', [
                    'status' => $response->status(),
                ]);

                return $this->defaultTemplates();
            }

            $data = $response->json();
            $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
            $out = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($item['status']) && strtoupper((string) $item['status']) !== 'APPROVED') {
                    continue;
                }

                $name = isset($item['name']) ? (string) $item['name'] : '';
                if ($name === '') {
                    continue;
                }

                $parsed = $this->parseTemplateComponents(
                    isset($item['components']) && is_array($item['components']) ? $item['components'] : []
                );

                $out[] = [
                    'name' => $name,
                    'label' => $name,
                    'language' => isset($item['language']) ? (string) $item['language'] : 'es_PE',
                    'text' => $parsed['text'],
                    'params' => $parsed['params'],
                    'param_defs' => $parsed['param_defs'],
                    'header_format' => $parsed['header_format'],
                ];
            }

            if ($out === []) {
                return $this->defaultTemplates();
            }

            return $out;
        } catch (\Exception $e) {
            Log::warning('WhatsappInboxTemplate: exception', ['message' => $e->getMessage()]);

            return $this->defaultTemplates();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array{text: string, params: array<int, string>, param_defs: array<int, array<string, mixed>>, header_format: string|null}
     */
    private function parseTemplateComponents(array $components)
    {
        $bodyText = '';
        $params = [];
        $paramDefs = [];
        $headerFormat = null;

        foreach ($components as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $type = strtoupper((string) ($comp['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($comp['format'] ?? 'TEXT'));
                if (in_array($format, ['DOCUMENT', 'IMAGE', 'VIDEO'], true)) {
                    $headerFormat = $format;
                    $label = 'Archivo de encabezado';
                    if ($format === 'DOCUMENT') {
                        $label = 'PDF de encabezado (requerido)';
                    }
                    $paramDefs[] = [
                        'name' => 'header_media',
                        'type' => 'file',
                        'file_kind' => strtolower($format),
                        'label' => $label,
                    ];
                }
                continue;
            }

            if ($type !== 'BODY') {
                continue;
            }

            $bodyText = isset($comp['text']) ? (string) $comp['text'] : '';
            if (preg_match_all('/\{\{([^}]+)\}\}/', $bodyText, $m)) {
                foreach ($m[1] as $paramName) {
                    $params[] = $paramName;
                    $paramDefs[] = [
                        'name' => $paramName,
                        'type' => 'text',
                        'label' => str_replace('_', ' ', $paramName),
                    ];
                }
            }
        }

        return [
            'text' => $bodyText,
            'params' => $params,
            'param_defs' => $paramDefs,
            'header_format' => $headerFormat,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array<string, mixed>>
     */
    private function withParamDefs(array $templates)
    {
        foreach ($templates as $i => $tpl) {
            if (!empty($tpl['param_defs'])) {
                continue;
            }
            $templates[$i]['param_defs'] = array_map(function ($name) {
                return [
                    'name' => $name,
                    'type' => 'text',
                    'label' => str_replace('_', ' ', $name),
                ];
            }, isset($tpl['params']) && is_array($tpl['params']) ? $tpl['params'] : []);
        }

        return $templates;
    }
}
