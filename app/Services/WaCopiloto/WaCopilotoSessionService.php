<?php

namespace App\Services\WaCopiloto;

use App\Models\WaCopiloto\WaCopilotoSession;

class WaCopilotoSessionService
{
    /**
     * @param  string|null  $slug
     * @return WaCopilotoSession
     */
    public function ensureDefaultSession($slug = null)
    {
        $slug = trim((string) ($slug ?: config('meta_whatsapp_copiloto.default_session_slug', 'ventas')));
        if ($slug === '') {
            $slug = 'ventas';
        }

        $session = WaCopilotoSession::query()->where('slug', $slug)->where('is_active', true)->first();
        if ($session) {
            return $session;
        }

        $phoneNumberId = (string) config('meta_whatsapp_copiloto.phone_number_id');
        if ($phoneNumberId === '') {
            throw new \RuntimeException('META_WHATSAPP_COPILOTO_PHONE_NUMBER_ID no configurado');
        }

        return WaCopilotoSession::create([
            'slug' => $slug,
            'phone_number_id' => $phoneNumberId,
            'display_number' => (string) config('meta_whatsapp_copiloto.display_number', ''),
            'label' => 'Copiloto Ventas',
            'is_active' => true,
        ]);
    }

    /**
     * @param  string|null  $slug
     * @return array<string, mixed>
     */
    public function getSessionPayload($slug = null)
    {
        $session = $this->ensureDefaultSession($slug);

        return [
            'id' => (int) $session->id,
            'slug' => (string) $session->slug,
            'phone_number_id' => $session->phone_number_id,
            'display_number' => $session->display_number ?: $session->phone_number_id,
            'label' => $session->label,
            'waba_id' => $session->waba_id,
            'template_name_prefix' => $session->template_name_prefix,
            'is_active' => (bool) $session->is_active,
            'last_webhook_at' => $session->last_webhook_at,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveSessions()
    {
        return WaCopilotoSession::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (WaCopilotoSession $session) {
                return [
                    'id' => (int) $session->id,
                    'slug' => (string) $session->slug,
                    'phone_number_id' => $session->phone_number_id,
                    'display_number' => $session->display_number ?: $session->phone_number_id,
                    'label' => $session->label,
                    'waba_id' => $session->waba_id,
                    'template_name_prefix' => $session->template_name_prefix,
                    'is_active' => (bool) $session->is_active,
                    'last_webhook_at' => $session->last_webhook_at,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  string  $phoneNumberId
     * @return WaCopilotoSession|null
     */
    public function findByPhoneNumberId($phoneNumberId)
    {
        return WaCopilotoSession::query()
            ->where('phone_number_id', (string) $phoneNumberId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  string  $slug
     * @return WaCopilotoSession|null
     */
    public function findBySlug($slug)
    {
        return WaCopilotoSession::query()
            ->where('slug', (string) $slug)
            ->where('is_active', true)
            ->first();
    }
}
