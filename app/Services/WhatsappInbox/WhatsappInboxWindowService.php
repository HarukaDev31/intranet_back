<?php

namespace App\Services\WhatsappInbox;

use App\Models\WhatsappInbox\WaInboxConversation;
use Carbon\Carbon;

class WhatsappInboxWindowService
{
    /**
     * @param  WaInboxConversation  $conversation
     * @return array{state: string, expires_at: string|null, label: string, can_send_text: bool}
     */
    public function computeWindowState(WaInboxConversation $conversation)
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
}
