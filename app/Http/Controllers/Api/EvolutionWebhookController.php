<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Copiloto\CopilotoMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookController extends Controller
{
    /** @var CopilotoMessageService */
    protected $copilotoMessageService;

    public function __construct(CopilotoMessageService $copilotoMessageService)
    {
        $this->copilotoMessageService = $copilotoMessageService;
    }

    public function handle(Request $request)
    {
        if (!filter_var(env('COPILOTO_EVOLUTION_WEBHOOK_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => true,
                'message' => 'Evolution webhook deshabilitado (reservado Copiloto ventas).',
            ]);
        }

        $payload = $request->all();

        Log::info('Evolution webhook recibido', [
            'payload' => $payload,
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'ts' => now()->toISOString(),
        ]);

        $phone = $this->extractPhone($payload);
        $body = $this->extractBody($payload);
        $bitrixMsgId = $this->extractMessageId($payload);
        $direction = $this->extractDirection($payload);
        $linea = $this->extractLinea($payload);
        $sentAt = $this->extractSentAt($payload);
        $bitrixChatId = $this->extractChatId($payload);

        if ($phone) {
            $this->copilotoMessageService->persistMessage([
                'phone' => $phone,
                'bitrix_chat_id' => $bitrixChatId,
                'bitrix_msg_id' => $bitrixMsgId ?: ('evolution_' . md5($phone . '|' . ($sentAt ?: now()) . '|' . ($body ?: ''))),
                'direction' => $direction,
                'body' => $body,
                'source' => 'evolution',
                'linea' => $linea,
                'sent_at' => $sentAt ?: now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook procesado',
        ]);
    }

    private function extractPhone(array $payload)
    {
        $candidates = [
            data_get($payload, 'data.key.remoteJid'),
            data_get($payload, 'data.phone'),
            data_get($payload, 'phone'),
            data_get($payload, 'sender'),
        ];

        foreach ($candidates as $raw) {
            if (!$raw) {
                continue;
            }
            $clean = preg_replace('/\D+/', '', (string) $raw);
            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
    }

    private function extractBody(array $payload)
    {
        return data_get($payload, 'data.message.conversation')
            ?: data_get($payload, 'data.message.extendedTextMessage.text')
            ?: data_get($payload, 'message')
            ?: null;
    }

    private function extractMessageId(array $payload)
    {
        return data_get($payload, 'data.key.id')
            ?: data_get($payload, 'id')
            ?: null;
    }

    private function extractDirection(array $payload)
    {
        $fromMe = data_get($payload, 'data.key.fromMe');
        if ($fromMe === true || $fromMe === 1 || $fromMe === '1') {
            return 'out';
        }
        return 'in';
    }

    private function extractLinea(array $payload)
    {
        $lineId = data_get($payload, 'line_id')
            ?: data_get($payload, 'data.line_id')
            ?: data_get($payload, 'instance.line_id');

        if ((string) $lineId === (string) env('BITRIX_LINEA_VENTAS_CC')) {
            return 'ventas_cc';
        }
        if ((string) $lineId === (string) env('BITRIX_LINEA_CONSOLIDADO')) {
            return 'consolidado';
        }
        if ((string) $lineId === (string) env('BITRIX_LINEA_FACTURACION')) {
            return 'facturacion';
        }
        if ((string) $lineId === (string) env('BITRIX_LINEA_AYUDA')) {
            return 'ayuda';
        }

        return null;
    }

    private function extractSentAt(array $payload)
    {
        $ts = data_get($payload, 'data.messageTimestamp')
            ?: data_get($payload, 'timestamp')
            ?: null;

        if (!$ts) {
            return null;
        }

        if (is_numeric($ts)) {
            return date('Y-m-d H:i:s', (int) $ts);
        }

        return $ts;
    }

    private function extractChatId(array $payload)
    {
        return data_get($payload, 'data.key.remoteJid')
            ?: data_get($payload, 'chat_id')
            ?: null;
    }
}

