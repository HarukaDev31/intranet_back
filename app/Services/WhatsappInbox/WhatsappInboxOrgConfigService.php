<?php

namespace App\Services\WhatsappInbox;

use App\Models\Organizacion;
use App\Models\Usuario;
use App\Models\WhatsappInbox\WaInboxOrganizacionConfig;
use App\Models\WhatsappInbox\WaInboxSession;

class WhatsappInboxOrgConfigService
{
    /**
     * @param  int  $organizacionId
     * @return WaInboxOrganizacionConfig|null
     */
    public function findByOrganizacion($organizacionId)
    {
        return WaInboxOrganizacionConfig::query()
            ->where('organizacion_id', (int) $organizacionId)
            ->first();
    }

    /**
     * @param  string  $phoneNumberId
     * @return WaInboxOrganizacionConfig|null
     */
    public function findByPhoneNumberId($phoneNumberId)
    {
        $phoneNumberId = trim((string) $phoneNumberId);
        if ($phoneNumberId === '') {
            return null;
        }

        $row = WaInboxOrganizacionConfig::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
        if ($row) {
            return $row;
        }

        $envPhone = trim((string) config('meta_whatsapp.phone_number_id', ''));
        if ($envPhone !== '' && $envPhone === $phoneNumberId) {
            return $this->findByOrganizacion(Usuario::ID_ORGANIZACION_ADMIN);
        }

        return null;
    }

    /**
     * @param  string  $token
     * @return bool
     */
    public function matchesVerifyToken($token)
    {
        $token = (string) $token;
        if ($token === '') {
            return false;
        }

        $rows = WaInboxOrganizacionConfig::query()
            ->whereNotNull('webhook_verify_token')
            ->where('webhook_verify_token', '!=', '')
            ->get();
        foreach ($rows as $row) {
            $stored = (string) $row->webhook_verify_token;
            if ($stored !== '' && hash_equals($stored, $token)) {
                return true;
            }
        }

        $envToken = (string) config('meta_whatsapp.webhook_verify_token');

        return $envToken !== '' && hash_equals($envToken, $token);
    }

