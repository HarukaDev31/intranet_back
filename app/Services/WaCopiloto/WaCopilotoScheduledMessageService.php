<?php

namespace App\Services\WaCopiloto;

use App\Jobs\WaCopiloto\ProcessWaCopilotoScheduledMessageJob;
use App\Models\WaCopiloto\WaCopilotoConversation;
use App\Models\WaCopiloto\WaCopilotoScheduledMessage;
use App\Support\WhatsApp\WaCopilotoJobContext;
use Carbon\Carbon;

class WaCopilotoScheduledMessageService
{
    /** @var WaCopilotoWindowService */
    protected $windowService;

    public function __construct(WaCopilotoWindowService $windowService)
    {
        $this->windowService = $windowService;
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  int  $userId
     * @param  string  $body
     * @param  Carbon  $scheduledAt
     * @return array<string, mixed>
     */
    public function schedule(WaCopilotoConversation $conversation, $userId, $body, Carbon $scheduledAt)
    {
        $window = $this->windowService->computeWindowState($conversation);
        if (empty($window['can_send_text'])) {
            return [
                'success' => false,
                'message' => 'Ventana cerrada. Envía una plantilla para reactivar la conversación.',
            ];
        }

        $body = trim((string) $body);
        if ($body === '') {
            return [
                'success' => false,
                'message' => 'Escribe el mensaje a programar.',
            ];
        }

        $now = Carbon::now();
        if ($scheduledAt->lessThanOrEqualTo($now)) {
            return [
                'success' => false,
                'message' => 'La fecha y hora deben ser en el futuro.',
            ];
        }

        $expiresAt = !empty($window['expires_at']) ? Carbon::parse($window['expires_at']) : null;
        if ($expiresAt && $scheduledAt->greaterThanOrEqualTo($expiresAt)) {
            return [
                'success' => false,
                'message' => 'Ese horario queda fuera de la ventana de 24 h. Programa antes del cierre o usa una plantilla.',
            ];
        }

        $row = WaCopilotoScheduledMessage::query()->create([
            'conversation_id' => (int) $conversation->id,
            'session_id' => (int) $conversation->session_id,
            'created_by_user_id' => (int) $userId,
            'body' => $body,
            'message_type' => 'text',
            'template_params' => null,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        ProcessWaCopilotoScheduledMessageJob::dispatch(
            (int) $row->id,
            WaCopilotoJobContext::resolveJobDomain()
        )->delay($scheduledAt);

        return [
            'success' => true,
            'message' => 'Mensaje programado',
            'data' => $this->formatScheduled($row),
        ];
    }

    /**
     * @param  WaCopilotoScheduledMessage  $row
     * @return array<string, mixed>
     */
    public function formatScheduled(WaCopilotoScheduledMessage $row)
    {
        return [
            'id' => (int) $row->id,
            'conversation_id' => (int) $row->conversation_id,
            'body' => (string) $row->body,
            'message_type' => (string) $row->message_type,
            'scheduled_at' => $row->scheduled_at ? $row->scheduled_at->toIso8601String() : null,
            'status' => (string) $row->status,
            'failed_reason' => $row->failed_reason,
            'message_id' => $row->message_id ? (int) $row->message_id : null,
        ];
    }
}
