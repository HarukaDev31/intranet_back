<?php

namespace App\Services\WaCopiloto;

use App\Models\WaCopiloto\WaCopilotoConversation;
use Carbon\Carbon;

class WaCopilotoWindowService
{
    /**
     * @param  WaCopilotoConversation  $conversation
     * @return array{state: string, expires_at: string|null, label: string, can_send_text: bool}
     */
    public function computeWindowState(WaCopilotoConversation $conversation)
    {
        $last = $conversation->last_customer_message_at;
        if (!$last) {
            return [
                'state' => 'closed',
                'expires_at' => null,
                'label' => 'Ventana cerrada',
                'can_send_text' => false,
            ];
        }

        $expires = Carbon::parse($last)->addHours(24);
        $now = Carbon::now();

        if ($now->greaterThan($expires)) {
            return [
                'state' => 'closed',
                'expires_at' => $expires->toIso8601String(),
                'label' => 'Ventana cerrada',
                'can_send_text' => false,
            ];
        }

        $minutesLeft = $now->diffInMinutes($expires, false);
        if ($minutesLeft <= 60) {
            $hours = max(1, (int) ceil($minutesLeft / 60));

            return [
                'state' => 'warn',
                'expires_at' => $expires->toIso8601String(),
                'label' => 'Expira en ' . $minutesLeft . 'min',
                'can_send_text' => true,
            ];
        }

        $hoursLeft = (int) floor($minutesLeft / 60);

        return [
            'state' => 'open',
            'expires_at' => $expires->toIso8601String(),
            'label' => $hoursLeft . 'h restantes',
            'can_send_text' => true,
        ];
    }

    /**
     * Ventana de servicio Meta (24 h desde el último mensaje del cliente).
     * Requiere conversación existente en wa_copiloto_conversations.
     */
    public function isWindowOpenForPhone(string $phoneE164, int $sessionId): bool
    {
        $phoneE164 = preg_replace('/\D+/', '', $phoneE164);
        if ($phoneE164 === '' || $sessionId <= 0) {
            return false;
        }

        $conversation = WaCopilotoConversation::query()
            ->where('session_id', $sessionId)
            ->where('phone_e164', $phoneE164)
            ->first();

        if (!$conversation) {
            return false;
        }

        return (bool) ($this->computeWindowState($conversation)['can_send_text'] ?? false);
    }
}