    /**
     * @param  string  $rawBody
     * @param  string  $signatureHeader
     * @return bool
     */
    public function isValidWebhookSignature($rawBody, $signatureHeader)
    {
        $signatureHeader = (string) $signatureHeader;
        if ($signatureHeader === '') {
            return app()->environment('local');
        }

        $secrets = [];
        $rows = WaInboxOrganizacionConfig::query()
            ->whereNotNull('app_secret')
            ->where('app_secret', '!=', '')
            ->get();
        foreach ($rows as $row) {
            $secret = trim((string) $row->app_secret);
            if ($secret !== '') {
                $secrets[] = $secret;
            }
        }

        $envSecret = trim((string) config('meta_whatsapp.app_secret', ''));
        if ($envSecret !== '') {
            $secrets[] = $envSecret;
        }

        if ($secrets === []) {
            return app()->environment('local');
        }

        foreach (array_unique($secrets) as $secret) {
            $expected = 'sha256=' . hash_hmac('sha256', (string) $rawBody, $secret);
            if (hash_equals($expected, $signatureHeader)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Credenciales de envío. Si la org no tiene fila, org 1 cae al .env.
     *
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function credentials($organizacionId)
    {
        $row = $this->findByOrganizacion($organizacionId);
        if ($row) {
            return $this->credentialsFromRow($row);
        }

        if ((int) $organizacionId === Usuario::ID_ORGANIZACION_ADMIN) {
            return $this->credentialsFromEnv();
        }

        return $this->emptyCredentials((int) $organizacionId);
    }

    /**
     * @param  int  $organizacionId
     * @return bool
     */
    public function isEnabled($organizacionId)
    {
        return !empty($this->credentials($organizacionId)['enabled']);
    }

    /**
     * @param  \App\Models\Usuario|null  $user
     * @return int
     */
    public function organizacionIdForUser($user)
    {
        if (!$user) {
            return 0;
        }

        return (int) $user->getAttribute('ID_Organizacion');
    }

    /**
     * Payload para el formulario de administración (secretos enmascarados).
     *
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function adminPayload($organizacionId)
    {
        $row = $this->findByOrganizacion($organizacionId);
        $creds = $this->credentials($organizacionId);
        $org = Organizacion::query()->find($organizacionId);

        return [
            'organizacion_id' => (int) $organizacionId,
            'organizacion_nombre' => $org ? (string) $org->No_Organizacion : '',
            'enabled' => (bool) $creds['enabled'],
            'phone_number_id' => (string) $creds['phone_number_id'],
            'waba_id' => (string) $creds['waba_id'],
            'display_number' => (string) $creds['display_number'],
            'graph_api_version' => (string) $creds['graph_api_version'],
            'default_language' => (string) $creds['default_language'],
            'legacy_fallback' => (bool) $creds['legacy_fallback'],
            'preview_from_template' => (bool) $creds['preview_from_template'],
            'session_when_window_open' => (bool) $creds['session_when_window_open'],
            'access_token_set' => $row ? trim((string) $row->access_token) !== '' : trim((string) config('meta_whatsapp.access_token')) !== '',
            'app_secret_set' => $row ? trim((string) $row->app_secret) !== '' : trim((string) config('meta_whatsapp.app_secret')) !== '',
            'webhook_verify_token_set' => $row
                ? trim((string) $row->webhook_verify_token) !== ''
                : trim((string) config('meta_whatsapp.webhook_verify_token')) !== '',
        ];
    }

    /**
     * @param  int  $organizacionId
     * @param  array<string, mixed>  $input
     * @return WaInboxOrganizacionConfig
     */
    public function saveForOrganizacion($organizacionId, array $input)
    {
        $organizacionId = (int) $organizacionId;
        $row = $this->findByOrganizacion($organizacionId);
        if (!$row) {
            $row = new WaInboxOrganizacionConfig();
            $row->organizacion_id = $organizacionId;
        }

        $phone = trim((string) ($input['phone_number_id'] ?? $row->phone_number_id ?? ''));
        if ($phone !== '') {
            $taken = WaInboxOrganizacionConfig::query()
                ->where('phone_number_id', $phone)
                ->where('organizacion_id', '!=', $organizacionId)
                ->exists();
            if ($taken) {
                throw new \InvalidArgumentException('Ese ID de número ya está asignado a otra organización.');
            }
        }

        $row->enabled = !empty($input['enabled']);
        $row->phone_number_id = $phone !== '' ? $phone : null;
        $row->waba_id = $this->nullableString($input, 'waba_id', $row->waba_id);
        $row->display_number = $this->nullableString($input, 'display_number', $row->display_number);
        $row->graph_api_version = $this->presentString($input, 'graph_api_version', $row->graph_api_version ?: 'v19.0');
        $row->default_language = $this->presentString($input, 'default_language', $row->default_language ?: 'es_PE');
        $verifyToken = isset($input['webhook_verify_token']) ? trim((string) $input['webhook_verify_token']) : '';
        if ($verifyToken !== '') {
            $row->webhook_verify_token = $verifyToken;
        }
        $row->legacy_fallback = array_key_exists('legacy_fallback', $input)
            ? !empty($input['legacy_fallback'])
            : (bool) $row->legacy_fallback;
        $row->preview_from_template = array_key_exists('preview_from_template', $input)
            ? !empty($input['preview_from_template'])
            : (bool) $row->preview_from_template;
        $row->session_when_window_open = array_key_exists('session_when_window_open', $input)
            ? !empty($input['session_when_window_open'])
            : (bool) $row->session_when_window_open;

        $token = isset($input['access_token']) ? trim((string) $input['access_token']) : '';
        if ($token !== '') {
            $row->access_token = $token;
        }
        $secret = isset($input['app_secret']) ? trim((string) $input['app_secret']) : '';
        if ($secret !== '') {
            $row->app_secret = $secret;
        }

        $row->save();
        $this->syncSession($row);

        return $row;
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @return void
     */
    public function syncSession(WaInboxOrganizacionConfig $row)
    {
        $phone = trim((string) $row->phone_number_id);
        if ($phone === '') {
            return;
        }

        $session = WaInboxSession::query()
            ->where('organizacion_id', (int) $row->organizacion_id)
            ->first();
        if (!$session) {
            $session = WaInboxSession::query()->where('phone_number_id', $phone)->first();
        }
        if (!$session) {
            $session = new WaInboxSession();
        }

        $session->organizacion_id = (int) $row->organizacion_id;
        $session->phone_number_id = $phone;
        $session->display_number = (string) $row->display_number;
        $session->label = 'WhatsApp';
        $session->is_active = (bool) $row->enabled;
        $session->save();
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @return array<string, mixed>
     */
    private function credentialsFromRow(WaInboxOrganizacionConfig $row)
    {
        $token = trim((string) $row->access_token);
        $phone = trim((string) $row->phone_number_id);

        return [
            'organizacion_id' => (int) $row->organizacion_id,
            'enabled' => (bool) $row->enabled && $token !== '' && $phone !== '',
            'access_token' => $token,
            'phone_number_id' => $phone,
            'app_secret' => (string) $row->app_secret,
            'webhook_verify_token' => (string) $row->webhook_verify_token,
            'waba_id' => (string) $row->waba_id,
            'graph_api_version' => $row->graph_api_version ?: 'v19.0',
            'default_language' => $row->default_language ?: 'es_PE',
            'display_number' => (string) $row->display_number,
            'legacy_fallback' => (bool) $row->legacy_fallback,
            'preview_from_template' => (bool) $row->preview_from_template,
            'session_when_window_open' => (bool) $row->session_when_window_open,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialsFromEnv()
    {
        $token = trim((string) config('meta_whatsapp.access_token'));
        $phone = trim((string) config('meta_whatsapp.phone_number_id'));

        return [
            'organizacion_id' => Usuario::ID_ORGANIZACION_ADMIN,
            'enabled' => (bool) config('meta_whatsapp.coordinacion_enabled') && $token !== '' && $phone !== '',
            'access_token' => $token,
            'phone_number_id' => $phone,
            'app_secret' => (string) config('meta_whatsapp.app_secret'),
            'webhook_verify_token' => (string) config('meta_whatsapp.webhook_verify_token'),
            'waba_id' => (string) config('meta_whatsapp.waba_id'),
            'graph_api_version' => (string) config('meta_whatsapp.graph_api_version', 'v19.0'),
            'default_language' => (string) config('meta_whatsapp.default_language', 'es_PE'),
            'display_number' => (string) config('meta_whatsapp.inbox_display_number'),
            'legacy_fallback' => (bool) config('meta_whatsapp.legacy_fallback', true),
            'preview_from_template' => (bool) config('meta_whatsapp.coordinacion_inbox_preview_from_template', true),
            'session_when_window_open' => (bool) config('meta_whatsapp.coordinacion_session_message_when_window_open', true),
        ];
    }

    /**
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    private function emptyCredentials($organizacionId)
    {
        return [
            'organizacion_id' => (int) $organizacionId,
            'enabled' => false,
            'access_token' => '',
            'phone_number_id' => '',
            'app_secret' => '',
            'webhook_verify_token' => '',
            'waba_id' => '',
            'graph_api_version' => 'v19.0',
            'default_language' => 'es_PE',
            'display_number' => '',
            'legacy_fallback' => true,
            'preview_from_template' => true,
            'session_when_window_open' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  string  $key
     * @param  mixed  $fallback
     * @return string|null
     */
    private function nullableString(array $input, $key, $fallback)
    {
        if (!array_key_exists($key, $input)) {
            $value = trim((string) $fallback);

            return $value !== '' ? $value : null;
        }

        $value = trim((string) $input[$key]);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  string  $key
     * @param  string  $fallback
     * @return string
     */
    private function presentString(array $input, $key, $fallback)
    {
        if (!array_key_exists($key, $input)) {
            return (string) $fallback;
        }

        $value = trim((string) $input[$key]);

        return $value !== '' ? $value : (string) $fallback;
    }
}
