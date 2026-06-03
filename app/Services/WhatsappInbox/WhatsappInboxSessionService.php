<?php

namespace App\Services\WhatsappInbox;

use App\Models\WhatsappInbox\WaInboxSession;

class WhatsappInboxSessionService
{
    /**
     * @return WaInboxSession
     */
    public function ensureDefaultSession()
    {
        $phoneNumberId = (string) config('meta_whatsapp.phone_number_id');
        if ($phoneNumberId === '') {
            throw new \RuntimeException('META_WHATSAPP_PHONE_NUMBER_ID no configurado');
        }

        $session = WaInboxSession::query()->where('phone_number_id', $phoneNumberId)->first();
        if ($session) {
            return $session;
        }

        return WaInboxSession::create([
            'phone_number_id' => $phoneNumberId,
            'display_number' => (string) config('meta_whatsapp.inbox_display_number', ''),
            'label' => 'Coordinación',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionPayload()
    {
        $session = $this->ensureDefaultSession();

        return [
            'id' => (int) $session->id,
            'phone_number_id' => $session->phone_number_id,
            'display_number' => $session->display_number ?: $session->phone_number_id,
            'label' => $session->label,
            'is_active' => (bool) $session->is_active,
            'last_webhook_at' => $session->last_webhook_at,
        ];
    }

    /**
     * @param  string  $phoneNumberId
     * @return WaInboxSession|null
     */
    public function findByPhoneNumberId($phoneNumberId)
    {
        return WaInboxSession::query()
            ->where('phone_number_id', (string) $phoneNumberId)
            ->where('is_active', true)
            ->first();
    }
}
