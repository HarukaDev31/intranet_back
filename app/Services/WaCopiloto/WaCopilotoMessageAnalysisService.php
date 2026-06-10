<?php

namespace App\Services\WaCopiloto;

use App\Events\WaCopiloto\WaCopilotoMessageInsightsReady;
use App\Models\Copiloto\CopilotoFicha;
use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Models\WaCopiloto\WaCopilotoMessageInsight;
use App\Services\CargaConsolidada\GeminiService;
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

    public function __construct(
        GeminiService $gemini,
        WaCopilotoConversationService $conversationService,
        WaCopilotoConversationContextService $contextService,
        WaCopilotoSalesContextService $salesContextService
    ) {
        $this->gemini = $gemini;
        $this->conversationService = $conversationService;
        $this->contextService = $contextService;
        $this->salesContextService = $salesContextService;
    }

    /**
     * Analiza un mensaje entrante del cliente con Gemini y emite insights por WS.
     *
     * @param  int  $messageId
     * @return array<string, mixed>|null
     */
    public function analyzeInboundMessage($messageId)
    {
        $message = WaCopilotoMessage::query()->with('conversation')->find((int) $messageId);
        if (!$message || $message->direction !== 'in') {
            return null;
        }

        if (WaCopilotoMessageInsight::query()->where('message_id', (int) $message->id)->exists()) {
            return null;
        }

        $conversation = $message->conversation;
        if (!$conversation) {
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
            return null;
        }

        $context = $this->contextService->buildForAnalysis($conversation, (int) $message->id);

        $contactName = trim((string) $conversation->contact_name);
        if ($contactName === '') {
            $contactName = (string) $conversation->phone_e164;
        }

        $prompt = $this->buildPrompt($contactName, $context, $body, (string) $message->message_type);
        $maxTokens = max(512, min(4096, (int) config('meta_whatsapp_copiloto.analysis_gemini_max_output_tokens', 1024)));
        $gemini = $this->gemini->analyzeTextAsJson($prompt, $maxTokens, 0.25);

        if (empty($gemini['success']) || !is_array($gemini['data'])) {
            WaCopilotoLog::warning('analysis.gemini_failed', [
                'message_id' => (int) $message->id,
                'error' => isset($gemini['error']) ? (string) $gemini['error'] : 'unknown',
            ]);

            return null;
        }

        $parsed = $gemini['data'];
        $items = $this->normalizeInsightItems($parsed, $body);
        if (empty($items)) {
            return null;
        }

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

        $savedInsights = [];
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
            $resumenContexto,
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
                    'label' => isset($item['label']) ? (string) $item['label'] : null,
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

            $sugerenciaCorta = $sugerencia;
            if (mb_strlen($sugerenciaCorta) > 255) {
                $sugerenciaCorta = mb_substr($sugerenciaCorta, 0, 252) . '…';
            }

            $ficha = CopilotoFicha::updateOrCreate(
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

            $this->contextService->persistConversationAiState(
                $conversation,
                (int) $temperaturaLead,
                $resumenContexto,
                (int) $message->id
            );
        });

        $ficha = CopilotoFicha::query()->where('phone', $phone)->first();

        $fichaPayload = [
            'temperatura' => (int) $temperaturaLead,
            'nivel' => (string) $nivel,
            'senales' => $senales,
            'objecion' => $objecion !== '' ? $objecion : null,
            'sugerencia' => $sugerencia !== '' ? $sugerencia : null,
            'sugerencia_corta' => $ficha ? $ficha->sugerencia_corta : null,
            'accion_sugerida' => $ficha && $ficha->sugerencia_corta ? $ficha->sugerencia_corta : $sugerencia,
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

        return $payload;
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
    protected function buildPrompt($contactName, array $context, $latestBody, $messageType)
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

        $contextBlock = implode("\n\n", $parts);
        $salesKnowledge = $this->salesContextService->buildKnowledgeBlock();

        return 'Eres copiloto comercial de Probusiness (importaciones desde China / carga consolidada Perú). '
            . 'Tu trabajo: analizar el último mensaje del cliente y sugerir la respuesta EXACTA que enviaría un vendedor exitoso, '
            . 'basándote en las reglas de negocio y en el estilo de las conversaciones WON (V: = vendedor, C: = cliente). '
            . 'Responde ÚNICAMENTE JSON válido con esta estructura: '
            . '{"temperatura_mensaje":0-100,"temperatura_lead":0-100,"nivel":"caliente|tibio|enfriando|frio",'
            . '"etapa":"calificacion|documentos|cotizacion|cierre|postventa",'
            . '"intencion":"qué quiere el cliente en este mensaje",'
            . '"alerta":"restricción o riesgo comercial, o null",'
            . '"senales":["texto corto"],"objecion":"texto o null",'
            . '"sugerencia_principal":"texto listo para copiar y enviar al cliente por WhatsApp (tono Probusiness)",'
            . '"resumen_contexto":"máx 280 chars, estado del lead y temas clave del hilo para próximos análisis",'
            . '"items":[{"tipo":"temperatura|comentario|sugerencia","etiqueta":"texto","texto":"texto","puntaje":0-100|null}]}. '
            . 'Reglas: items debe tener AL MENOS 2 entradas; sugerencia_principal debe ser mensaje WhatsApp real (no metainstrucciones); '
            . 'usa frases y políticas del bloque de conocimiento; no inventes montos ni fechas no presentes en el hilo; '
            . 'si aplica confirmación, recuerda que debe decir "Si confirmo"; '
            . 'temperatura_mensaje = solo el último mensaje; temperatura_lead = estimación global del lead; '
            . 'textos en español. '
            . 'Contacto: ' . $contactName . '. '
            . 'Tipo último mensaje: ' . $messageType . ".\n\n"
            . "=== CONOCIMIENTO DE NEGOCIO Y VENTAS WON ===\n"
            . $salesKnowledge . "\n\n"
            . "=== HILO ACTUAL (ventana temporal) ===\n"
            . $contextBlock . "\n\n"
            . 'Último mensaje a analizar (prioridad máxima):\n' . $latestBody;
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
