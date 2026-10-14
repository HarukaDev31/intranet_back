<?php

namespace App\Services\ApiClients;

use App\Helpers\CodeIgniterEncryption;
use App\Models\ApiClient;
use App\Models\Organizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta y ciclo de vida de los API clients (tokens, scopes, límites, usuario BOT).
 */
class ApiClientService
{
    public const TOKEN_PREFIX = 'pbk_';

    /**
     * Genera un token nuevo: prefijo visible + 48 hex aleatorios (192 bits).
     *
     * @return array{token: string, hash: string, prefix: string}
     */
    public function generarToken(): array
    {
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(24));

        return [
            'token' => $token,
            'hash' => self::hashToken($token),
            'prefix' => substr($token, 0, 12),
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    public function scopesValidos(array $scopes): array
    {
        $permitidos = array_keys((array) config('api_clients.scopes', []));

        return array_values(array_unique(array_intersect($scopes, $permitidos)));
    }

    /**
     * Crea el cliente + el usuario BOT de la organización. El token en claro solo se devuelve aquí.
     *
     * @param  array{organizacion_id: int, nombre: string, scopes: array<int, string>, rate_limit_per_minute?: int, daily_quota?: int}  $datos
     * @return array{client: ApiClient, token: string}
     */
    public function crear(array $datos, ?int $creadoPor): array
    {
        return DB::transaction(function () use ($datos, $creadoPor) {
            $org = Organizacion::query()->findOrFail((int) $datos['organizacion_id']);
            $bot = $this->crearUsuarioBot($org, (string) $datos['nombre']);
            $t = $this->generarToken();

            $client = ApiClient::query()->create([
                'organizacion_id' => (int) $org->getAttribute('ID_Organizacion'),
                'nombre' => $datos['nombre'],
                'token_hash' => $t['hash'],
                'token_prefix' => $t['prefix'],
                'scopes' => $this->scopesValidos($datos['scopes']),
                'id_usuario_bot' => $bot,
                'rate_limit_per_minute' => $this->limite($datos['rate_limit_per_minute'] ?? null, (int) config('api_clients.default_rate_limit_per_minute'), (int) config('api_clients.max_rate_limit_per_minute')),
                'daily_quota' => $this->limite($datos['daily_quota'] ?? null, (int) config('api_clients.default_daily_quota'), (int) config('api_clients.max_daily_quota')),
                'activo' => true,
                'created_by' => $creadoPor,
            ]);

            return ['client' => $client, 'token' => $t['token']];
        });
    }

    /**
     * Reemplaza el token (el anterior deja de funcionar al instante).
     *
     * @return string Token en claro (única vez que se ve)
     */
    public function rotarToken(ApiClient $client): string
    {
        $t = $this->generarToken();
        $client->forceFill([
            'token_hash' => $t['hash'],
            'token_prefix' => $t['prefix'],
            'revoked_at' => null,
        ])->save();

        return $t['token'];
    }

    public function revocar(ApiClient $client): void
    {
        $client->forceFill(['activo' => false, 'revoked_at' => now()])->save();
    }

    /**
     * @param mixed $valor
     */
    private function limite($valor, int $default, int $max): int
    {
        $n = is_numeric($valor) ? (int) $valor : $default;

        return max(1, min($n, $max));
    }

    /**
     * Usuario BOT de la organización (grupo "Integracion"): es el vendedor de lo creado por API
     * y permite filtrar esa actividad. No puede iniciar sesión: la contraseña es aleatoria,
     * nunca se guarda en claro y nadie la conoce.
     */
    private function crearUsuarioBot(Organizacion $org, string $nombre): int
    {
        $orgId = (int) $org->getAttribute('ID_Organizacion');
        $empresaId = (int) $org->getAttribute('ID_Empresa');
        $nombreGrupo = (string) config('api_clients.grupo_integracion', 'Integracion');

        $grupoId = DB::table('grupo')
            ->where('ID_Organizacion', $orgId)
            ->where('No_Grupo', $nombreGrupo)
            ->value('ID_Grupo');
        if (!$grupoId) {
            $grupoId = DB::table('grupo')->insertGetId([
                'ID_Empresa' => $empresaId,
                'ID_Organizacion' => $orgId,
                'No_Grupo' => $nombreGrupo,
                'No_Grupo_Descripcion' => 'Usuario BOT de integracion API (no inicia sesion)',
                'Nu_Estado' => 1,
                'Nu_Tipo_Privilegio_Acceso' => 1,
                'Nu_Notificacion' => 0,
            ]);
        }

        $login = 'api-bot-' . $orgId . '-' . strtolower(Str::random(8)) . '@integracion.probusiness.pe';
        $password = (new CodeIgniterEncryption())->encrypt(Str::random(48));
        if (!$password) {
            throw new \RuntimeException('No se pudo generar la credencial del usuario BOT.');
        }

        $usuarioId = (int) DB::table('usuario')->insertGetId([
            'ID_Empresa' => $empresaId,
            'ID_Organizacion' => $orgId,
            'ID_Grupo' => $grupoId,
            'Nu_Codigo_Pais' => '1',
            'No_Usuario' => $login,
            'No_Password' => $password,
            'No_Password_Sin_Encriptar' => null,
            'No_Nombres_Apellidos' => mb_substr('API ' . $nombre, 0, 100),
            'Txt_Email' => null,
            'Nu_Estado' => 1,
            'Nu_Setting_Panel_Menu_Izquierdo' => 0,
        ]);

        DB::table('grupo_usuario')->insert([
            'ID_Empresa' => $empresaId,
            'ID_Organizacion' => $orgId,
            'ID_Grupo' => $grupoId,
            'ID_Usuario' => $usuarioId,
        ]);

        return $usuarioId;
    }
}
