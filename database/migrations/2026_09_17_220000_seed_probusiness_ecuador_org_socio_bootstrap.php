<?php

use App\Helpers\CodeIgniterEncryption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bootstrap de datos QA → prod para Probusiness Ecuador (org socio).
 *
 * Tablas:
 * - organizacion (+ id_pais ECUADOR)
 * - organizacion_paises_habilitados
 * - organizacion_portales
 * - organizacion_mensajeria
 * - wa_inbox_organizacion_config (sin secretos cifrados; se configuran en panel)
 * - grupo (Socio, Cotizador)
 * - menu (catálogo org)
 * - usuario + grupo_usuario
 * - menu_acceso
 *
 * Idempotente: no duplica si la org/usuario/portal ya existen.
 */
class SeedProbusinessEcuadorOrgSocioBootstrap extends Migration
{
    private const ORG_ID = 2;
    private const EMPRESA_ID = 1;
    private const PAIS_ECUADOR_ID = 4;

    /** Misma public_key que QA para no romper X-Org-Key del portal socios. */
    private const PORTAL_PUBLIC_KEY = 'fccd822c-f06c-4469-8632-a496458836f4';

    public function up()
    {
        if (! Schema::hasTable('organizacion')) {
            return;
        }

        DB::transaction(function () {
            $orgId = $this->ensureOrganizacion();
            $this->ensurePaisesHabilitados($orgId);
            $this->ensurePortal($orgId);
            $this->ensureMensajeria($orgId);
            $this->ensureWaInboxConfig($orgId);

            $grupoSocioId = $this->ensureGrupo($orgId, 'Socio', 'Rol administrador del socio');
            $grupoCotizadorId = $this->ensureGrupo($orgId, 'Cotizador', 'Cotizador del socio');

            $menuIds = $this->ensureMenus($orgId);
            $this->ensureWhatsappAdminMenu($orgId, $menuIds);

            $socioGuId = $this->ensureUsuario(
                $orgId,
                $grupoSocioId,
                [
                    'No_Usuario' => 'socio1@probusiness.pe',
                    'Txt_Email' => 'socio1@probusiness.pe',
                    'No_Nombres_Apellidos' => 'Francis Torres Cuya',
                    'plain_password' => 'socio1@probusiness.pe',
                ]
            );

            $this->ensureMenuAccesoSocio($orgId, $socioGuId, $menuIds);

            $cotizadores = [
                [
                    'No_Usuario' => 'francis@probusinessecuador.com',
                    'Txt_Email' => 'francis@probusinessecuador.com',
                    'No_Nombres_Apellidos' => 'Francis',
                    'plain_password' => 'MQGd2hzBeg',
                ],
                [
                    'No_Usuario' => 'francis14@probusinessecuador.com',
                    'Txt_Email' => 'francis14@probusinessecuador.com',
                    'No_Nombres_Apellidos' => 'francis',
                    'plain_password' => 'qnGjGbbjxh',
                ],
                [
                    'No_Usuario' => 'meliza@probusinessecuador.com',
                    'Txt_Email' => 'meliza@probusinessecuador.com',
                    'No_Nombres_Apellidos' => 'Meliza',
                    'plain_password' => 'MJY6eyUQRW',
                ],
                [
                    'No_Usuario' => 'francis28@probusinessecuador.com',
                    'Txt_Email' => 'francis28@probusinessecuador.com',
                    'No_Nombres_Apellidos' => 'Francis',
                    'plain_password' => '5zEdKDk7Zq',
                ],
            ];

            foreach ($cotizadores as $cotizador) {
                $guId = $this->ensureUsuario($orgId, $grupoCotizadorId, $cotizador);
                $this->ensureMenuAccesoCotizador($orgId, $guId, $menuIds);
            }
        });
    }

    public function down()
    {
        // No borra org/usuarios en prod: rollback destructivo.
        Log::warning('SeedProbusinessEcuadorOrgSocioBootstrap::down omitido (datos de bootstrap).');
    }

