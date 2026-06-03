<?php

namespace App\Services\WhatsappInbox;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappInboxTemplateService
{
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
     * @return array<string, mixed>
     */
    public function listTemplates()
    {
        $cacheKey = 'wa_inbox_meta_templates_v1';

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
     * @return array{text: string, params: array<int, string>, param_defs: array<int, array<string, mixed>>}
     */
    private function parseTemplateComponents(array $components)
    {
        $bodyText = '';
        $params = [];
        $paramDefs = [];

        foreach ($components as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $type = strtoupper((string) ($comp['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($comp['format'] ?? 'TEXT'));
                if (in_array($format, ['DOCUMENT', 'IMAGE', 'VIDEO'], true)) {
                    $paramDefs[] = [
                        'name' => 'header_media',
                        'type' => 'file',
                        'file_kind' => strtolower($format),
                        'label' => 'Archivo de encabezado',
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
