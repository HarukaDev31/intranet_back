<?php

namespace App\Services\WhatsappInbox;

use App\Models\Organizacion;
use App\Models\Usuario;
use App\Models\WhatsappInbox\WaInboxSession;

class WhatsappInboxSessionService
{
    /** @var WhatsappInboxOrgConfigService */
    protected $orgConfig;

    public function __construct(WhatsappInboxOrgConfigService $orgConfig)
    {
        $this->orgConfig = $orgConfig;
    }

    /**
     * Sesión de org 1 (jobs de coordinación históricos).
     *
     * @return WaInboxSession
     */
    public function ensureDefaultSession()
    {
        return $this->ensureSessionForOrganizacion(Usuario::ID_ORGANIZACION_ADMIN);
    }

    /**
     * @param  int  $organizacionId
     * @return WaInboxSession
     */
    public function ensureSessionForOrganizacion($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        $creds = $this->orgConfig->credentials($organizacionId);
        $phoneNumberId = (string) $creds['phone_number_id'];
        if ($phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp no está configurado para esta organización');
        }

        $session = WaInboxSession::query()
            ->where('organizacion_id', $organizacionId)
            ->first();
        if (!$session) {
            $session = WaInboxSession::query()->where('phone_number_id', $phoneNumberId)->first();
        }
        if ($session) {
            $dirty = false;
            if ((int) $session->organizacion_id !== $organizacionId) {
                $session->organizacion_id = $organizacionId;
                $dirty = true;
            }
            if ((string) $session->phone_number_id !== $phoneNumberId) {
                $session->phone_number_id = $phoneNumberId;
                $dirty = true;
            }
            $display = (string) $creds['display_number'];
            if ($display !== '' && (string) $session->display_number !== $display) {
                $session->display_number = $display;
                $dirty = true;
            }
            if ($dirty) {
                $session->save();
            }

            return $session;
        }

        return WaInboxSession::create([
            'organizacion_id' => $organizacionId,
            'phone_number_id' => $phoneNumberId,
            'display_number' => (string) $creds['display_number'],
            'label' => 'WhatsApp',
            'is_active' => !empty($creds['enabled']),
        ]);
    }

    /**
     * @param  int  $organizacionId
     * @param  \App\Models\Usuario|null  $user
     * @return array<string, mixed>
     */
    public function getSessionPayloadForOrganizacion($organizacionId, $user = null)
    {
        $organizacionId = (int) $organizacionId;
        $creds = $this->orgConfig->credentials($organizacionId);
        $org = Organizacion::query()->find($organizacionId);
        $canConfigure = $user instanceof Usuario ? $user->puedeConfigurarWhatsappInbox() : false;

        $base = [
            'organizacion_id' => $organizacionId,
            'organizacion_nombre' => $org ? (string) $org->No_Organizacion : '',
            'configured' => (string) $creds['phone_number_id'] !== '',
            'enabled' => !empty($creds['enabled']),
            'can_configure' => $canConfigure,
        ];

        if ((string) $creds['phone_number_id'] === '') {
            return $base + [
                'id' => null,
                'phone_number_id' => '',
                'display_number' => '',
                'label' => 'WhatsApp',
                'is_active' => false,
                'last_webhook_at' => null,
            ];
        }

        $session = $this->ensureSessionForOrganizacion($organizacionId);

        return $base + [
            'id' => (int) $session->id,
            'phone_number_id' => $session->phone_number_id,
            'display_number' => $session->display_number ?: $session->phone_number_id,
            'label' => $session->label,
            'is_active' => (bool) $session->is_active,
            'last_webhook_at' => $session->last_webhook_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionPayload()
    {
        return $this->getSessionPayloadForOrganizacion(Usuario::ID_ORGANIZACION_ADMIN);
    }

    /**
     * @param  string  $phoneNumberId
     * @return WaInboxSession|null
     */
    public function findByPhoneNumberId($phoneNumberId)
    {
        $session = WaInboxSession::query()
            ->where('phone_number_id', (string) $phoneNumberId)
            ->where('is_active', true)
            ->first();
        if ($session) {
            return $session;
        }

        $config = $this->orgConfig->findByPhoneNumberId($phoneNumberId);
        if (!$config || empty($config->enabled) || trim((string) $config->phone_number_id) === '') {
            return null;
        }

        $this->orgConfig->syncSession($config);

        return WaInboxSession::query()
            ->where('phone_number_id', (string) $phoneNumberId)
            ->where('is_active', true)
            ->first();
    }
}
