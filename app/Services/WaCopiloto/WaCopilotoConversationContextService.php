<?php

namespace App\Services\WaCopiloto;

use App\Models\Copiloto\CopilotoFicha;
use App\Models\WaCopiloto\WaCopilotoConversation;
use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Support\WhatsApp\WaJsonUtf8;
use Carbon\Carbon;

/**
 * Arma contexto de chat acotado en tiempo y tokens para análisis IA.
 *
 * Capas:
 * 1) Meta local (conteos, ventana) — sin tokens extra
 * 2) Ficha previa compacta
 * 3) Resumen acumulado (reutilizado entre análisis)
 * 4) Mensajes antiguos comprimidos (1 línea c/u)
 * 5) Últimos N mensajes casi completos
 */
class WaCopilotoConversationContextService
{
    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  int  $currentMessageId
     * @return array{
     *   meta: string,
     *   previous_ficha: string,
     *   rolling_summary: string,
     *   compact_block: string,
     *   recent_block: string,
     *   stats: array<string, mixed>,
     *   previous_lead_score: int|null
     * }
     */
    public function buildForAnalysis(WaCopilotoConversation $conversation, $currentMessageId)
    {
        $days = max(1, min(90, (int) config('meta_whatsapp_copiloto.analysis_context_days', 14)));
        $recentLimit = max(4, min(16, (int) config('meta_whatsapp_copiloto.analysis_recent_messages', 8)));
        $compactLimit = max(0, min(12, (int) config('meta_whatsapp_copiloto.analysis_compact_messages', 6)));
        $maxChars = max(1200, min(8000, (int) config('meta_whatsapp_copiloto.analysis_max_context_chars', 3200)));
        $maxLineChars = max(80, min(500, (int) config('meta_whatsapp_copiloto.analysis_max_line_chars', 260)));

        $since = Carbon::now()->subDays($days);
        $messages = WaCopilotoMessage::query()
            ->where('conversation_id', (int) $conversation->id)
            ->where('id', '<=', (int) $currentMessageId)
            ->where('sent_at', '>=', $since)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get(['id', 'direction', 'body', 'message_type', 'sent_at']);

        $totalInWindow = $messages->count();
        $inboundCount = $messages->where('direction', 'in')->count();
        $outboundCount = $totalInWindow - $inboundCount;

        $lastInbound = $messages->where('direction', 'in')->last();
        $previousInbound = $messages
            ->where('direction', 'in')
            ->filter(function ($msg) use ($currentMessageId) {
                return (int) $msg->id < (int) $currentMessageId;
            })
            ->last();
        $hoursSinceInbound = null;
        if ($previousInbound && $previousInbound->sent_at) {
            $hoursSinceInbound = Carbon::parse($previousInbound->sent_at)->diffInHours(Carbon::now());
        } elseif ($lastInbound && (int) $lastInbound->id === (int) $currentMessageId && $lastInbound->sent_at) {
            $hoursSinceInbound = 0;
        }

        $recent = $messages->slice(max(0, $totalInWindow - $recentLimit))->values();
        $older = $messages->slice(0, max(0, $totalInWindow - $recentLimit))->values();

        $stats = [
            'days_window' => $days,
            'messages_in_window' => $totalInWindow,
            'inbound_count' => $inboundCount,
            'outbound_count' => $outboundCount,
            'hours_since_last_inbound' => $hoursSinceInbound,
        ];

        $meta = sprintf(
            'Ventana: últimos %d días · %d mensajes (%d cliente, %d asesor)%s',
            $days,
            $totalInWindow,
            $inboundCount,
            $outboundCount,
            $hoursSinceInbound !== null ? ' · último msg cliente hace ~' . $hoursSinceInbound . 'h' : ''
        );

        $phone = (string) $conversation->phone_e164;
        $latestFicha = $phone !== ''
            ? CopilotoFicha::query()
                ->where('phone', $phone)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first(['temperatura', 'nivel', 'objecion'])
            : null;

        $previousLeadScore = $conversation->ai_temperatura !== null
            ? (int) $conversation->ai_temperatura
            : ($latestFicha ? (int) $latestFicha->temperatura : null);

        $previousFicha = $this->formatPreviousFichaBlockFromModel($latestFicha, $previousLeadScore);

        $rollingSummary = trim((string) $conversation->ai_context_summary);
        if (mb_strlen($rollingSummary) > (int) config('meta_whatsapp_copiloto.analysis_summary_max_chars', 320)) {
            $rollingSummary = mb_substr($rollingSummary, 0, 317) . '…';
        }

        $compactLines = [];
        if ($compactLimit > 0 && $older->count() > 0) {
            $slice = $older->slice(max(0, $older->count() - $compactLimit));
            foreach ($slice as $msg) {
                $compactLines[] = $this->formatMessageLine($msg, (int) ($maxLineChars * 0.65));
            }
        }

        $recentLines = [];
        foreach ($recent as $msg) {
            $recentLines[] = $this->formatMessageLine($msg, $maxLineChars);
        }

        $compactBlock = $compactLines ? implode("\n", $compactLines) : '';
        $recentBlock = $recentLines ? implode("\n", $recentLines) : '';

        $budget = $maxChars - mb_strlen($meta) - mb_strlen($previousFicha) - 80;
        if ($rollingSummary !== '') {
            $budget -= mb_strlen($rollingSummary) + 20;
        }

        if ($budget > 0 && (mb_strlen($compactBlock) + mb_strlen($recentBlock)) > $budget) {
            $trimmed = $this->trimBlocksToBudget($compactBlock, $recentBlock, $budget);
            $compactBlock = $trimmed['compact'];
            $recentBlock = $trimmed['recent'];
        }

        return [
            'meta' => WaJsonUtf8::sanitizeString($meta),
            'previous_ficha' => WaJsonUtf8::sanitizeString($previousFicha),
            'rolling_summary' => WaJsonUtf8::sanitizeString($rollingSummary),
            'compact_block' => WaJsonUtf8::sanitizeString($compactBlock),
            'recent_block' => WaJsonUtf8::sanitizeString($recentBlock),
            'stats' => $stats,
            'previous_lead_score' => $previousLeadScore,
        ];
    }

