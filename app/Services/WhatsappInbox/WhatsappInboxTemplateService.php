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
        'pb_consolidado_cotizacion_final_v1',
        'pb_consolidado_cotizacion_final_pdf_v1',
        'pb_rotulado_pdf_producto_v1',
        'pb_rotulado_etiqueta_calzado_v1',
        'pb_rotulado_etiqueta_ropa_v1',
        'pb_rotulado_etiqueta_ropa_interior_v1',
        'pb_rotulado_etiqueta_maquinaria_v1',
    ];

    /**
     * Plantillas conocidas con encabezado IMAGE (fallback si Graph no trae components).
     *
     * @var array<int, string>
     */
    private $knownImageHeaderTemplates = [
        'pb_consolidado_pagos_img_v1',
        'pb_consolidado_resumen_pago_v1',
    ];

    /**
     * Parámetros de BODY conocidos (fallback si Graph/caché no trae {{vars}} parseadas).
     * Orden = orden posicional en Meta cuando parameter_format=POSITIONAL.
     *
     * @var array<string, array<int, string>>
     */
    private $knownBodyParamNames = [
        'pb_consolidado_resumen_pago_v1' => ['total_cotizacion', 'adelanto', 'pendiente'],
        'pb_consolidado_cotizacion_final_v1' => [
            'carga',
            'nombre',
            'costo_cbm',
            'impuestos',
            'servicios_extras',
            'total',
            'fecha_limite',
        ],
        'pb_docs_excel_link_v1' => ['carga', 'codigo_proveedor', 'link_excel'],
        'pb_docs_excel_link_v1_qa' => ['carga', 'codigo_proveedor', 'link_excel', 'link_intranet'],
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
                'label' => 'Inspección — Llegó a almacén (solo boxes)',
                'language' => 'es_PE',
                'text' => "📦 Cliente: {{nombre_cliente}} — Proveedor {{codigo_proveedor}} — {{cantidad_cajas}} boxes.\n\nTu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.\n\n🔗 Ver inspección: {{link_inspeccion}} 📦",
                'params' => ['nombre_cliente', 'codigo_proveedor', 'cantidad_cajas', 'link_inspeccion'],
            ],
            [
                'name' => 'pb_inspeccion_llegada_v1_pallets',
                'label' => 'Inspección — Llegó a almacén (solo pallets)',
                'language' => 'es_PE',
                'text' => "📦 Cliente: {{nombre_cliente}} — Proveedor {{codigo_proveedor}} — {{cantidad_pallets}} pallets.\n\nTu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.\n\n🔗 Ver inspección: {{link_inspeccion}} 📦",
                'params' => ['nombre_cliente', 'codigo_proveedor', 'cantidad_pallets', 'link_inspeccion'],
            ],
            [
                'name' => 'pb_inspeccion_llegada_v1_pallets_boxes',
                'label' => 'Inspección — Llegó a almacén (boxes y pallets)',
                'language' => 'es_PE',
                'text' => "📦 Cliente: {{nombre_cliente}} — Proveedor {{codigo_proveedor}} — {{cantidad_boxes}} boxes y {{cantidad_pallets}} pallets.\n\nTu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.\n\n🔗 Ver inspección: {{link_inspeccion}} 📦",
                'params' => ['nombre_cliente', 'codigo_proveedor', 'cantidad_boxes', 'cantidad_pallets', 'link_inspeccion'],
            ],
            [
                'name' => 'pb_entrega_recordatorio_v1',
                'label' => 'Recordatorio general',
                'language' => 'es_PE',
                'text' => "📩 Recordatorio:\n\n{{mensaje}}\n\n🙌",
                'params' => ['mensaje'],
            ],
            [
                'name' => 'pb_docs_consideraciones_doc_v1',
                'label' => 'Docs — Consideraciones PDF',
                'language' => 'es_PE',
                'text' => 'Consideraciones para la documentación de tu importación. 📋',
                'params' => [],
                'header_format' => 'DOCUMENT',
            ],
            [
                'name' => 'pb_docs_excel_link_v1',
                'label' => 'Docs — Excel de confirmación (link)',
                'language' => 'es_PE',
                'text' => "Documentación: CONSOLIDADO #{{carga}}\n\nExcel de confirmación — Proveedor {{codigo_proveedor}}\n\nLlenalo aquí: {{link_excel}} 📄",
                'params' => ['carga', 'codigo_proveedor', 'link_excel'],
            ],
            [
                'name' => 'pb_docs_excel_link_v1_qa',
                'label' => 'Docs — Excel de confirmación (Drive + web)',
                'language' => 'es_PE',
                'text' => "Documentación: CONSOLIDADO #{{carga}}\n\nExcel de confirmación — Proveedor {{codigo_proveedor}}\n\nLlenalo aquí: {{link_excel}} o\n{{link_intranet}}  📄 .",
                'params' => ['carga', 'codigo_proveedor', 'link_excel', 'link_intranet'],
            ],
            [
                'name' => 'pb_consolidado_resumen_pago_v1',
                'label' => 'Consolidado — Resumen de pago',
                'language' => 'es_PE',
                'text' => "💰*Resumen de Pago*\n✅Cotización final: \${{total_cotizacion}}\n✅Adelanto: \${{adelanto}}\n✅ Pendiente de pago: \${{pendiente}} 💳",
                'params' => ['total_cotizacion', 'adelanto', 'pendiente'],
                'header_format' => 'IMAGE',
            ],
            [
                'name' => 'pb_consolidado_cotizacion_final_v1',
                'label' => 'Consolidado — Cotización final',
                'language' => 'es_PE',
                'text' => "📦 *Consolidado #{{carga}}*\nHola {{nombre}} 😁 un gusto saludarte!\nA continuación te envio la cotización final de tu importación📋📦.\n🙋‍♂PAGO PENDIENTE:\n☑️Costo CBM: \${{costo_cbm}}\n☑️Impuestos: \${{impuestos}}\n{{servicios_extras}}✅Total: \${{total}}\nPronto le aviso nuevos avances, que tengan buen dia\nÚltimo día de pago: {{fecha_limite}}",
                'params' => ['carga', 'nombre', 'costo_cbm', 'impuestos', 'servicios_extras', 'total', 'fecha_limite'],
                'header_format' => 'DOCUMENT',
            ],
        ]);
    }

    /**
     * Quita metadatos internos del inbox (p. ej. _media_filename) que no son parámetros Meta.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function stripInternalTemplateParamKeys(array $params)
    {
        foreach (array_keys($params) as $key) {
            if (!is_string($key) || $key === '' || $key[0] === '_') {
                unset($params[$key]);
            }
        }

        return $params;
    }

    /**
     * Nombres de variables del BODY según caché Graph (no incluye header DOCUMENT).
     *
     * @param  string  $templateName
     * @return array<int, string>
     */
    public function getTemplateBodyParamNames($templateName)
    {
        $tpl = $this->findTemplateByName($templateName);
        $names = [];
        if ($tpl !== null && isset($tpl['params']) && is_array($tpl['params'])) {
            $names = array_values($tpl['params']);
        }

        if ($names === [] && isset($this->knownBodyParamNames[$templateName])) {
            return array_values($this->knownBodyParamNames[$templateName]);
        }

        return $names;
    }

    /**
     * @param  string  $templateName
     * @return bool
     */
    public function usesPositionalParameters($templateName)
    {
        $tpl = $this->findTemplateByName($templateName);
        if ($tpl !== null) {
            $format = strtoupper((string) ($tpl['parameter_format'] ?? ''));
            if ($format === 'POSITIONAL') {
                return true;
            }
            if ($format === 'NAMED') {
                return false;
            }
        }

        // Solo mirar nombres del caché Graph (sin fallback known) para detectar {{1}}{{2}}.
        $graphNames = [];
        if ($tpl !== null && isset($tpl['params']) && is_array($tpl['params'])) {
            $graphNames = array_values($tpl['params']);
        }
        if ($graphNames === []) {
            return false;
        }

        foreach ($graphNames as $name) {
            if (!ctype_digit((string) $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alinea el payload con la definición de la plantilla (conteo y placeholders).
     *
     * @param  string  $templateName
     * @param  array<string, mixed>  $payloadParams
     * @return array<string, string>
     */
    public function alignBodyParametersForMeta($templateName, array $payloadParams)
    {
        $payloadParams = $this->stripInternalTemplateParamKeys($payloadParams);
        $expected = $this->getTemplateBodyParamNames($templateName);
        $placeholder = (string) config('meta_whatsapp.empty_body_parameter_placeholder', '—');

        // Si Graph no trae params, no descartar los del payload (evita body_param_count=0).
        if ($expected === []) {
            if ($payloadParams === []) {
                return [];
            }
            $aligned = [];
            foreach ($payloadParams as $name => $text) {
                $val = trim((string) $text);
                $aligned[(string) $name] = $val !== '' ? $val : $placeholder;
            }

            return $aligned;
        }

        $aligned = [];
        $positional = $this->usesPositionalParameters($templateName);
        $knownNamed = isset($this->knownBodyParamNames[$templateName])
            ? array_values($this->knownBodyParamNames[$templateName])
            : [];

        // Meta POSITIONAL {{1}}{{2}}… + payload nombrado (total_cotizacion, …).
        if ($positional && $knownNamed !== []) {
            $values = [];
            foreach ($knownNamed as $named) {
                $val = isset($payloadParams[$named]) ? trim((string) $payloadParams[$named]) : '';
                $values[] = $val !== '' ? $val : $placeholder;
            }
            foreach ($expected as $i => $name) {
                $aligned[(string) $name] = $values[$i] ?? $placeholder;
            }

            return $aligned;
        }

        foreach ($expected as $name) {
            $name = (string) $name;
            $val = isset($payloadParams[$name]) ? trim((string) $payloadParams[$name]) : '';
            $aligned[$name] = $val !== '' ? $val : $placeholder;
        }

        return $aligned;
    }

    /**
     * @param  string  $templateName
     * @return array<string, mixed>|null
     */
    private function findTemplateByName($templateName)
    {
        $list = $this->listTemplates();
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];

        foreach ($templates as $tpl) {
            if (is_array($tpl) && ($tpl['name'] ?? '') === $templateName) {
                return $tpl;
            }
        }

        return null;
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

        if (in_array($templateName, $this->knownImageHeaderTemplates, true)) {
            return 'IMAGE';
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
        $bodyText = '[' . $templateName . ']';
        $headerText = '';
        $found = false;
        foreach ($templates as $tpl) {
            if (!is_array($tpl) || ($tpl['name'] ?? '') !== $templateName) {
                continue;
            }
            $bodyText = isset($tpl['text']) ? (string) $tpl['text'] : $bodyText;
            $headerText = isset($tpl['header_text']) ? (string) $tpl['header_text'] : '';
            $found = true;
            break;
        }

        // Graph a veces no trae plantillas QA/_qa: usar fallback local.
        if (!$found) {
            foreach ($this->defaultTemplates() as $tpl) {
                if (!is_array($tpl) || ($tpl['name'] ?? '') !== $templateName) {
                    continue;
                }
                $bodyText = isset($tpl['text']) ? (string) $tpl['text'] : $bodyText;
                $headerText = isset($tpl['header_text']) ? (string) $tpl['header_text'] : '';
                break;
            }
        }

        $parts = [];
        if ($headerText !== '') {
            $parts[] = $headerText;
        }
        if ($bodyText !== '') {
            $parts[] = $bodyText;
        }
        $text = $parts === [] ? '[' . $templateName . ']' : implode("\n\n", $parts);

        foreach ($params as $key => $val) {
            if ($key === '_header' || is_array($val)) {
                continue;
            }
            $text = str_replace('{{' . $key . '}}', (string) $val, $text);
        }

        return $text;
    }

    /**
     * Texto del chat inbox: cuerpo de plantilla Meta (caché Graph) + parámetros del job.
     * Evita duplicar copy en CoordinacionWhatsappPayload / jobs.
     *
     * @param  string  $templateName
     * @param  array<string, mixed>  $params
     * @param  string|null  $fallback  Solo si la plantilla no está en caché (p. ej. texto legacy multilínea)
     * @return string
     */
    public function resolvePreviewText($templateName, array $params, $fallback = null)
    {
        $fromMeta = trim($this->buildPreviewBody($templateName, $params));
        $missingMarker = '[' . $templateName . ']';

        if (config('meta_whatsapp.coordinacion_inbox_preview_from_template', true)) {
            if ($fromMeta !== '' && $fromMeta !== $missingMarker) {
                return $fromMeta;
            }
        }

        $fb = trim((string) $fallback);

        return $fb !== '' ? $fb : ($fromMeta !== '' ? $fromMeta : $templateName);
    }

    /**
     * @return array<string, mixed>
     */
    public function listTemplates()
    {
        $cacheKey = 'wa_inbox_meta_templates_v3';

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
                    'header_text' => $parsed['header_text'],
                    'params' => $parsed['params'],
                    'param_defs' => $parsed['param_defs'],
                    'header_format' => $parsed['header_format'],
                    'parameter_format' => isset($item['parameter_format'])
                        ? (string) $item['parameter_format']
                        : null,
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
     * @return array{text: string, header_text: string, params: array<int, string>, param_defs: array<int, array<string, mixed>>, header_format: string|null}
     */
    private function parseTemplateComponents(array $components)
    {
        $bodyText = '';
        $headerText = '';
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
                if ($format === 'TEXT') {
                    $headerText = isset($comp['text']) ? (string) $comp['text'] : '';
                    if (preg_match_all('/\{\{([^}]+)\}\}/', $headerText, $m)) {
                        foreach ($m[1] as $paramName) {
                            if (!in_array($paramName, $params, true)) {
                                $params[] = $paramName;
                            }
                        }
                    }
                } elseif (in_array($format, ['DOCUMENT', 'IMAGE', 'VIDEO'], true)) {
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
            'header_text' => $headerText,
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
