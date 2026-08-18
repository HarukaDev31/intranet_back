<?php

namespace App\Services\ManualUsuario;

/**
 * Nombre visible de una captura: no usa la capture_key cruda.
 */
class ManualUsuarioCapturaNombre
{
    public static function resolve(
        ?string $stored,
        array $snapshot = [],
        ?string $blockTitulo = null,
        ?string $pageTitulo = null
    ): string {
        $stored = trim((string) $stored);
        if ($stored !== '') {
            return $stored;
        }

        $fromSnapshot = trim((string) ($snapshot['nombre'] ?? ''));
        if ($fromSnapshot !== '') {
            return $fromSnapshot;
        }

        return self::fromSnapshot($snapshot, $blockTitulo, $pageTitulo);
    }

    public static function fromSnapshot(
        array $snapshot,
        ?string $blockTitulo = null,
        ?string $pageTitulo = null
    ): string {
        $flow = self::stripPasosPrefix((string) ($snapshot['capture_flow'] ?? ''));
        $step = '';
        if (isset($snapshot['capture_step']) && is_array($snapshot['capture_step'])) {
            $step = self::stripFotoPrefix((string) ($snapshot['capture_step']['title'] ?? ''));
        }

        if ($flow !== '' && $step !== '' && strcasecmp($flow, $step) !== 0) {
            return $flow . ' — ' . $step;
        }
        if ($flow !== '') {
            return $flow;
        }
        if ($step !== '') {
            return $step;
        }

        $titulo = self::stripFotoPrefix((string) $blockTitulo);
        $page = trim((string) $pageTitulo);
        if ($titulo !== '' && $page !== '' && strcasecmp($page, $titulo) !== 0) {
            return $page . ' — ' . $titulo;
        }
        if ($titulo !== '') {
            return $titulo;
        }
        if ($page !== '') {
            return $page;
        }

        return 'Imagen del manual';
    }

    public static function stripFotoPrefix(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $stripped = preg_replace('/^Foto\s+\d+\s*[—–-]\s*/u', '', $value);

        return trim((string) $stripped);
    }

    public static function stripPasosPrefix(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $stripped = preg_replace('/^Pasos\s*[—–-]?\s*/u', '', $value);

        return trim((string) $stripped);
    }
}