    /**
     * Mezcla puntaje histórico con señales nuevas (EMA + decaimiento por inactividad).
     *
     * @param  int|null  $previous
     * @param  int|null  $geminiLead
     * @param  int|null  $messageScore
     * @param  int|null  $hoursSinceLastInbound
     * @return int
     */
    public function blendLeadScore($previous, $geminiLead, $messageScore, $hoursSinceLastInbound = null)
    {
        $prev = $previous !== null ? max(0, min(100, (int) $previous)) : null;
        $decayAfterDays = max(1, (int) config('meta_whatsapp_copiloto.analysis_score_decay_after_days', 7));
        $decayHours = $decayAfterDays * 24;

        if ($prev !== null && $hoursSinceLastInbound !== null && $hoursSinceLastInbound > $decayHours) {
            $daysOver = ($hoursSinceLastInbound - $decayHours) / 24;
            $factor = max(0.55, 1 - (0.05 * $daysOver));
            $prev = (int) round($prev * $factor);
        }

        $msg = $messageScore !== null ? max(0, min(100, (int) $messageScore)) : null;
        $gemini = $geminiLead !== null ? max(0, min(100, (int) $geminiLead)) : null;

        if ($msg === null && $gemini === null) {
            return $prev !== null ? $prev : 0;
        }

        $signal = null;
        if ($msg !== null && $gemini !== null) {
            $signal = (int) round(($msg + $gemini) / 2);
        } else {
            $signal = $msg !== null ? $msg : $gemini;
        }

        if ($prev === null) {
            return (int) $signal;
        }

        $alpha = (float) config('meta_whatsapp_copiloto.analysis_lead_score_ema_alpha', 0.4);
        $alpha = max(0.15, min(0.75, $alpha));

        return max(0, min(100, (int) round(($alpha * $signal) + ((1 - $alpha) * $prev))));
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  int  $leadScore
     * @param  string  $summary
     * @param  int  $throughMessageId
     */
    public function persistConversationAiState(WaCopilotoConversation $conversation, $leadScore, $summary, $throughMessageId)
    {
        $conversation->ai_temperatura = max(0, min(100, (int) $leadScore));
        $conversation->ai_temperatura_at = now();

        $summary = trim(WaJsonUtf8::sanitizeString((string) $summary));
        $maxSummary = max(120, (int) config('meta_whatsapp_copiloto.analysis_summary_max_chars', 320));
        if ($summary !== '') {
            if (mb_strlen($summary) > $maxSummary) {
                $summary = mb_substr($summary, 0, $maxSummary - 1) . '…';
            }
            $conversation->ai_context_summary = $summary;
            $conversation->ai_summary_through_message_id = (int) $throughMessageId;
        }

        $conversation->save();

        app(WaCopilotoCacheService::class)->invalidateSession((int) $conversation->session_id);
    }

    /**
     * @param  mixed  $message
     * @param  int  $maxChars
     * @return string
     */
    private function formatMessageLine($message, $maxChars)
    {
        $who = $message->direction === 'in' ? 'Cliente' : 'Asesor';
        $text = trim(WaJsonUtf8::sanitizeString((string) $message->body));
        if ($text === '') {
            $text = '[' . (string) $message->message_type . ']';
        }
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, max(1, $maxChars - 1)) . '…';
        }

        $time = $message->sent_at ? Carbon::parse($message->sent_at)->format('d/m H:i') : '';

        return $time !== '' ? ($time . ' ' . $who . ': ' . $text) : ($who . ': ' . $text);
    }

    /**
     * @param  string  $phone
     * @param  int|null  $fallbackScore
     * @return string
     */
    /**
     * @param  CopilotoFicha|null  $ficha
     * @param  int|null  $fallbackScore
     * @return string
     */
    private function formatPreviousFichaBlockFromModel($ficha, $fallbackScore)
    {
        if (!$ficha && $fallbackScore === null) {
            return 'Ficha previa: sin historial IA.';
        }

        $temp = $ficha ? (int) $ficha->temperatura : (int) $fallbackScore;
        $parts = ['temp=' . $temp . '/100'];
        if ($ficha && $ficha->nivel) {
            $parts[] = 'nivel=' . $ficha->nivel;
        }
        if ($ficha && $ficha->objecion) {
            $obj = trim((string) $ficha->objecion);
            if (mb_strlen($obj) > 90) {
                $obj = mb_substr($obj, 0, 87) . '…';
            }
            $parts[] = 'objeción=' . $obj;
        }

        return 'Ficha previa: ' . implode(' · ', $parts);
    }

    /**
     * @param  string  $compactBlock
     * @param  string  $recentBlock
     * @param  int  $budget
     * @return array{compact: string, recent: string}
     */
    private function trimBlocksToBudget($compactBlock, $recentBlock, $budget)
    {
        $recent = $recentBlock;
        $compact = $compactBlock;

        if (mb_strlen($recent) > $budget) {
            $recent = mb_substr($recent, max(0, mb_strlen($recent) - $budget));
            $compact = '';
        } else {
            $remaining = $budget - mb_strlen($recent);
            if (mb_strlen($compact) > $remaining) {
                $compact = mb_substr($compact, max(0, mb_strlen($compact) - $remaining));
            }
        }

        return [
            'compact' => trim($compact),
            'recent' => trim($recent),
        ];
    }
}