    private function ensureOrganizacion(): int
    {
        $existing = DB::table('organizacion')
            ->where('ID_Organizacion', self::ORG_ID)
            ->orWhere('No_Organizacion', 'Probusiness Ecuador')
            ->first();

        if ($existing) {
            $orgId = (int) $existing->ID_Organizacion;
            $updates = [];
            if (Schema::hasColumn('organizacion', 'id_pais') && empty($existing->id_pais)) {
                $updates['id_pais'] = self::PAIS_ECUADOR_ID;
            }
            if ((int) ($existing->Nu_Estado ?? 1) !== 1) {
                $updates['Nu_Estado'] = 1;
            }
            if ($updates) {
                DB::table('organizacion')->where('ID_Organizacion', $orgId)->update($updates);
            }

            return $orgId;
        }

        $payload = [
            'ID_Empresa' => self::EMPRESA_ID,
            'ID_Organizacion' => self::ORG_ID,
            'No_Organizacion' => 'Probusiness Ecuador',
            'Txt_Organizacion' => null,
            'Nu_Estado' => 1,
            'Nu_Estado_Sistema' => 0,
        ];
        if (Schema::hasColumn('organizacion', 'id_pais')) {
            $payload['id_pais'] = self::PAIS_ECUADOR_ID;
        }

        DB::table('organizacion')->insert($payload);

        return self::ORG_ID;
    }

    private function ensurePaisesHabilitados(int $orgId): void
    {
        if (! Schema::hasTable('organizacion_paises_habilitados')) {
            return;
        }

        $exists = DB::table('organizacion_paises_habilitados')
            ->where('organizacion_id', $orgId)
            ->where('id_pais', self::PAIS_ECUADOR_ID)
            ->exists();

        if (! $exists) {
            DB::table('organizacion_paises_habilitados')->insert([
                'organizacion_id' => $orgId,
                'id_pais' => self::PAIS_ECUADOR_ID,
            ]);
        }
    }

