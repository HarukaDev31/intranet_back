<?php

namespace App\Services\WaCopiloto;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaCopilotoTemplateService
{
    /** @var WaCopilotoSessionService */
    protected $sessionService;

    public function __construct(WaCopilotoSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Plantillas Copiloto con encabezado DOCUMENT (fallback si Graph no trae components).
     *
     * @var array<int, string>
     */
    private $knownDocumentHeaderTemplates = [];

    /**
     * Fallback ventas / Copiloto (no plantillas operativas de coordinación).
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates()
    {
        return $this->withParamDefs([
            [
                'name' => 'pb_ventas_bienvenida_v1',
                'label' => 'Ventas — Bienvenida',
                'language' => 'es_PE',
                'text' => 'Hola 👋 {{nombre_cliente}}, gracias por escribirnos. Soy {{nombre_asesor}} de ProBusiness. ¿En qué producto o importación te puedo ayudar hoy?',
                'params' => ['nombre_cliente', 'nombre_asesor'],
            ],
            [
                'name' => 'pb_copiloto_seguimiento_v1',
                'label' => 'Copiloto — Seguimiento cotización',
                'language' => 'es_PE',
                'text' => "Hola {{nombre_cliente}} 👋\n\nTe escribo para dar seguimiento a tu cotización {{referencia}}. ¿Tienes alguna duda o avanzamos con el siguiente paso?",
                'params' => ['nombre_cliente', 'referencia'],
            ],
            [
                'name' => 'pb_ventas_recordatorio_v1',
                'label' => 'Ventas — Recordatorio',
                'language' => 'es_PE',
                'text' => "📩 Recordatorio:\n\n{{mensaje}}\n\n🙌",
                'params' => ['mensaje'],
            ],
        ]);
    }

    /**
     * @param  string  $templateName
     * @param  string|null  $sessionSlug
     * @return string|null DOCUMENT | IMAGE | VIDEO | null
     */
    public function getTemplateHeaderFormat($templateName, $sessionSlug = null)
    {
        $list = $this->listTemplates($sessionSlug);
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

    /**
     * @param  string  $templateName
     * @param  string|null  $sessionSlug
     * @return bool
     */
    public function templateRequiresHeaderMedia($templateName, $sessionSlug = null)
    {
        if ($this->getTemplateHeaderFormat($templateName, $sessionSlug) !== null) {
            return true;
        }

        $list = $this->listTemplates($sessionSlug);
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
     * @param  string  $templateName
     * @param  array<string, mixed>  $params
     * @param  string|null  $sessionSlug
     * @return string
     */
    public function buildPreviewBody($templateName, array $params, $sessionSlug = null)
    {
        $list = $this->listTemplates($sessionSlug);
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];
        $bodyText = '[' . $templateName . ']';
        $headerText = '';
        foreach ($templates as $tpl) {
            if (!is_array($tpl) || ($tpl['name'] ?? '') !== $templateName) {
                continue;
            }
            $bodyText = isset($tpl['text']) ? (string) $tpl['text'] : $bodyText;
            $headerText = isset($tpl['header_text']) ? (string) $tpl['header_text'] : '';
            break;
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
     * @param  string  $templateName
     * @param  array<string, mixed>  $params
     * @param  string|null  $fallback
     * @param  string|null  $sessionSlug
     * @return string
     */
    public function resolvePreviewText($templateName, array $params, $fallback = null, $sessionSlug = null)
    {
        $fromMeta = trim($this->buildPreviewBody($templateName, $params, $sessionSlug));
        $missingMarker = '[' . $templateName . ']';

        if (config('meta_whatsapp_copiloto.preview_from_template', true)) {
            if ($fromMeta !== '' && $fromMeta !== $missingMarker) {
                return $fromMeta;
            }
        }

        $fb = trim((string) $fallback);

        return $fb !== '' ? $fb : ($fromMeta !== '' ? $fromMeta : $templateName);
    }

    /**
     * @param  string|null  $sessionSlug
     * @return array<string, mixed>
     */
    public function listTemplates($sessionSlug = null)
    {
        $ctx = $this->resolveLineContext($sessionSlug);
        $cacheKey = 'wa_copiloto_meta_templates_v3_' . $ctx['cache_suffix'];

        $templates = Cache::remember($cacheKey, 3600, function () use ($ctx) {
            $raw = $this->fetchFromMetaOrDefault($ctx['waba_id']);

            return $this->filterTemplatesForLine(
                $raw,
                $ctx['prefixes'],
                $ctx['exclude_prefixes'],
                $ctx['exclude_only']
            );
        });

        return [
            'success' => true,
            'data' => $templates,
        ];
    }

    /**
     * @param  string  $templateName
     * @param  string|null  $sessionSlug
     * @return void
     */
    public function assertTemplateAllowed($templateName, $sessionSlug = null)
    {
        $list = $this->listTemplates($sessionSlug);
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];

        foreach ($templates as $tpl) {
            if (is_array($tpl) && ($tpl['name'] ?? '') === $templateName) {
                return;
            }
        }

        throw new \InvalidArgumentException('La plantilla no está disponible para esta línea de Copiloto.');
    }

    /**
     * @param  string|null  $sessionSlug
     * @return array{waba_id: string, prefixes: array<int, string>, exclude_prefixes: array<int, string>, exclude_only: bool, cache_suffix: string}
     */
    private function resolveLineContext($sessionSlug = null)
    {
        $session = null;
        if ($sessionSlug !== null && trim((string) $sessionSlug) !== '') {
            $session = $this->sessionService->findBySlug($sessionSlug);
        }
        if ($session === null) {
            try {
                $session = $this->sessionService->ensureDefaultSession($sessionSlug);
            } catch (\Exception $e) {
                $session = null;
            }
        }

        $wabaId = '';
        if ($session !== null && !empty($session->waba_id)) {
            $wabaId = (string) $session->waba_id;
        } else {
            $wabaId = (string) config('meta_whatsapp_copiloto.waba_id');
        }

        $prefixes = config('meta_whatsapp_copiloto.template_prefixes', []);
        if (!is_array($prefixes)) {
            $prefixes = [];
        }
        if ($session !== null && !empty($session->template_name_prefix)) {
            $prefixes = [(string) $session->template_name_prefix];
        }

        $excludePrefixes = config('meta_whatsapp_copiloto.template_exclude_prefixes', []);
        if (!is_array($excludePrefixes)) {
            $excludePrefixes = [];
        }

        $excludeOnly = (bool) config('meta_whatsapp_copiloto.template_filter_exclude_only', true);

        $cacheSuffix = md5(
            $wabaId . '|'
            . implode(',', $prefixes) . '|'
            . implode(',', $excludePrefixes) . '|'
            . ($excludeOnly ? '1' : '0')
        );

        return [
            'waba_id' => $wabaId,
            'prefixes' => $prefixes,
            'exclude_prefixes' => $excludePrefixes,
            'exclude_only' => $excludeOnly,
            'cache_suffix' => $cacheSuffix,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @param  array<int, string>  $prefixes
     * @param  array<int, string>  $excludePrefixes
     * @param  bool  $excludeOnly
     * @return array<int, array<string, mixed>>
     */
    private function filterTemplatesForLine(array $templates, array $prefixes, array $excludePrefixes, $excludeOnly)
    {
        $filtered = array_values(array_filter($templates, function ($tpl) use ($prefixes, $excludePrefixes, $excludeOnly) {
            if (!is_array($tpl)) {
                return false;
            }

            $name = strtolower((string) ($tpl['name'] ?? ''));
            if ($name === '') {
                return false;
            }

            foreach ($excludePrefixes as $ex) {
                $ex = strtolower(trim((string) $ex));
                if ($ex !== '' && strpos($name, $ex) === 0) {
                    return false;
                }
            }

            $activePrefixes = array_values(array_filter(array_map(function ($pfx) {
                return strtolower(trim((string) $pfx));
            }, $prefixes)));

            if ($activePrefixes === []) {
                return $excludeOnly;
            }

            foreach ($activePrefixes as $pfx) {
                if ($pfx !== '' && strpos($name, $pfx) === 0) {
                    return true;
                }
            }

            return false;
        }));

        if ($filtered !== []) {
            return $filtered;
        }

        $nonEmptyPrefixes = array_values(array_filter(array_map(function ($pfx) {
            return trim((string) $pfx);
        }, $prefixes)));

        if ($excludeOnly && $nonEmptyPrefixes !== []) {
            return $this->filterTemplatesForLine($this->defaultTemplates(), $nonEmptyPrefixes, $excludePrefixes, false);
        }

        return $this->defaultTemplates();
    }

    /**
     * @param  string  $wabaId
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromMetaOrDefault($wabaId)
    {
        $token = (string) config('meta_whatsapp_copiloto.access_token');
        if ($wabaId === '' || $token === '') {
            return $this->defaultTemplates();
        }

        $version = (string) config('meta_whatsapp_copiloto.graph_api_version', 'v19.0');
        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->acceptJson()
                ->get($url, ['limit' => 100]);

            if (!$response->successful()) {
                Log::warning('WaCopilotoTemplate: Graph list failed', [
                    'waba_id' => $wabaId,
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
                ];
            }

            if ($out === []) {
                return $this->defaultTemplates();
            }

            return $out;
        } catch (\Exception $e) {
            Log::warning('WaCopilotoTemplate: exception', ['message' => $e->getMessage()]);

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
