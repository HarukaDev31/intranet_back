<?php

namespace App\Services\ManualUsuario;

/**
 * Convierte textos del manual al formato plantilla Alumnos (viñetas, listas numeradas).
 */
class ManualUsuarioPlantillaTextFormatter
{
    /**
     * @param  mixed  $paraQue
     */
    public static function formatParaQue($paraQue): string
    {
        if (is_array($paraQue)) {
            $items = array_values(array_filter(array_map('trim', $paraQue)));

            return self::bulletList($items);
        }

        $text = trim((string) $paraQue);
        if ($text === '') {
            return '';
        }

        if (str_contains($text, '•') || preg_match('/^\s*Desde esta sección/u', $text)) {
            return $text;
        }

        if (str_contains($text, "\n")) {
            $lines = preg_split('/\R/u', $text) ?: [];
            $items = [];
            foreach ($lines as $line) {
                $line = trim(preg_replace('/^[-•*]\s*/u', '', trim($line)));
                if ($line !== '') {
                    $items[] = $line;
                }
            }
            if (count($items) > 1) {
                return self::bulletList($items);
            }
        }

        $sentences = self::splitSentences($text);
        if (count($sentences) > 1 && self::looksLikeActionList($sentences)) {
            return self::bulletList($sentences);
        }

        if (substr_count($text, ',') >= 2 && strlen($text) < 400) {
            $parts = array_map('trim', explode(',', $text));
            $parts = array_values(array_filter($parts));
            if (count($parts) > 1) {
                return self::bulletList($parts);
            }
        }

        return $text;
    }

    /**
     * Si el cuerpo es prosa corrida, intenta numerar frases (1. 2. 3.).
     */
    public static function formatFlowBody(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        if (preg_match('/^\s*\d+\.\s/m', $body) || str_contains($body, "\n•")) {
            return $body;
        }

        $sentences = self::splitSentences($body);
        if (count($sentences) < 2) {
            return $body;
        }

        if (!self::looksLikeStepList($sentences)) {
            return $body;
        }

        $lines = [];
        foreach ($sentences as $i => $sentence) {
            $lines[] = ($i + 1) . '. ' . $sentence;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $items
     */
    private static function bulletList(array $items): string
    {
        $items = array_values(array_filter(array_map(function ($item) {
            $item = trim((string) $item);
            $item = rtrim($item, '.');

            return $item !== '' ? $item . '.' : '';
        }, $items)));

        if ($items === []) {
            return '';
        }

        return "Desde esta sección puedes:\n" . implode("\n", array_map(function ($item) {
            return '• ' . $item;
        }, $items));
    }

    /**
     * @return array<int, string>
     */
    private static function splitSentences(string $text): array
    {
        $chunks = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $out = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function looksLikeActionList(array $parts): bool
    {
        if (count($parts) < 2) {
            return false;
        }

        $verbs = 0;
        foreach ($parts as $part) {
            if (preg_match('/^(Ver|Consultar|Buscar|Filtrar|Cambiar|Crear|Generar|Enviar|Registrar|Eliminar|Abrir|Entra|Entrar|Usar|Pulsa|Completa|Revisa|Ubica|Marca|Descarga|Sube|Guarda)/iu', $part)) {
                $verbs++;
            }
        }

        return $verbs >= max(1, (int) floor(count($parts) / 2));
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function looksLikeStepList(array $parts): bool
    {
        return self::looksLikeActionList($parts) || count($parts) >= 3;
    }
}
