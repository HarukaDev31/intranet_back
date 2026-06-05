<?php

namespace App\Services\WaCopiloto;

use App\Models\WaCopiloto\WaCopilotoConversation;
use App\Models\WaCopiloto\WaCopilotoSuggestionUsage;
use App\Support\WhatsApp\WaJsonUtf8;

class WaCopilotoSuggestionUsageService
{
    /**
     * @param  int  $conversationId
     * @param  int  $limit
     * @return array<string, mixed>
     */
    public function listForConversation($conversationId, $limit = 30)
    {
        $rows = WaCopilotoSuggestionUsage::query()
            ->where('conversation_id', (int) $conversationId)
            ->orderByDesc('id')
            ->limit(max(1, min(100, (int) $limit)))
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->formatRow($row);
        }

        return [
            'success' => true,
            'data' => WaJsonUtf8::sanitize($data),
        ];
    }

    /**
     * @param  int  $conversationId
     * @param  array<string, mixed>  $params
     * @param  int|null  $userId
     * @return array<string, mixed>
     */
    public function record($conversationId, array $params, $userId = null)
    {
        WaCopilotoConversation::query()->findOrFail((int) $conversationId);

        $outcome = strtolower(trim((string) ($params['outcome'] ?? '')));
        if (!in_array($outcome, ['used', 'modified', 'ignored'], true)) {
            return [
                'success' => false,
                'message' => 'Resultado inválido',
            ];
        }

        $suggested = WaJsonUtf8::sanitizeString(trim((string) ($params['suggested_text'] ?? '')));
        if ($suggested === '') {
            return [
                'success' => false,
                'message' => 'Texto sugerido requerido',
            ];
        }

        $final = WaJsonUtf8::sanitizeString(trim((string) ($params['final_text'] ?? '')));

        $row = WaCopilotoSuggestionUsage::create([
            'conversation_id' => (int) $conversationId,
            'message_id' => !empty($params['message_id']) ? (int) $params['message_id'] : null,
            'insight_id' => !empty($params['insight_id']) ? (int) $params['insight_id'] : null,
            'user_id' => $userId ? (int) $userId : null,
            'outcome' => $outcome,
            'suggested_text' => $suggested,
            'final_text' => $final !== '' ? $final : null,
        ]);

        return [
            'success' => true,
            'data' => WaJsonUtf8::sanitize($this->formatRow($row)),
        ];
    }

    /**
     * @param  WaCopilotoSuggestionUsage  $row
     * @return array<string, mixed>
     */
    private function formatRow(WaCopilotoSuggestionUsage $row)
    {
        return [
            'id' => (int) $row->id,
            'conversation_id' => (int) $row->conversation_id,
            'message_id' => $row->message_id ? (int) $row->message_id : null,
            'insight_id' => $row->insight_id ? (int) $row->insight_id : null,
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'outcome' => (string) $row->outcome,
            'suggested_text' => (string) $row->suggested_text,
            'final_text' => $row->final_text,
            'created_at' => $row->created_at,
        ];
    }
}
