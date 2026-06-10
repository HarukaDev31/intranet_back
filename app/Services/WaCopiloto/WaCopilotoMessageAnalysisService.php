<?php

namespace App\Services\WaCopiloto;

use App\Events\WaCopiloto\WaCopilotoMessageInsightsReady;
use App\Models\Copiloto\CopilotoFicha;
use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Models\WaCopiloto\WaCopilotoMessageInsight;
use App\Services\CargaConsolidada\GeminiService;
use App\Services\Copiloto\CopilotoAduanaKnowledgeService;
use App\Services\Copiloto\CopilotoClienteComercialContextService;
use App\Support\WhatsApp\WaCopilotoLog;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;

class WaCopilotoMessageAnalysisService
{
    /** @var GeminiService */
    protected $gemini;

    /** @var WaCopilotoConversationService */
    protected $conversationService;

    /** @var WaCopilotoConversationContextService */
    protected $contextService;

    /** @var WaCopilotoSalesContextService */
    protected $salesContextService;

    /** @var CopilotoAduanaKnowledgeService */
    protected $aduanaKnowledgeService;

    /** @var WaCopilotoConsolidadoContextService */
    protected $consolidadoContextService;

    /** @var CopilotoClienteComercialContextService */
    protected $clienteComercialContextService;

    public function __construct(
        GeminiService $gemini,
        WaCopilotoConversationService $conversationService,
        WaCopilotoConversationContextService $contextService,
        WaCopilotoSalesContextService $salesContextService,
        CopilotoAduanaKnowledgeService $aduanaKnowledgeService,
        WaCopilotoConsolidadoContextService $consolidadoContextService,
        CopilotoClienteComercialContextService $clienteComercialContextService
    ) {
        $this->gemini = $gemini;
        $this->conversationService = $conversationService;
        $this->contextService = $contextService;
        $this->salesContextService = $salesContextService;
        $this->aduanaKnowledgeService = $aduanaKnowledgeService;
        $this->consolidadoContextService = $consolidadoContextService;
        $this->clienteComercialContextService = $clienteComercialContextService;
    }