    private function ensurePortal(int $orgId): void
    {
        if (! Schema::hasTable('organizacion_portales')) {
            return;
        }

        $exists = DB::table('organizacion_portales')->where('organizacion_id', $orgId)->exists();
        if ($exists) {
            DB::table('organizacion_portales')->where('organizacion_id', $orgId)->update([
                'public_key' => self::PORTAL_PUBLIC_KEY,
                'url_clientes' => 'https://socioecuador.probusiness.pe/',
                'url_excel_confirmacion' => 'https://confirmacion-ecuador.probusiness.pe/',
                'url_datos_proveedor' => 'https://socioecuador.probusiness.pe/',
                'nombre_publico' => 'Empresa Ecuador',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('organizacion_portales')->insert([
            'organizacion_id' => $orgId,
            'public_key' => self::PORTAL_PUBLIC_KEY,
            'url_clientes' => 'https://socioecuador.probusiness.pe/',
            'url_excel_confirmacion' => 'https://confirmacion-ecuador.probusiness.pe/',
            'url_datos_proveedor' => 'https://socioecuador.probusiness.pe/',
            'drive_folder_id' => null,
            'logo_url' => null,
            'nombre_publico' => 'Empresa Ecuador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureMensajeria(int $orgId): void
    {
        if (! Schema::hasTable('organizacion_mensajeria')) {
            return;
        }

        $flujos = json_encode([
            'rotulado' => true,
            'documentos' => true,
            'datos_proveedor' => true,
            'cbm_alerta' => false,
            'arrive_date' => true,
            'cambio_consolidado' => true,
            'inspeccion' => true,
            'entrega' => false,
            'cobranza' => false,
            'reminder_pago' => false,
            'factura_guia' => false,
            'contabilidad' => false,
            'comprobante_form' => false,
            'calculadora' => false,
            'cotizacion_pdf' => false,
        ], JSON_UNESCAPED_UNICODE);

        $payload = [
            'envios_habilitados' => 1,
            'rotulado_habilitado' => 1,
            'flujos' => $flujos,
            'img_rotulado_paso1' => 'organizacion/2/rotulado/paso1_1789520111_6aa9e8ef7cf9b.png',
            'img_rotulado_paso2' => null,
            'img_rotulado_direccion' => 'organizacion/2/rotulado/direccion_1789520134_6aa9e90631c8d.jpeg',
            'updated_at' => now(),
        ];

        $exists = DB::table('organizacion_mensajeria')->where('organizacion_id', $orgId)->exists();
        if ($exists) {
            DB::table('organizacion_mensajeria')->where('organizacion_id', $orgId)->update($payload);

            return;
        }

        $payload['organizacion_id'] = $orgId;
        $payload['created_at'] = now();
        DB::table('organizacion_mensajeria')->insert($payload);
    }

    private function ensureWaInboxConfig(int $orgId): void
    {
        if (! Schema::hasTable('wa_inbox_organizacion_config')) {
            return;
        }

        // Secretos cifrados con APP_KEY de QA no sirven en prod: se dejan null (panel / env).
        $payload = [
            'enabled' => 0,
            'phone_number_id' => env('META_WHATSAPP_ORG2_PHONE_NUMBER_ID', '1062249786981832'),
            'waba_id' => env('META_WHATSAPP_ORG2_WABA_ID', '27140133382305301'),
            'graph_api_version' => 'v19.0',
            'default_language' => 'es_PE',
            'display_number' => env('META_WHATSAPP_ORG2_DISPLAY_NUMBER', '+51 991 351 346'),
            'legacy_fallback' => 1,
            'preview_from_template' => 1,
            'session_when_window_open' => 1,
            'updated_at' => now(),
        ];

        $exists = DB::table('wa_inbox_organizacion_config')->where('organizacion_id', $orgId)->exists();
        if ($exists) {
            DB::table('wa_inbox_organizacion_config')->where('organizacion_id', $orgId)->update($payload);

            return;
        }

        $payload['organizacion_id'] = $orgId;
        $payload['access_token'] = null;
        $payload['app_secret'] = null;
        $payload['webhook_verify_token'] = null;
        $payload['created_at'] = now();
        DB::table('wa_inbox_organizacion_config')->insert($payload);
    }

    private function ensureGrupo(int $orgId, string $nombre, string $descripcion): int
    {
        $existing = DB::table('grupo')
            ->where('ID_Organizacion', $orgId)
            ->where('No_Grupo', $nombre)
            ->first();

        if ($existing) {
            return (int) $existing->ID_Grupo;
        }

        return (int) DB::table('grupo')->insertGetId([
            'ID_Empresa' => self::EMPRESA_ID,
            'ID_Organizacion' => $orgId,
            'No_Grupo' => $nombre,
            'No_Grupo_Descripcion' => $descripcion,
            'Nu_Estado' => 1,
            'Nu_Tipo_Privilegio_Acceso' => 1,
            'Nu_Notificacion' => 0,
        ]);
    }

    /**
     * @return array<string, int> keys lógicos → ID_Menu
     */
    private function ensureMenus(int $orgId): array
    {
        $defs = [
            'ventas' => [
                'padre' => null,
                'Nu_Orden' => 1,
                'No_Menu' => 'Ventas',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => '',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 0,
                'url_intranet_v2' => 'cotizaciones/resumen',
            ],
            'consolidados' => [
                'padre' => null,
                'Nu_Orden' => 2,
                'No_Menu' => 'Consolidados',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => '',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => '',
            ],
            'chat' => [
                'padre' => null,
                'Nu_Orden' => 4,
                'No_Menu' => 'Chat',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:chat-bubble-left',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 0,
                'url_intranet_v2' => 'coordinacion/whatsapp-inbox',
            ],
            'clientes' => [
                'padre' => null,
                'Nu_Orden' => 4,
                'No_Menu' => 'Clientes',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:user',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 0,
                'url_intranet_v2' => 'basedatos/clientes',
            ],
            'panel' => [
                'padre' => null,
                'Nu_Orden' => 0,
                'No_Menu' => 'Panel de Acceso',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'mdi:settings',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => '',
            ],
            'cotizador' => [
                'padre' => 'ventas',
                'Nu_Orden' => 1,
                'No_Menu' => 'Cotizador',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:calculator',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 0,
                'url_intranet_v2' => 'cotizaciones/resumen',
            ],
            'abiertos' => [
                'padre' => 'consolidados',
                'Nu_Orden' => 1,
                'No_Menu' => 'Abiertos',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => '',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => 'cargaconsolidada/abiertos',
            ],
            'embarcados' => [
                'padre' => 'consolidados',
                'Nu_Orden' => 2,
                'No_Menu' => 'Embarcados',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => '',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => 'cargaconsolidada/completados',
            ],
            'chat_hijo' => [
                'padre' => 'chat',
                'Nu_Orden' => 3,
                'No_Menu' => 'Chat',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:chat-bubble-left',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => 'coordinacion/whatsapp-inbox',
            ],
            'clientes_hijo' => [
                'padre' => 'clientes',
                'Nu_Orden' => 4,
                'No_Menu' => 'Clientes',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:user',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => 'basedatos/clientes',
            ],
            'usuarios' => [
                'padre' => 'panel',
                'Nu_Orden' => 1,
                'No_Menu' => 'Usuarios',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:user',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => 'panel-acceso/usuarios',
            ],
            'permisos' => [
                'padre' => 'panel',
                'Nu_Orden' => 2,
                'No_Menu' => 'Permisos',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:list-bullet',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'show_father' => 1,
                'url_intranet_v2' => 'panel-acceso/permisos',
            ],
        ];

        $ids = [];
        foreach ($defs as $key => $def) {
            $padreKey = $def['padre'];
            $padreId = $padreKey === null ? 0 : ($ids[$padreKey] ?? 0);
            $ids[$key] = $this->findOrCreateMenu($orgId, $padreId, $def);
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function findOrCreateMenu(int $orgId, int $padreId, array $def): int
    {
        $query = DB::table('menu')
            ->where('ID_Organizacion', $orgId)
            ->where('No_Menu', $def['No_Menu'])
            ->where('ID_Padre', $padreId);

        if (($def['url_intranet_v2'] ?? '') !== '') {
            $query->where('url_intranet_v2', $def['url_intranet_v2']);
        }

        $existingId = $query->value('ID_Menu');
        if ($existingId) {
            return (int) $existingId;
        }

        $payload = [
            'ID_Organizacion' => $orgId,
            'ID_Padre' => $padreId,
            'Nu_Orden' => $def['Nu_Orden'],
            'No_Menu' => $def['No_Menu'],
            'No_Menu_Url' => $def['No_Menu_Url'],
            'No_Class_Controller' => $def['No_Class_Controller'],
            'Txt_Css_Icons' => $def['Txt_Css_Icons'],
            'Nu_Separador' => $def['Nu_Separador'],
            'Nu_Seguridad' => $def['Nu_Seguridad'],
            'Nu_Activo' => $def['Nu_Activo'],
            'Nu_Tipo_Sistema' => $def['Nu_Tipo_Sistema'],
            'Txt_Url_Video' => null,
            'No_Menu_China' => $def['No_Menu'],
            'url_intranet_v2' => $def['url_intranet_v2'],
            'show_father' => $def['show_father'],
        ];

        return (int) DB::table('menu')->insertGetId($payload);
    }

    /**
     * @param  array<string, int>  $menuIds
     */
    private function ensureWhatsappAdminMenu(int $orgId, array &$menuIds): void
    {
        $existing = DB::table('menu')
            ->where('ID_Organizacion', $orgId)
            ->where('url_intranet_v2', 'admin/whatsapp')
            ->value('ID_Menu');

        if ($existing) {
            $menuIds['whatsapp_admin'] = (int) $existing;

            return;
        }

        $menuIds['whatsapp_admin'] = (int) DB::table('menu')->insertGetId([
            'ID_Organizacion' => $orgId,
            'ID_Padre' => 0,
            'Nu_Orden' => 99,
            'No_Menu' => 'WhatsApp',
            'No_Menu_Url' => 'admin/whatsapp',
            'No_Class_Controller' => 'WhatsappInboxConfigController',
            'Txt_Css_Icons' => 'fa fa-whatsapp',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => 'WhatsApp',
            'show_father' => 0,
            'url_intranet_v2' => 'admin/whatsapp',
        ]);
    }

    /**
     * @param  array{No_Usuario: string, Txt_Email: string, No_Nombres_Apellidos: string, plain_password: string}  $data
     */
    private function ensureUsuario(int $orgId, int $grupoId, array $data): int
    {
        $existing = DB::table('usuario')
            ->where('ID_Organizacion', $orgId)
            ->where(function ($q) use ($data) {
                $q->where('No_Usuario', $data['No_Usuario'])
                    ->orWhere('Txt_Email', $data['Txt_Email']);
            })
            ->first();

        $ci = new CodeIgniterEncryption();
        $encrypted = $ci->encryptPassword($data['plain_password']);

        if ($existing) {
            $userId = (int) $existing->ID_Usuario;
            DB::table('usuario')->where('ID_Usuario', $userId)->update([
                'ID_Grupo' => $grupoId,
                'No_Nombres_Apellidos' => $data['No_Nombres_Apellidos'],
                'Nu_Estado' => 1,
                'No_Password' => $encrypted,
                'No_Password_Sin_Encriptar' => $data['plain_password'],
            ]);
        } else {
            $userId = (int) DB::table('usuario')->insertGetId([
                'ID_Empresa' => self::EMPRESA_ID,
                'ID_Organizacion' => $orgId,
                'ID_Grupo' => $grupoId,
                'No_Usuario' => $data['No_Usuario'],
                'No_Password' => $encrypted,
                'No_Password_Sin_Encriptar' => $data['plain_password'],
                'No_Nombres_Apellidos' => $data['No_Nombres_Apellidos'],
                'Txt_Email' => $data['Txt_Email'],
                'Nu_Estado' => 1,
                'Nu_Codigo_Pais' => '1',
                'Nu_Setting_Panel_Menu_Izquierdo' => 0,
            ]);
        }

        $gu = DB::table('grupo_usuario')
            ->where('ID_Organizacion', $orgId)
            ->where('ID_Usuario', $userId)
            ->where('ID_Grupo', $grupoId)
            ->first();

        if ($gu) {
            return (int) $gu->ID_Grupo_Usuario;
        }

        return (int) DB::table('grupo_usuario')->insertGetId([
            'ID_Empresa' => self::EMPRESA_ID,
            'ID_Organizacion' => $orgId,
            'ID_Grupo' => $grupoId,
            'ID_Usuario' => $userId,
        ]);
    }

    /**
     * @param  array<string, int>  $menuIds
     */
    private function ensureMenuAccesoSocio(int $orgId, int $grupoUsuarioId, array $menuIds): void
    {
        $inicioId = (int) (DB::table('menu')->where('No_Menu', 'Inicio')->where('ID_Padre', 0)->orderBy('ID_Menu')->value('ID_Menu') ?: 0);

        $permisos = [
            'ventas' => [1, 1, 1, 1],
            'cotizador' => [1, 0, 0, 0],
            'consolidados' => [1, 1, 1, 1],
            'abiertos' => [1, 0, 0, 0],
            'embarcados' => [1, 0, 0, 0],
            'chat' => [1, 1, 1, 1],
            'chat_hijo' => [1, 0, 0, 0],
            'clientes' => [1, 1, 1, 1],
            'clientes_hijo' => [1, 0, 0, 0],
            'panel' => [1, 1, 1, 1],
            'usuarios' => [1, 0, 0, 0],
            'permisos' => [1, 0, 0, 0],
            'whatsapp_admin' => [1, 1, 1, 1],
        ];

        foreach ($permisos as $key => $flags) {
            if (empty($menuIds[$key])) {
                continue;
            }
            $this->upsertMenuAcceso($grupoUsuarioId, (int) $menuIds[$key], $flags);
        }

        if ($inicioId > 0) {
            $this->upsertMenuAcceso($grupoUsuarioId, $inicioId, [1, 0, 1, 0]);
        }
    }

    /**
     * Cotizadores: mismos menús operativos que Socio, sin Panel de Acceso.
     *
     * @param  array<string, int>  $menuIds
     */
    private function ensureMenuAccesoCotizador(int $orgId, int $grupoUsuarioId, array $menuIds): void
    {
        $inicioId = (int) (DB::table('menu')->where('No_Menu', 'Inicio')->where('ID_Padre', 0)->orderBy('ID_Menu')->value('ID_Menu') ?: 0);

        $permisos = [
            'ventas' => [1, 1, 1, 1],
            'cotizador' => [1, 0, 0, 0],
            'consolidados' => [1, 1, 1, 1],
            'abiertos' => [1, 0, 0, 0],
            'embarcados' => [1, 0, 0, 0],
            'chat' => [1, 1, 1, 1],
            'chat_hijo' => [1, 0, 0, 0],
            'clientes' => [1, 1, 1, 1],
            'clientes_hijo' => [1, 0, 0, 0],
        ];

        foreach ($permisos as $key => $flags) {
            if (empty($menuIds[$key])) {
                continue;
            }
            $this->upsertMenuAcceso($grupoUsuarioId, (int) $menuIds[$key], $flags);
        }

        if ($inicioId > 0) {
            $this->upsertMenuAcceso($grupoUsuarioId, $inicioId, [1, 0, 1, 0]);
        }
    }

    /**
     * @param  array{0:int,1:int,2:int,3:int}  $flags  consultar, agregar, editar, eliminar
     */
    private function upsertMenuAcceso(int $grupoUsuarioId, int $menuId, array $flags): void
    {
        $exists = DB::table('menu_acceso')
            ->where('ID_Grupo_Usuario', $grupoUsuarioId)
            ->where('ID_Menu', $menuId)
            ->exists();

        $payload = [
            'ID_Empresa' => self::EMPRESA_ID,
            'ID_Menu' => $menuId,
            'ID_Grupo_Usuario' => $grupoUsuarioId,
            'Nu_Consultar' => $flags[0],
            'Nu_Agregar' => $flags[1],
            'Nu_Editar' => $flags[2],
            'Nu_Eliminar' => $flags[3],
        ];

        if ($exists) {
            DB::table('menu_acceso')
                ->where('ID_Grupo_Usuario', $grupoUsuarioId)
                ->where('ID_Menu', $menuId)
                ->update($payload);

            return;
        }

        DB::table('menu_acceso')->insert($payload);
    }
}
