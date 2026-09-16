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
        if ((string) $creds['phone_number_id'] === '') {
            $creds = $this->orgConfig->credentialsForOutbound($organizacionId);
        }
        $phoneNumberId = (string) $creds['phone_number_id'];
        if ($phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp no está configurado para esta organización');
        }

        $byPhone = WaInboxSession::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
        if ($byPhone) {
            $display = (string) $creds['display_number'];
            if ($display !== '' && (string) $byPhone->display_number !== $display) {
                $byPhone->display_number = $display;
                $byPhone->save();
            }

            return $byPhone;
        }

        $session = WaInboxSession::query()
            ->where('organizacion_id', $organizacionId)
            ->first();
        if ($session) {
            $session->phone_number_id = $phoneNumberId;
            $display = (string) $creds['display_number'];
            if ($display !== '') {
                $session->display_number = $display;
            }
            $session->save();

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
     * Sesión del número Meta que realmente envía (socio si está activo; si no, org 1).
     *
     * @param  int  $organizacionId
     * @return WaInboxSession
     */
    public function ensureSessionForOutboundOrganizacion($organizacionId)
    {
        $creds = $this->orgConfig->credentialsForOutbound($organizacionId);
        $metaOrgId = (int) $creds['organizacion_id'];
        if ($metaOrgId <= 0) {
            $metaOrgId = Usuario::ID_ORGANIZACION_ADMIN;
        }

        return $this->ensureSessionForOrganizacion($metaOrgId);
    }

    /**
     * @param  int  $organizacionId
     * @return bool
     */
    public function isOutboundConfigured($organizacionId)
    {
        $creds = $this->orgConfig->credentialsForOutbound((int) $organizacionId);

        return (string) $creds['phone_number_id'] !== '';
    }

    /**
     * @param  int  $organizacionId
     * @param  \App\Models\Usuario|null  $user
     * @return array<string, mixed>
     */
    public function getSessionPayloadForOrganizacion($organizacionId, $user = null)
    {
        $organizacionId = (int) $organizacionId;
        $own = $this->orgConfig->credentials($organizacionId);
        $outbound = $this->orgConfig->credentialsForOutbound($organizacionId);
        $org = Organizacion::query()->find($organizacionId);
        $canConfigure = $user instanceof Usuario ? $user->puedeConfigurarWhatsappInbox() : false;

        $base = [
            'organizacion_id' => $organizacionId,
            'organizacion_nombre' => $org ? (string) $org->No_Organizacion : '',
            'configured' => (string) $outbound['phone_number_id'] !== '',
            'enabled' => !empty($outbound['enabled']),
            'can_configure' => $canConfigure,
        ];

        if ((string) $own['phone_number_id'] === '') {
            return $base + [
                'id' => null,
                'phone_number_id' => (string) $outbound['phone_number_id'],
                'display_number' => (string) ($outbound['display_number'] ?: $outbound['phone_number_id']),
                'label' => 'WhatsApp',
                'is_active' => !empty($outbound['enabled']),
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