    /**
     * Analiza un mensaje entrante del cliente con Gemini y emite insights por WS.
     *
     * @param  int  $messageId
     * @return array<string, mixed>|null
     */
    public function analyzeInboundMessage($messageId)
    {
        $dbConnection = DB::getDefaultConnection();

        WaCopilotoLog::info('analysis.start', [
            'message_id' => (int) $messageId,
            'db_connection' => $dbConnection,
        ]);

        $message = WaCopilotoMessage::query()->with('conversation')->find((int) $messageId);
        if (!$message || $message->direction !== 'in') {
            WaCopilotoLog::info('analysis.skipped_message', [
                'message_id' => (int) $messageId,
                'found' => (bool) $message,
                'direction' => $message ? (string) $message->direction : null,
            ]);

            return null;
        }

        if (WaCopilotoMessageInsight::query()->where('message_id', (int) $message->id)->exists()) {
            WaCopilotoLog::info('analysis.skipped_already_analyzed', [
                'message_id' => (int) $message->id,
            ]);

            return null;
        }

        $conversation = $message->conversation;
        if (!$conversation) {
            WaCopilotoLog::warning('analysis.skipped_no_conversation', [
                'message_id' => (int) $message->id,
            ]);

            return null;
        }

        if (!$this->conversationService->isPhoneAllowedForCopilotoAnalysis((string) $conversation->phone_e164)) {
            WaCopilotoLog::info('analysis.skipped_phone_not_allowed', [
                'message_id' => (int) $message->id,
                'phone_e164' => (string) $conversation->phone_e164,
            ]);

            return null;
        }

        $body = trim((string) $message->body);
        if ($body === '' && !in_array((string) $message->message_type, ['text', 'button'], true)) {
            $body = '[' . (string) $message->message_type . ']';
        }
        if ($body === '') {
            WaCopilotoLog::info('analysis.skipped_empty_body', [
                'message_id' => (int) $message->id,
                'message_type' => (string) $message->message_type,
            ]);

            return null;
        }

        $context = $this->contextService->buildForAnalysis($conversation, (int) $message->id);
        $context['phone_e164'] = (string) $conversation->phone_e164;

        $contactName = trim((string) $conversation->contact_name);
        if ($contactName === '') {
            $contactName = (string) $conversation->phone_e164;
        }

        $baseTokens = max(1024, min(8192, (int) config('meta_whatsapp_copiloto.analysis_gemini_max_output_tokens', 4096)));
        $schema = $this->geminiResponseSchema();
        $gemini = null;

        foreach ([true, false] as $includeWon) {
            $prompt = $this->buildPrompt($contactName, $context, $body, (string) $message->message_type, $includeWon);
            $tokenAttempts = [$baseTokens];
            if ($baseTokens < 8192) {
                $tokenAttempts[] = min(8192, max($baseTokens + 2048, 6144));
            }

            foreach ($tokenAttempts as $attemptIdx => $maxTokens) {
                WaCopilotoLog::info('analysis.gemini_request', [
                    'message_id' => (int) $message->id,
                    'conversation_id' => (int) $conversation->id,
                    'phone_e164' => (string) $conversation->phone_e164,
                    'max_tokens' => $maxTokens,
                    'prompt_chars' => mb_strlen($prompt),
                    'won_excerpts' => $includeWon,
                    'token_attempt' => $attemptIdx + 1,
                ]);

                $gemini = $this->gemini->analyzeTextAsJson($prompt, $maxTokens, 0.25, $schema);

                if (!empty($gemini['success']) && is_array($gemini['data'])) {
                    break 2;
                }

                $finishReason = isset($gemini['finish_reason']) ? (string) $gemini['finish_reason'] : null;
                $isTruncated = $finishReason === 'MAX_TOKENS';
                $hasMoreTokenAttempts = $attemptIdx < count($tokenAttempts) - 1;

                WaCopilotoLog::warning('analysis.gemini_failed', [
                    'message_id' => (int) $message->id,
                    'error' => isset($gemini['error']) ? (string) $gemini['error'] : 'unknown',
                    'finish_reason' => $finishReason,
                    'won_excerpts' => $includeWon,
                    'max_tokens' => $maxTokens,
                    'will_retry_more_tokens' => $isTruncated && $hasMoreTokenAttempts,
                    'will_retry_rules_only' => !$isTruncated && $includeWon,
                ]);

                if ($isTruncated && $hasMoreTokenAttempts) {
                    continue;
                }

                break;
            }

            if (!empty($gemini['success']) && is_array($gemini['data'])) {
                break;
            }

            if (!$includeWon) {
                return null;
            }
        }

        if (empty($gemini['success']) || !is_array($gemini['data'])) {
            return null;
        }

        $parsed = $gemini['data'];
        $items = $this->normalizeInsightItems($parsed, $body);
        if (empty($items)) {
            WaCopilotoLog::warning('analysis.empty_items_after_gemini', [
                'message_id' => (int) $message->id,
                'parsed_keys' => array_keys($parsed),
                'body_preview' => mb_substr($body, 0, 120),
            ]);

            return null;
        }

        WaCopilotoLog::info('analysis.gemini_ok', [
            'message_id' => (int) $message->id,
            'items_count' => count($items),
            'temperatura_mensaje' => isset($parsed['temperatura_mensaje']) ? $parsed['temperatura_mensaje'] : null,
            'temperatura_lead' => isset($parsed['temperatura_lead']) ? $parsed['temperatura_lead'] : null,
        ]);

        $phone = (string) $conversation->phone_e164;
        $temperaturaMensaje = $this->clampScore(isset($parsed['temperatura_mensaje']) ? $parsed['temperatura_mensaje'] : null);
        $geminiLead = $this->clampScore(isset($parsed['temperatura_lead']) ? $parsed['temperatura_lead'] : null);
        $temperaturaLead = $this->contextService->blendLeadScore(
            $context['previous_lead_score'],
            $geminiLead,
            $temperaturaMensaje,
            isset($context['stats']['hours_since_last_inbound']) ? $context['stats']['hours_since_last_inbound'] : null
        );
        $nivel = $this->resolveNivel(isset($parsed['nivel']) ? $parsed['nivel'] : null, $temperaturaLead);
        $senales = $this->normalizeSignals(isset($parsed['senales']) ? $parsed['senales'] : []);
        $objecion = trim((string) ($parsed['objecion'] ?? ''));
        $sugerencia = trim((string) ($parsed['sugerencia_principal'] ?? $parsed['sugerencia'] ?? $parsed['respuesta'] ?? ''));
        $resumenContexto = trim((string) ($parsed['resumen_contexto'] ?? ''));
        $etapa = trim((string) ($parsed['etapa'] ?? ''));
        $alerta = trim((string) ($parsed['alerta'] ?? ''));
        $intencion = trim((string) ($parsed['intencion'] ?? ''));

        $sugerenciaCorta = $sugerencia;
        if (mb_strlen($sugerenciaCorta) > 255) {
            $sugerenciaCorta = mb_substr($sugerenciaCorta, 0, 252) . '…';
        }

        $savedInsights = [];
        try {
            DB::transaction(function () use (
                $message,
                $conversation,
                $phone,
                $items,
                $temperaturaLead,
                $nivel,
                $senales,
                $objecion,
                $sugerencia,
                $sugerenciaCorta,
                $etapa,
                $alerta,
                $intencion,
                &$savedInsights
            ) {
                $sort = 0;
                foreach ($items as $item) {
                    $row = WaCopilotoMessageInsight::create([
                        'message_id' => (int) $message->id,
                        'conversation_id' => (int) $conversation->id,
                        'phone_e164' => $phone,
                        'kind' => (string) $item['kind'],
                        'label' => $this->truncateInsightLabel(isset($item['label']) ? $item['label'] : null),
                        'body' => (string) $item['body'],
                        'score' => isset($item['score']) ? (int) $item['score'] : null,
                        'sort_order' => $sort++,
                    ]);
                    $savedInsights[] = $this->formatInsight($row);
                }

                if ($etapa !== '') {
                    $row = WaCopilotoMessageInsight::create([
                        'message_id' => (int) $message->id,
                        'conversation_id' => (int) $conversation->id,
                        'phone_e164' => $phone,
                        'kind' => 'comentario',
                        'label' => 'Etapa',
                        'body' => $etapa,
                        'score' => null,
                        'sort_order' => $sort++,
                    ]);
                    $savedInsights[] = $this->formatInsight($row);
                }
                if ($intencion !== '') {
                    $row = WaCopilotoMessageInsight::create([
                        'message_id' => (int) $message->id,
                        'conversation_id' => (int) $conversation->id,
                        'phone_e164' => $phone,
                        'kind' => 'comentario',
                        'label' => 'Intención del cliente',
                        'body' => $intencion,
                        'score' => null,
                        'sort_order' => $sort++,
                    ]);
                    $savedInsights[] = $this->formatInsight($row);
                }
                if ($alerta !== '' && strtolower($alerta) !== 'null') {
                    $row = WaCopilotoMessageInsight::create([
                        'message_id' => (int) $message->id,
                        'conversation_id' => (int) $conversation->id,
                        'phone_e164' => $phone,
                        'kind' => 'comentario',
                        'label' => 'Alerta',
                        'body' => $alerta,
                        'score' => null,
                        'sort_order' => $sort++,
                    ]);
                    $savedInsights[] = $this->formatInsight($row);
                }

                CopilotoFicha::updateOrCreate(
                    ['phone' => $phone],
                    [
                        'temperatura' => (int) $temperaturaLead,
                        'nivel' => $nivel,
                        'senales' => $senales,
                        'objecion' => $objecion !== '' ? $objecion : null,
                        'sugerencia' => $sugerencia !== '' ? $sugerencia : null,
                        'sugerencia_corta' => $sugerenciaCorta !== '' ? $sugerenciaCorta : null,
                    ]
                );

                app(WaCopilotoCacheService::class)->invalidateAfterFichaWrite(
                    $phone,
                    (int) $conversation->session_id
                );
            });
        } catch (\Throwable $e) {
            WaCopilotoLog::error('analysis.persist_failed', [
                'message_id' => (int) $message->id,
                'conversation_id' => (int) $conversation->id,
                'phone_e164' => $phone,
                'db_connection' => $dbConnection,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        WaCopilotoLog::info('analysis.persist_ok', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'insights_saved' => count($savedInsights),
            'temperatura_lead' => $temperaturaLead,
        ]);

        try {
            $this->contextService->persistConversationAiState(
                $conversation,
                (int) $temperaturaLead,
                $resumenContexto,
                (int) $message->id
            );
        } catch (\Throwable $e) {
            WaCopilotoLog::warning('analysis.persistConversationAiState_failed', [
                'message_id' => (int) $message->id,
                'conversation_id' => (int) $conversation->id,
                'db_connection' => $dbConnection,
                'error' => $e->getMessage(),
            ]);
        }

        $fichaPayload = [
            'temperatura' => (int) $temperaturaLead,
            'nivel' => (string) $nivel,
            'senales' => $senales,
            'objecion' => $objecion !== '' ? $objecion : null,
            'sugerencia' => $sugerencia !== '' ? $sugerencia : null,
            'sugerencia_corta' => $sugerenciaCorta !== '' ? $sugerenciaCorta : null,
            'accion_sugerida' => $sugerenciaCorta !== '' ? $sugerenciaCorta : $sugerencia,
            'motivo' => $objecion !== '' ? $objecion : (count($senales) ? implode(' · ', array_slice($senales, 0, 2)) : null),
        ];

        $payload = [
            'conversation_id' => (int) $conversation->id,
            'message_id' => (int) $message->id,
            'phone_e164' => $phone,
            'temperatura_mensaje' => $temperaturaMensaje,
            'temperatura_lead' => $temperaturaLead,
            'insights' => $savedInsights,
            'ficha' => $fichaPayload,
        ];

        $this->broadcastInsightsReady($payload);

        WaCopilotoLog::info('analysis.complete', [
            'message_id' => (int) $message->id,
            'conversation_id' => (int) $conversation->id,
            'insights_count' => count($savedInsights),
            'temperatura_lead' => $temperaturaLead,
        ]);

        return $payload;
    }

    /**
     * @param  mixed  $label
     * @return string|null
     */
    protected function truncateInsightLabel($label)
    {
        if ($label === null || $label === '') {
            return null;
        }
        $text = trim((string) $label);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > 120) {
            return mb_substr($text, 0, 117) . '…';
        }

        return $text;
    }

    /**
     * @param  WaCopilotoMessageInsight  $insight
     * @return array<string, mixed>
     */
    public function formatInsight(WaCopilotoMessageInsight $insight)
    {
        return [
            'id' => (int) $insight->id,
            'message_id' => (int) $insight->message_id,
            'kind' => (string) $insight->kind,
            'label' => $insight->label,
            'body' => (string) $insight->body,
            'score' => $insight->score !== null ? (int) $insight->score : null,
        ];
    }

    /**
     * @param  array<int, WaCopilotoMessageInsight>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function formatInsightCollection($rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->formatInsight($row);
        }

        return $out;
    }

    /**
     * @param  string  $contactName
     * @param  array<string, mixed>  $context
     * @param  string  $latestBody
     * @param  string  $messageType
     * @return string
     */
    protected function buildPrompt($contactName, array $context, $latestBody, $messageType, $includeWonExcerpts = true)
    {
        $parts = [];
        $parts[] = 'Meta: ' . (string) $context['meta'];
        $parts[] = (string) $context['previous_ficha'];

        if (!empty($context['rolling_summary'])) {
            $parts[] = 'Resumen previo del hilo: ' . (string) $context['rolling_summary'];
        }
        if (!empty($context['compact_block'])) {
            $parts[] = "Mensajes anteriores (comprimidos):\n" . (string) $context['compact_block'];
        }
        if (!empty($context['recent_block'])) {
            $parts[] = "Mensajes recientes:\n" . (string) $context['recent_block'];
        }

        $threadText = (string) (($context['recent_block'] ?? '') . "\n" . ($context['compact_block'] ?? ''));
        $contextBlock = implode("\n\n", $parts);
        $salesKnowledge = $this->salesContextService->buildKnowledgeBlockForMessage(
            (string) $latestBody,
            $threadText,
            $includeWonExcerpts
        );
        $consolidadoKnowledge = $this->consolidadoContextService->buildUpcomingBlock();
        $clienteComercialKnowledge = $this->clienteComercialContextService->buildBlockForPhone(
            (string) ($context['phone_e164'] ?? '')
        );
        $aduanaKnowledge = $this->aduanaKnowledgeService->buildKnowledgeBlockForMessage(
            (string) $latestBody,
            $threadText
        );

        $prompt = 'Eres copiloto comercial de Probusiness (importaciones desde China / carga consolidada Perú). '
            . 'Analiza el último mensaje del cliente y sugiere la respuesta que enviaría un vendedor exitoso. '
            . 'Usa las reglas de negocio y el estilo/tácticas de los ejemplos WON (V:=vendedor, C:=cliente). '
            . 'En etapas documentos/cotizacion/cierre: aplica presión indirecta (urgencia suave, reservar espacio, corte próximo) como en los WON, sin ser agresivo. '
            . 'Si el cliente objeta precio ("caro", "sale más"): usa PERFIL COMERCIAL (tipo cliente, tarifas, descuento) — ofrece recotizar con tarifa preferencial si es RECURRENTE/ANTIGUO/PREMIUM y menciona descuento por fidelidad sin inventar montos. '
            . 'sugerencia_principal: mensaje WhatsApp corto listo para enviar (máx 350 caracteres). '
            . 'resumen_contexto: máx 200 caracteres. senales: máximo 2. items: exactamente 2 entradas (1 sugerencia + 1 comentario). '
            . 'No inventes montos totales en soles. Para tarifas USD/CBM usa SOLO PERFIL COMERCIAL. Para fechas de consolidado usa SOLO CONSOLIDADOS ACTIVOS. '
            . 'Si el producto mencionado aparece regulado en la base aduanera, indícalo en alerta y en la sugerencia. '
            . 'Confirmación formal: el cliente debe escribir "Si confirmo". '
            . 'Contacto: ' . $contactName . '. Tipo último mensaje: ' . $messageType . ".\n\n"
            . "=== CONOCIMIENTO DE NEGOCIO ===\n"
            . $salesKnowledge . "\n\n";

        if ($clienteComercialKnowledge !== '') {
            $prompt .= "=== PERFIL COMERCIAL CLIENTE ===\n"
                . $clienteComercialKnowledge . "\n\n";
        }

        if ($consolidadoKnowledge !== '') {
            $prompt .= "=== CONSOLIDADOS ACTIVOS (fechas reales) ===\n"
                . $consolidadoKnowledge . "\n\n";
        }

        if ($aduanaKnowledge !== '') {
            $prompt .= "=== BASE DE DATOS PRODUCTOS / REGULACIONES / PERMISOS ===\n"
                . $aduanaKnowledge . "\n\n";
        }

        $prompt .= "=== HILO ACTUAL ===\n"
            . $contextBlock . "\n\n"
            . 'Último mensaje del cliente (prioridad máxima):\n' . $latestBody;

        return $prompt;
    }

    /**
     * Schema estructurado para forzar JSON válido en Gemini.
     *
     * @return array<string, mixed>
     */
    protected function geminiResponseSchema()
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'temperatura_mensaje' => ['type' => 'INTEGER'],
                'temperatura_lead' => ['type' => 'INTEGER'],
                'nivel' => [
                    'type' => 'STRING',
                    'enum' => ['caliente', 'tibio', 'enfriando', 'frio'],
                ],
                'etapa' => [
                    'type' => 'STRING',
                    'enum' => ['calificacion', 'documentos', 'cotizacion', 'cierre', 'postventa'],
                ],
                'intencion' => ['type' => 'STRING'],
                'alerta' => ['type' => 'STRING'],
                'senales' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'objecion' => ['type' => 'STRING'],
                'sugerencia_principal' => ['type' => 'STRING'],
                'resumen_contexto' => ['type' => 'STRING'],
                'items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'tipo' => ['type' => 'STRING'],
                            'etiqueta' => ['type' => 'STRING'],
                            'texto' => ['type' => 'STRING'],
                            'puntaje' => ['type' => 'INTEGER'],
                        ],
                        'required' => ['tipo', 'texto'],
                    ],
                ],
            ],
            'required' => [
                'temperatura_mensaje',
                'temperatura_lead',
                'nivel',
                'etapa',
                'sugerencia_principal',
                'resumen_contexto',
                'items',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  string  $fallbackBody
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeInsightItems(array $parsed, $fallbackBody)
    {
        $items = [];
        $rawItems = isset($parsed['items']) && is_array($parsed['items']) ? $parsed['items'] : [];

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $tipo = strtolower(trim((string) ($raw['tipo'] ?? $raw['kind'] ?? '')));
            if ($tipo === 'temperatura') {
                $kind = 'temperatura';
            } elseif ($tipo === 'sugerencia') {
                $kind = 'sugerencia';
            } else {
                $kind = 'comentario';
            }

            $body = trim((string) ($raw['texto'] ?? $raw['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $items[] = [
                'kind' => $kind,
                'label' => trim((string) ($raw['etiqueta'] ?? $raw['label'] ?? '')),
                'body' => $body,
                'score' => $this->clampScore(isset($raw['puntaje']) ? $raw['puntaje'] : (isset($raw['score']) ? $raw['score'] : null)),
            ];
        }

        if (empty($items)) {
            $msgScore = $this->clampScore(isset($parsed['temperatura_mensaje']) ? $parsed['temperatura_mensaje'] : null);
            if ($msgScore !== null) {
                $items[] = [
                    'kind' => 'temperatura',
                    'label' => 'Temperatura del mensaje',
                    'body' => 'El mensaje del cliente refleja un interés estimado de ' . $msgScore . '/100.',
                    'score' => $msgScore,
                ];
            }

            $sug = trim((string) ($parsed['sugerencia_principal'] ?? $parsed['sugerencia'] ?? ''));
            if ($sug !== '') {
                $items[] = [
                    'kind' => 'sugerencia',
                    'label' => 'Sugerencia',
                    'body' => $sug,
                    'score' => null,
                ];
            }

            $obj = trim((string) ($parsed['objecion'] ?? ''));
            if ($obj !== '') {
                $items[] = [
                    'kind' => 'comentario',
                    'label' => 'Objeción detectada',
                    'body' => $obj,
                    'score' => null,
                ];
            } elseif ($fallbackBody !== '') {
                $items[] = [
                    'kind' => 'comentario',
                    'label' => 'Mensaje recibido',
                    'body' => $fallbackBody,
                    'score' => null,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  mixed  $value
     * @return int|null
     */
    protected function clampScore($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) round((float) $value);

        return max(0, min(100, $n));
    }

    /**
     * @param  mixed  $nivel
     * @param  int|null  $temperatura
     * @return string
     */
    protected function resolveNivel($nivel, $temperatura)
    {
        $n = strtolower(trim((string) $nivel));
        $allowed = ['caliente', 'tibio', 'enfriando', 'frio'];
        if (in_array($n, $allowed, true)) {
            return $n;
        }

        $t = $temperatura !== null ? $temperatura : 0;
        if ($t >= 70) {
            return 'caliente';
        }
        if ($t >= 40) {
            return 'tibio';
        }
        if ($t >= 20) {
            return 'enfriando';
        }

        return 'frio';
    }

    /**
     * @param  mixed  $senales
     * @return array<int, string>
     */
    protected function normalizeSignals($senales)
    {
        if (!is_array($senales)) {
            return [];
        }
        $out = [];
        foreach ($senales as $s) {
            $t = trim((string) $s);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function broadcastInsightsReady(array $payload)
    {
        if (!config('meta_whatsapp_copiloto.broadcast_enabled', true)) {
            return;
        }

        try {
            event(new WaCopilotoMessageInsightsReady($payload));
        } catch (BroadcastException $e) {
            WaCopilotoLog::warning('broadcastInsightsReady.failed', [
                'message_id' => (int) ($payload['message_id'] ?? 0),
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            WaCopilotoLog::warning('broadcastInsightsReady.failed', [
                'message_id' => (int) ($payload['message_id'] ?? 0),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
