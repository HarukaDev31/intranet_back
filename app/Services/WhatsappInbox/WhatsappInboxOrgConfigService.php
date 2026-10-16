<?php

namespace App\Services\WhatsappInbox;

use App\Models\Grupo;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Models\WhatsappInbox\WaInboxConfigRole;
use App\Models\WhatsappInbox\WaInboxConversation;
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
            ->orderByDesc('is_default')
            ->orderBy('id')
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
            ->orderByRaw('CASE WHEN organizacion_id = ' . (int) Usuario::ID_ORGANIZACION_ADMIN . ' THEN 0 ELSE 1 END')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
        if ($row) {
            return $row;
        }

        $envPhone = $this->phoneNumberIdDesdeEnv();
        if ($envPhone === '' || $envPhone !== $phoneNumberId) {
            return null;
        }

        $default = $this->findByOrganizacion(Usuario::ID_ORGANIZACION_ADMIN);
        if (!$default) {
            return null;
        }

        $rowPhone = trim((string) $default->phone_number_id);

        return ($rowPhone === '' || $rowPhone === $envPhone) ? $default : null;
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
     * Credenciales de envío.
     * Org 1: panel solo si está completo; si no, .env.
     * Socio: sus creds del panel si están completas.
     *
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function credentials($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        $row = $this->findByOrganizacion($organizacionId);
        if ($this->rowEstaConfigurada($row)) {
            return $this->credentialsFromRow($row);
        }

        if ($organizacionId === Usuario::ID_ORGANIZACION_ADMIN) {
            return $this->credentialsFromEnv();
        }

        return $this->emptyCredentials($organizacionId);
    }

    /**
     * @param  WaInboxOrganizacionConfig|null  $row
     * @return bool
     */
    private function rowEstaConfigurada($row)
    {
        if (!$row) {
            return false;
        }
        $token = trim((string) $row->access_token);
        $phone = trim((string) $row->phone_number_id);

        return (bool) $row->enabled && $token !== '' && $phone !== '';
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
     * Envío programático: si el socio no tiene Meta activo, usa el número de org 1.
     *
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function credentialsForOutbound($organizacionId)
    {
        $creds = $this->credentials($organizacionId);
        if (!empty($creds['enabled'])) {
            return $creds;
        }
        if ((int) $organizacionId !== Usuario::ID_ORGANIZACION_ADMIN) {
            return $this->credentials(Usuario::ID_ORGANIZACION_ADMIN);
        }

        return $creds;
    }

    /**
     * @param  int  $organizacionId
     * @return bool
     */
    public function isEnabledForOutbound($organizacionId)
    {
        return !empty($this->credentialsForOutbound($organizacionId)['enabled']);
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
            $phone = $this->phoneDeLinea($row);
        }
        if ($phone === '') {
            return;
        }

        $orgId = (int) $row->organizacion_id;
        $label = trim((string) $row->nombre);
        if ($label === '') {
            $label = 'WhatsApp';
        }

        $porTelefono = WaInboxSession::query()->where('phone_number_id', $phone)->first();
        $porConfig = WaInboxSession::query()->where('config_id', $row->id)->first();
        if ($porTelefono && $porConfig && (int) $porTelefono->id !== (int) $porConfig->id) {
            $porConfig->config_id = null;
            $porConfig->save();
        }
        $session = $porTelefono ?: $porConfig;

        if ($session) {
            $session->organizacion_id = $orgId;
            $session->config_id = (int) $row->id;
            $session->phone_number_id = $phone;
            $session->display_number = $this->recortar((string) $row->display_number, 32);
            $session->label = $this->recortar($label, 64);
            $session->is_active = (bool) $row->enabled;
            $session->save();
            $this->traerConversacionesAOrganizacion($session, $orgId);

            return;
        }

        $creada = WaInboxSession::create([
            'organizacion_id' => $orgId,
            'config_id' => (int) $row->id,
            'phone_number_id' => $phone,
            'display_number' => $this->recortar((string) $row->display_number, 32),
            'label' => $this->recortar($label, 64),
            'is_active' => (bool) $row->enabled,
        ]);
        $this->traerConversacionesAOrganizacion($creada, $orgId);
    }

    /**
     * Listado del mantenedor: varios números de la org y los roles que pueden verlos.
     *
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function adminIndex($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        $org = Organizacion::query()->find($organizacionId);
        $lineas = WaInboxOrganizacionConfig::query()
            ->with('roles')
            ->where('organizacion_id', $organizacionId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($lineas as $row) {
            $items[] = $this->lineaPayload($row);
        }

        return [
            'organizacion_id' => $organizacionId,
            'organizacion_nombre' => $org ? (string) $org->No_Organizacion : '',
            'roles' => $this->rolesDeOrganizacion($organizacionId),
            'lineas' => $items,
        ];
    }

    /**
     * @param  int  $organizacionId
     * @param  int|null  $lineaId
     * @param  array<string, mixed>  $input
     * @return WaInboxOrganizacionConfig
     */
    public function saveLinea($organizacionId, $lineaId, array $input)
    {
        $organizacionId = (int) $organizacionId;
        $row = null;
        if ($lineaId) {
            $row = WaInboxOrganizacionConfig::query()
                ->where('organizacion_id', $organizacionId)
                ->where('id', (int) $lineaId)
                ->first();
            if (!$row) {
                throw new \InvalidArgumentException('Ese número no pertenece a tu organización');
            }
        } else {
            $row = new WaInboxOrganizacionConfig();
            $row->organizacion_id = $organizacionId;
            $row->legacy_fallback = true;
            $row->preview_from_template = true;
            $row->session_when_window_open = true;
        }

        $nombre = trim((string) ($input['nombre'] ?? $row->nombre ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('Indica un nombre para el número');
        }
        if (mb_strlen($nombre) > 80) {
            throw new \InvalidArgumentException('El nombre es demasiado largo');
        }

        $phone = trim((string) ($input['phone_number_id'] ?? $row->phone_number_id ?? ''));
        if ($phone === '' && $this->esLineaCoordinacion($row, $input)) {
            $phone = $this->phoneNumberIdDesdeEnv();
        }
        if (strlen($phone) > 32) {
            throw new \InvalidArgumentException('El ID del número es demasiado largo');
        }
        if (!empty($input['enabled']) && $phone === '') {
            throw new \InvalidArgumentException('Actívalo solo cuando ya tengas el ID del número de Meta');
        }
        if ($phone !== '') {
            $duplicado = WaInboxOrganizacionConfig::query()
                ->where('organizacion_id', $organizacionId)
                ->where('phone_number_id', $phone)
                ->where('id', '!=', (int) $row->id)
                ->first();
            if ($duplicado) {
                $duplicado->phone_number_id = null;
                $duplicado->save();
            }
        }

        $tokenNuevo = isset($input['access_token']) ? trim((string) $input['access_token']) : '';
        $tokenActual = trim((string) $row->access_token);
        $usaTokenDeEnv = $tokenNuevo === ''
            && $tokenActual === ''
            && $organizacionId === Usuario::ID_ORGANIZACION_ADMIN
            && $phone !== ''
            && $phone === $this->phoneNumberIdDesdeEnv()
            && trim((string) config('meta_whatsapp.access_token')) !== '';
        if (!empty($input['enabled']) && $tokenNuevo === '' && $tokenActual === '' && !$usaTokenDeEnv) {
            throw new \InvalidArgumentException('Pega el token de acceso para activar este número');
        }

        $esDefault = !empty($input['is_default']);
        $hayOtras = WaInboxOrganizacionConfig::query()
            ->where('organizacion_id', $organizacionId)
            ->where('id', '!=', (int) $row->id)
            ->exists();
        if (!$hayOtras) {
            $esDefault = true;
        }
        if (!$esDefault) {
            $otraPrincipal = WaInboxOrganizacionConfig::query()
                ->where('organizacion_id', $organizacionId)
                ->where('id', '!=', (int) $row->id)
                ->where('is_default', true)
                ->exists();
            if (!$otraPrincipal) {
                $esDefault = true;
            }
        }

        $row->nombre = $nombre;
        $row->is_default = $esDefault;
        $row->enabled = !empty($input['enabled']);
        $row->phone_number_id = $phone !== '' ? $phone : null;
        $row->waba_id = $this->nullableString($input, 'waba_id', $row->waba_id);
        $row->display_number = $this->nullableString($input, 'display_number', $row->display_number);
        $row->graph_api_version = $this->recortar(
            $this->presentString($input, 'graph_api_version', $row->graph_api_version ?: 'v19.0'),
            16
        ) ?: 'v19.0';
        $row->default_language = $this->recortar(
            $this->presentString($input, 'default_language', $row->default_language ?: 'es_PE'),
            16
        ) ?: 'es_PE';

        $verifyToken = isset($input['webhook_verify_token']) ? trim((string) $input['webhook_verify_token']) : '';
        if ($verifyToken !== '') {
            $row->webhook_verify_token = $verifyToken;
        }
        if ($tokenNuevo !== '') {
            $row->access_token = $tokenNuevo;
        }
        $secret = isset($input['app_secret']) ? trim((string) $input['app_secret']) : '';
        if ($secret !== '') {
            $row->app_secret = $secret;
        }

        $this->completarLineaCoordinacionDesdeEnv($row);
        if ($row->display_number !== null) {
            $row->display_number = $this->recortar((string) $row->display_number, 32) ?: null;
        }
        if ($row->waba_id !== null) {
            $row->waba_id = $this->recortar((string) $row->waba_id, 32) ?: null;
        }

        $row->save();

        if ($esDefault) {
            WaInboxOrganizacionConfig::query()
                ->where('organizacion_id', $organizacionId)
                ->where('id', '!=', (int) $row->id)
                ->update(['is_default' => false]);
        }

        if (array_key_exists('roles', $input)) {
            $this->syncRoles($row, $input['roles']);
        }

        $this->syncSession($row);

        return $row->load('roles');
    }

    /**
     * Números cuyo chat puede ver este usuario.
     * Una línea sin roles la ve toda la organización. Con roles, solo esos.
     *
     * @param  \App\Models\Usuario|null  $user
     * @return array<int, string>
     */
    public function visiblePhoneNumberIds($user)
    {
        if (!$user) {
            return [];
        }

        $orgId = (int) $user->getAttribute('ID_Organizacion');
        $rolesUsuario = $this->roleNamesOfUser($user);
        $veLineaCoordinacion = $this->rolesCoinciden([Usuario::ROL_COORDINACION], $rolesUsuario)
            || ($user instanceof Usuario && $user->puedeConfigurarWhatsappInbox());
        $envPhone = $this->phoneNumberIdDesdeEnv();
        $lineas = WaInboxOrganizacionConfig::query()
            ->with('roles')
            ->where('organizacion_id', $orgId)
            ->get();

        $phones = [];
        foreach ($lineas as $linea) {
            $phone = $this->phoneDeLinea($linea);
            if ($phone === '') {
                continue;
            }
            $asignados = [];
            foreach ($linea->roles as $role) {
                /** @var WaInboxConfigRole $role */
                $nombre = trim((string) $role->role_name);
                if ($nombre !== '') {
                    $asignados[] = $nombre;
                }
            }
            $esNumeroCoordinacion = (bool) $linea->is_default || ($envPhone !== '' && $phone === $envPhone);
            if ($this->rolesCoinciden($asignados, $rolesUsuario) || ($veLineaCoordinacion && $esNumeroCoordinacion)) {
                $phones[] = $phone;
            }
        }

        return array_values(array_unique($phones));
    }

    /**
     * Línea que debe mostrar el chat de este usuario.
     * Si su rol no tiene ningún número, no se usa el de coordinación.
     *
     * @param  \App\Models\Usuario|null  $user
     * @return WaInboxOrganizacionConfig|null
     */
    public function lineaVisibleParaUsuario($user)
    {
        if (!$user) {
            return null;
        }

        $phones = $this->visiblePhoneNumberIds($user);
        if ($phones === []) {
            return null;
        }

        $orgId = (int) $user->getAttribute('ID_Organizacion');
        $lineas = WaInboxOrganizacionConfig::query()
            ->with('roles')
            ->where('organizacion_id', $orgId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
        $visibles = [];
        foreach ($lineas as $linea) {
            if (in_array($this->phoneDeLinea($linea), $phones, true)) {
                $visibles[] = $linea;
            }
        }
        if ($visibles === []) {
            return null;
        }

        $principal = Usuario::normalizarRol($user->getNombreGrupo());
        if ($principal !== '' && $principal !== Usuario::normalizarRol('Sin grupo')) {
            foreach ($visibles as $linea) {
                foreach ($linea->roles as $role) {
                    /** @var WaInboxConfigRole $role */
                    if (Usuario::normalizarRol($role->role_name) === $principal) {
                        return $linea;
                    }
                }
            }
        }

        return $visibles[0];
    }

    /**
     * Credenciales del número de la conversación (no el único de la org).
     *
     * @param  string  $phoneNumberId
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function credentialsForPhoneNumber($phoneNumberId, $organizacionId)
    {
        $config = $this->findByPhoneNumberId($phoneNumberId);
        if ($config && (int) $config->organizacion_id === (int) $organizacionId && $this->rowEstaConfigurada($config)) {
            return $this->credentialsFromRow($config);
        }

        return $this->credentialsForOutbound($organizacionId);
    }

    /**
     * @param  int  $organizacionId
     * @return array<int, array{label: string, value: string}>
     */
    private function rolesDeOrganizacion($organizacionId)
    {
        $nombres = Grupo::query()
            ->where('ID_Organizacion', (int) $organizacionId)
            ->orderBy('No_Grupo')
            ->pluck('No_Grupo')
            ->all();

        $items = [];
        $vistos = [];
        foreach ($nombres as $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre === '' || isset($vistos[$nombre])) {
                continue;
            }
            $vistos[$nombre] = true;
            $items[] = ['label' => $nombre, 'value' => $nombre];
        }

        if (!isset($vistos[Usuario::ROL_COORDINACION])) {
            array_unshift($items, [
                'label' => Usuario::ROL_COORDINACION,
                'value' => Usuario::ROL_COORDINACION,
            ]);
        }

        return $items;
    }

    /**
     * @param  \App\Models\Usuario  $user
     * @return array<int, string>
     */
    private function roleNamesOfUser($user)
    {
        $nombres = [];
        $grupos = $user->getAllGrupos();
        foreach ($grupos as $grupo) {
            if (!$grupo) {
                continue;
            }
            $nombre = trim((string) $grupo->No_Grupo);
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return array_values(array_unique($nombres));
    }

    /**
     * @param  WaInboxOrganizacionConfig  $linea
     * @return string
     */
    public function telefonoDeLinea(WaInboxOrganizacionConfig $linea)
    {
        return $this->phoneDeLinea($linea);
    }

    /**
     * @param  WaInboxSession  $session
     * @param  int  $orgId
     * @return void
     */
    private function traerConversacionesAOrganizacion(WaInboxSession $session, $orgId)
    {
        WaInboxConversation::query()
            ->where('session_id', $session->id)
            ->where(function ($q) use ($orgId) {
                $q->whereNull('organizacion_id')->orWhere('organizacion_id', '!=', (int) $orgId);
            })
            ->update(['organizacion_id' => (int) $orgId]);
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @param  array<string, mixed>  $input
     * @return bool
     */
    private function esLineaCoordinacion(WaInboxOrganizacionConfig $row, array $input)
    {
        if ((int) $row->organizacion_id !== Usuario::ID_ORGANIZACION_ADMIN) {
            return false;
        }

        return !empty($input['is_default']) || (bool) $row->is_default;
    }

    /**
     * Si Coordinación usa el número del entorno y el campo está vacío, copia token, WABA y número visible.
     *
     * @param  WaInboxOrganizacionConfig  $row
     * @return void
     */
    private function completarLineaCoordinacionDesdeEnv(WaInboxOrganizacionConfig $row)
    {
        if ((int) $row->organizacion_id !== Usuario::ID_ORGANIZACION_ADMIN || !(bool) $row->is_default) {
            return;
        }
        $phone = trim((string) $row->phone_number_id);
        $envPhone = $this->phoneNumberIdDesdeEnv();
        if ($phone === '' || $envPhone === '' || $phone !== $envPhone) {
            return;
        }

        $this->copiarTokenSiVacio($row, 'access_token', 'meta_whatsapp.access_token');
        $this->copiarTokenSiVacio($row, 'app_secret', 'meta_whatsapp.app_secret');
        $this->copiarTokenSiVacio($row, 'webhook_verify_token', 'meta_whatsapp.webhook_verify_token');
        if (trim((string) $row->waba_id) === '') {
            $waba = $this->recortar((string) config('meta_whatsapp.waba_id', ''), 32);
            if ($waba !== '') {
                $row->waba_id = $waba;
            }
        }
        if (trim((string) $row->display_number) === '') {
            $display = $this->recortar((string) config('meta_whatsapp.inbox_display_number', ''), 32);
            if ($display !== '') {
                $row->display_number = $display;
            }
        }
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @param  string  $campo
     * @param  string  $configKey
     * @return void
     */
    private function copiarTokenSiVacio(WaInboxOrganizacionConfig $row, $campo, $configKey)
    {
        $actual = '';
        if ($campo === 'access_token') {
            $actual = trim((string) $row->access_token);
        } elseif ($campo === 'app_secret') {
            $actual = trim((string) $row->app_secret);
        } elseif ($campo === 'webhook_verify_token') {
            $actual = trim((string) $row->webhook_verify_token);
        }
        if ($actual !== '') {
            return;
        }
        $valor = trim((string) config($configKey, ''));
        if ($valor === '') {
            return;
        }
        if ($campo === 'access_token') {
            $row->access_token = $valor;
        } elseif ($campo === 'app_secret') {
            $row->app_secret = $valor;
        } elseif ($campo === 'webhook_verify_token') {
            $row->webhook_verify_token = $valor;
        }
    }

    /**
     * @param  string  $texto
     * @param  int  $max
     * @return string
     */
    private function recortar($texto, $max)
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '';
        }

        return mb_substr($texto, 0, (int) $max);
    }

    /**
     * @param  WaInboxOrganizacionConfig  $linea
     * @return bool
     */
    private function lineaUsaTelefonoDeEnv(WaInboxOrganizacionConfig $linea)
    {
        if ((int) $linea->organizacion_id !== Usuario::ID_ORGANIZACION_ADMIN || !(bool) $linea->is_default) {
            return false;
        }
        $phone = $this->phoneDeLinea($linea);
        $envPhone = $this->phoneNumberIdDesdeEnv();

        return $phone !== '' && $phone === $envPhone;
    }

    /**
     * @param  WaInboxOrganizacionConfig  $linea
     * @param  string  $campo
     * @param  string  $configKey
     * @return string
     */
    private function textoVisibleDeLinea(WaInboxOrganizacionConfig $linea, $campo, $configKey)
    {
        $valor = '';
        if ($campo === 'waba_id') {
            $valor = trim((string) $linea->waba_id);
        } elseif ($campo === 'display_number') {
            $valor = trim((string) $linea->display_number);
        }
        if ($valor !== '') {
            return $valor;
        }
        if (!$this->lineaUsaTelefonoDeEnv($linea)) {
            return '';
        }

        return trim((string) config($configKey, ''));
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @param  string  $campo
     * @param  string  $configKey
     * @return bool
     */
    private function secretoConfigurado(WaInboxOrganizacionConfig $row, $campo, $configKey)
    {
        $actual = '';
        if ($campo === 'access_token') {
            $actual = trim((string) $row->access_token);
        } elseif ($campo === 'app_secret') {
            $actual = trim((string) $row->app_secret);
        } elseif ($campo === 'webhook_verify_token') {
            $actual = trim((string) $row->webhook_verify_token);
        }
        if ($actual !== '') {
            return true;
        }

        return $this->lineaUsaTelefonoDeEnv($row) && trim((string) config($configKey, '')) !== '';
    }

    /**
     * ID de Meta de la línea. Si Coordinación aún no está en la base, usa el del entorno.
     *
     * @param  WaInboxOrganizacionConfig  $linea
     * @return string
     */
    private function phoneDeLinea(WaInboxOrganizacionConfig $linea)
    {
        $phone = trim((string) $linea->phone_number_id);
        if ($phone !== '') {
            return $phone;
        }
        if (!(bool) $linea->is_default || (int) $linea->organizacion_id !== Usuario::ID_ORGANIZACION_ADMIN) {
            return '';
        }

        $envPhone = $this->phoneNumberIdDesdeEnv();
        if ($envPhone === '') {
            return '';
        }

        $yaEnOtra = WaInboxOrganizacionConfig::query()
            ->where('organizacion_id', (int) $linea->organizacion_id)
            ->where('phone_number_id', $envPhone)
            ->where('id', '!=', (int) $linea->id)
            ->exists();

        return $yaEnOtra ? '' : $envPhone;
    }

    /**
     * @return string
     */
    private function phoneNumberIdDesdeEnv()
    {
        return trim((string) config('meta_whatsapp.phone_number_id', ''));
    }

    /**
     * @param  array<int, string>  $asignados
     * @param  array<int, string>  $rolesUsuario
     * @return bool
     */
    private function rolesCoinciden(array $asignados, array $rolesUsuario)
    {
        if ($asignados === []) {
            return true;
        }

        $izquierda = [];
        foreach ($asignados as $nombre) {
            $normalizado = Usuario::normalizarRol($nombre);
            if ($normalizado !== '') {
                $izquierda[] = $normalizado;
            }
        }
        $derecha = [];
        foreach ($rolesUsuario as $nombre) {
            $normalizado = Usuario::normalizarRol($nombre);
            if ($normalizado !== '') {
                $derecha[] = $normalizado;
            }
        }

        return count(array_intersect($izquierda, $derecha)) > 0;
    }

    /**
     * @param  mixed  $role
     * @return string
     */
    private function nombreDeRol($role)
    {
        if (is_array($role)) {
            if (isset($role['value'])) {
                return trim((string) $role['value']);
            }
            if (isset($role['label'])) {
                return trim((string) $role['label']);
            }

            return '';
        }
        if (is_object($role)) {
            if (isset($role->value)) {
                return trim((string) $role->value);
            }
            if (isset($role->label)) {
                return trim((string) $role->label);
            }

            return '';
        }

        return trim((string) $role);
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @param  mixed  $roles
     */
    private function syncRoles(WaInboxOrganizacionConfig $row, $roles)
    {
        $nombres = [];
        if (is_array($roles)) {
            foreach ($roles as $role) {
                $nombre = $this->nombreDeRol($role);
                if ($nombre !== '' && mb_strlen($nombre) <= 80) {
                    $nombres[$nombre] = true;
                }
            }
        }

        WaInboxConfigRole::query()->where('config_id', $row->id)->delete();
        foreach (array_keys($nombres) as $nombre) {
            WaInboxConfigRole::query()->create([
                'config_id' => $row->id,
                'role_name' => $nombre,
            ]);
        }
    }

    /**
     * @param  WaInboxOrganizacionConfig  $row
     * @return array<string, mixed>
     */
    private function lineaPayload(WaInboxOrganizacionConfig $row)
    {
        $roles = [];
        foreach ($row->roles as $role) {
            /** @var WaInboxConfigRole $role */
            $nombre = trim((string) $role->role_name);
            if ($nombre !== '') {
                $roles[] = $nombre;
            }
        }

        return [
            'id' => (int) $row->id,
            'nombre' => (string) $row->nombre,
            'is_default' => (bool) $row->is_default,
            'enabled' => (bool) $row->enabled,
            'phone_number_id' => $this->phoneDeLinea($row),
            'waba_id' => $this->textoVisibleDeLinea($row, 'waba_id', 'meta_whatsapp.waba_id'),
            'display_number' => $this->textoVisibleDeLinea($row, 'display_number', 'meta_whatsapp.inbox_display_number'),
            'graph_api_version' => $row->graph_api_version ?: 'v19.0',
            'default_language' => $row->default_language ?: 'es_PE',
            'roles' => $roles,
            'access_token_set' => $this->secretoConfigurado($row, 'access_token', 'meta_whatsapp.access_token'),
            'app_secret_set' => $this->secretoConfigurado($row, 'app_secret', 'meta_whatsapp.app_secret'),
            'webhook_verify_token_set' => $this->secretoConfigurado($row, 'webhook_verify_token', 'meta_whatsapp.webhook_verify_token'),
        ];
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
            'enabled' => $token !== '' && $phone !== '',
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
