<?php

namespace App\Services\WhatsappInbox;

use App\Models\Organizacion;
use App\Models\Usuario;
use App\Models\WhatsappInbox\WaInboxSession;
use App\Support\Phone\CountryPhoneHelper;

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
        if ($user instanceof Usuario) {
            $linea = $this->orgConfig->lineaVisibleParaUsuario($user);
        } else {
            $linea = $this->orgConfig->findByOrganizacion($organizacionId);
        }
        $org = Organizacion::query()->find($organizacionId);
        $canConfigure = $user instanceof Usuario ? $user->puedeConfigurarWhatsappInbox() : false;
        $phone = $linea ? trim((string) $linea->phone_number_id) : '';
        $label = $linea ? trim((string) $linea->nombre) : '';
        if ($label === '') {
            $label = 'WhatsApp';
        }
        $display = $linea ? trim((string) $linea->display_number) : '';
        $enabled = $linea ? (bool) $linea->enabled : false;

        $base = [
            'organizacion_id' => $organizacionId,
            'organizacion_nombre' => $org ? (string) $org->No_Organizacion : '',
            'configured' => $phone !== '',
            'enabled' => $enabled,
            'can_configure' => $canConfigure,
            // Prefijo que se antepone a numeros sin codigo de pais (alta manual de contactos)
            'phone_code' => self::phoneCodeForOrganizacion($organizacionId),
        ];

        if ($phone === '') {
            return $base + [
                'id' => null,
                'phone_number_id' => '',
                'display_number' => '',
                'label' => $label,
                'is_active' => false,
                'last_webhook_at' => null,
            ];
        }

        $session = WaInboxSession::query()
            ->where('phone_number_id', $phone)
            ->first();
        if (!$session) {
            $this->orgConfig->syncSession($linea);
            $session = WaInboxSession::query()
                ->where('phone_number_id', $phone)
                ->first();
        }

        if ($display === '' && $session) {
            $display = trim((string) $session->display_number);
        }
        if ($display === '') {
            $display = $phone;
        }

        return $base + [
            'id' => $session ? (int) $session->id : null,
            'phone_number_id' => $phone,
            'display_number' => $display,
            'label' => $label,
            'is_active' => $session ? (bool) $session->is_active : $enabled,
            'last_webhook_at' => $session ? $session->last_webhook_at : null,
        ];
    }

    /**
     * Codigo de pais de la org (organizacion.id_pais -> pais_flags.phone_code). Sin pais configurado: 51.
     *
     * @param  int|null  $organizacionId
     * @return string
     */
    public static function phoneCodeForOrganizacion($organizacionId)
    {
        $orgId = (int) $organizacionId > 0 ? (int) $organizacionId : Usuario::ID_ORGANIZACION_ADMIN;

        return CountryPhoneHelper::codeForOrganizacionId($orgId) ?: '51';
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
