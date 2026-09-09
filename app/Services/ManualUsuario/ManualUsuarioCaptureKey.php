<?php

namespace App\Services\ManualUsuario;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Construye nombres estables y seguros para las capturas del manual.
 */
class ManualUsuarioCaptureKey
{
    public const MAX_LENGTH = 180;

    public static function make(
        string $modulo,
        string $role,
        string $flow,
        string $step,
        int $stepNumber,
        ?string $explicit = null
    ): string {
        if ($explicit !== null && trim($explicit) !== '') {
            return self::validate($explicit);
        }

        // El rol pertenece al contexto de autenticación y a la ruta del runner,
        // no a la identidad semántica de la captura.
        $segments = [
            self::segment($modulo, 'modulo'),
            self::segment($flow, 'flujo'),
            'paso-' . str_pad((string) max(1, $stepNumber), 2, '0', STR_PAD_LEFT)
                . '-' . self::segment($step, 'accion'),
        ];
        $key = implode('__', $segments);

        if (strlen($key) > self::MAX_LENGTH) {
            $key = substr($key, 0, self::MAX_LENGTH - 13) . '__' . substr(sha1($key), 0, 11);
        }

        return self::validate($key);
    }

    /**
     * Identidad compartida: alias canónico si existe, si no la propia capture_key.
     */
    public static function identity(?string $key, ?string $aliasOf = null): ?string
    {
        $alias = $aliasOf !== null ? trim($aliasOf) : '';
        if ($alias !== '') {
            return self::validate($alias);
        }
        if ($key === null || trim($key) === '') {
            return null;
        }

        return self::validate(trim($key));
    }

    public static function identityFromSnapshot(array $snapshot): ?string
    {
        $alias = !empty($snapshot['capture_alias_of']) ? (string) $snapshot['capture_alias_of'] : null;
        $key = !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;

        return self::identity($key, $alias);
    }

    public static function validate(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > self::MAX_LENGTH
            || !preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key)) {
            throw new InvalidArgumentException(
                'capture_key inválida. Usa solo minúsculas, números, punto, guion o guion bajo (máximo '
                . self::MAX_LENGTH . ' caracteres).'
            );
        }

        return $key;
    }

    public static function output(string $key): string
    {
        return self::validate($key) . '.png';
    }

    public static function screenId(string $screen): string
    {
        return self::segment($screen, 'pantalla');
    }

    public static function runnerOutput(
        string $role,
        string $screen,
        string $key
    ): string {
        return self::segment($role, 'rol') . '/'
            . self::screenId($screen) . '/'
            . self::validate($key) . '.png';
    }

    private static function segment(string $value, string $fallback): string
    {
        $slug = Str::slug(str_replace(['/', '\\'], '-', $value));

        return $slug !== '' ? $slug : $fallback;
    }
}
