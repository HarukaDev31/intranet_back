<?php

namespace App\Services\Auth;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\User;
use App\Support\Organizacion\OrganizacionPortalUrls;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * «Ver como cliente»: Coordinación abre el portal de clientes con la cuenta del cliente de una entrega.
 * La intranet recibe un link con un código de un solo uso; el portal lo canjea por una sesión corta de
 * solo lectura (JWTExternalMiddleware rechaza cualquier escritura con este token).
 */
class ImpersonacionClienteService
{
    /** Claim del JWT: ID del usuario interno que está viendo la cuenta. */
    public const CLAIM = 'impersonated_by';

    public const MINUTOS_SESION = 30;

    private const SEGUNDOS_CODIGO = 60;

    /**
     * Cuenta del portal del cliente de la cotización. Solo coincidencias exactas (correo, luego documento, luego
     * teléfono) y dentro de su organización: si hay más de una cuenta no se elige ninguna.
     *
     * @return array{user: User|null, error: string|null}
     */
    public function cuentaDeCotizacion(Cotizacion $cotizacion): array
    {
        $orgId = OrganizacionPortalUrls::orgIdFromParent($cotizacion);
        $correo = trim((string) $cotizacion->getAttribute('correo'));
        $documento = trim((string) $cotizacion->getAttribute('documento'));
        $telefono = self::telefono($cotizacion->getAttribute('telefono'));

        $busquedas = [];
        if ($correo !== '') {
            $busquedas[] = fn () => $this->usuarios($orgId)->where('email', $correo)->limit(2)->get();
        }
        if ($documento !== '') {
            $busquedas[] = fn () => $this->usuarios($orgId)->where('dni', $documento)->limit(2)->get();
        }
        if (strlen($telefono) >= 8) {
            $busquedas[] = fn () => $this->usuarios($orgId)
                ->where(function ($q) use ($telefono) {
                    foreach (['whatsapp', 'phone'] as $columna) {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$columna}, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?",
                            ['%'.$telefono]
                        );
                    }
                })
                ->get()
                ->filter(fn (User $user) => in_array($telefono, [self::telefono($user->getAttribute('whatsapp')), self::telefono($user->getAttribute('phone'))], true))
                ->values();
        }

        foreach ($busquedas as $buscar) {
            /** @var Collection<int, User> $usuarios */
            $usuarios = $buscar();
            if ($usuarios->count() === 1) {
                return ['user' => $usuarios->first(), 'error' => null];
            }
            if ($usuarios->count() > 1) {
                return ['user' => null, 'error' => 'CUENTA_AMBIGUA'];
            }
        }

        return ['user' => null, 'error' => 'SIN_CUENTA'];
    }

    /** Link al portal con un código de un solo uso. El código va en el fragmento: no llega a logs ni al Referer. */
    public function crearLink(User $cliente, int $idUsuarioInterno, int $idCotizacion, ?string $ip): string
    {
        $orgId = OrganizacionPortalUrls::orgIdFromParent($cliente);
        $codigo = Str::random(64);
        Cache::put(self::llave($codigo), [
            'user_id' => (int) $cliente->getKey(),
            'organizacion_id' => $orgId,
            'impersonated_by' => $idUsuarioInterno,
        ], self::SEGUNDOS_CODIGO);

        Log::info('Ver como cliente: link generado', [
            'usuario_interno' => $idUsuarioInterno,
            'user_id' => (int) $cliente->getKey(),
            'id_cotizacion' => $idCotizacion,
            'ip' => $ip,
        ]);

        return OrganizacionPortalUrls::urlClientes($orgId).'/ver-como-cliente#codigo='.$codigo;
    }

    /**
     * Canjea el código (una sola vez) por una sesión del cliente.
     *
     * @return array{user: User, token: string, expires_in: int}|null
     */
    public function canjear(string $codigo, int $orgId, ?string $ip): ?array
    {
        $datos = $codigo === '' ? null : Cache::pull(self::llave($codigo));
        if (! is_array($datos) || (int) $datos['organizacion_id'] !== $orgId) {
            return null;
        }

        $user = User::query()->find($datos['user_id']);
        if (! $user instanceof User) {
            return null;
        }

        Log::info('Ver como cliente: sesión iniciada', [
            'usuario_interno' => $datos['impersonated_by'],
            'user_id' => (int) $user->getKey(),
            'ip' => $ip,
        ]);

        return [
            'user' => $user,
            'token' => $this->token($user, (int) $datos['impersonated_by']),
            'expires_in' => self::MINUTOS_SESION * 60,
        ];
    }

    private function token(User $user, int $idUsuarioInterno): string
    {
        /** @var \Tymon\JWTAuth\JWTAuth $jwt */
        $jwt = app('tymon.jwt.auth');
        $factory = $jwt->factory();
        $ttl = $factory->getTTL();
        try {
            $factory->setTTL(self::MINUTOS_SESION);

            return $jwt->customClaims([self::CLAIM => $idUsuarioInterno])->fromUser($user);
        } finally {
            // El factory es compartido: sin esto, el siguiente token del proceso heredaría el claim y la duración.
            $factory->setTTL($ttl);
            $factory->customClaims([])->emptyClaims();
            $jwt->customClaims([]);
        }
    }

    private function usuarios(int $orgId)
    {
        return User::query()->where(function ($q) use ($orgId) {
            $q->where('organizacion_id', $orgId);
            if ($orgId === OrganizacionPortalUrls::ADMIN_ORG) {
                // Cuentas antiguas sin organización: el login las trata como Probusiness.
                $q->orWhereNull('organizacion_id')->orWhere('organizacion_id', 0);
            }
        });
    }

    /** Solo dígitos, sin el prefijo de Perú. */
    private static function telefono(mixed $valor): string
    {
        $digitos = (string) preg_replace('/\D+/', '', (string) $valor);

        return strlen($digitos) === 11 && str_starts_with($digitos, '51') ? substr($digitos, 2) : $digitos;
    }

    private static function llave(string $codigo): string
    {
        return 'ver-como-cliente:'.hash('sha256', $codigo);
    }
}
