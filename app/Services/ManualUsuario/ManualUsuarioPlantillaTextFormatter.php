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
     * Numeración con saltos de línea para bloques de texto (flujos, consideraciones, ejemplo, etc.).
     */
    public static function formatNumberedSteps(string $text): string
    {
        $text = trim($text);
        if ($text === '' || mb_strtolower($text) === 'pendiente de definir') {
            return $text;
        }

        if (self::hasStructuredSublist($text)) {
            return self::normalizeStructuredText($text);
        }

        if (self::isFullyNumbered($text)) {
            return self::normalizeNumberedText($text);
        }

        if (preg_match('/\n\s*\n/u', $text)) {
            $parts = preg_split('/\n\s*\n/u', $text) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts)));
            if (count($parts) > 1) {
                return self::numberLines($parts);
            }
        }

        if (str_contains($text, "\n")) {
            $lines = preg_split('/\R/u', $text) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines)));
            if (count($lines) > 1) {
                return self::numberLines($lines);
            }
        }

        $sentences = self::splitSentences($text);
        if (count($sentences) >= 2) {
            return self::numberLines($sentences);
        }

        return $text;
    }

    /**
     * Pasos de flujo: numeración con saltos de línea.
     */
    public static function formatFlowBody(string $body): string
    {
        return self::formatNumberedSteps(trim($body));
    }

    /**
     * QA / callout según título del bloque.
     */
    public static function formatQaBlock(string $titulo, string $body): string
    {
        $key = mb_strtolower(trim($titulo));
        if (str_contains($key, 'para qué') || str_contains($key, 'para que')) {
            return self::formatParaQue($body);
        }
        if (str_contains($key, 'ver también') || str_contains($key, 'ver tambien')) {
            return trim($body);
        }

        return self::formatNumberedSteps($body);
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
     * @param  array<int, string>  $lines
     */
    private static function numberLines(array $lines): string
    {
        $out = [];
        $n = 1;
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^\d+\.\s*/u', '', $line) ?? $line;
            $out[] = $n . '. ' . $line;
            $n++;
        }

        return implode("\n", $out);
    }

    private static function hasStructuredSublist(string $text): bool
    {
        return str_contains($text, "\n•")
            || (bool) preg_match('/^\d+\.\s[^\n]+\n(?:•|\d+\.)/m', $text);
    }

    private static function normalizeStructuredText(string $text): string
    {
        $blocks = preg_split('/\n(?=\d+\.\s)/u', $text) ?: [$text];
        $out = [];
        $n = 1;

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            if (preg_match('/^\d+\.\s/s', $block)) {
                $block = preg_replace('/^\d+\.\s*/u', '', $block) ?? $block;
            }

            $lines = preg_split('/\R/u', $block) ?: [$block];
            $head = trim((string) array_shift($lines));
            $tail = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $tail[] = $line;
                }
            }

            $step = $n . '. ' . $head;
            if ($tail !== []) {
                $step .= "\n" . implode("\n", $tail);
            }
            $out[] = $step;
            $n++;
        }

        return implode("\n", $out);
    }

    private static function isFullyNumbered(string $text): bool
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $meaningful = 0;
        $numbered = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '•')) {
                continue;
            }
            $meaningful++;
            if (preg_match('/^\d+\.\s/u', $line)) {
                $numbered++;
            }
        }

        return $meaningful > 0 && $numbered === $meaningful;
    }

    private static function normalizeNumberedText(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\d+\.\s/u', trim($line))) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = trim($line);
                continue;
            }

            if ($current === null) {
                $current = trim($line);
                continue;
            }

            $current .= "\n" . trim($line);
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return self::numberLines(array_map(function ($block) {
            return preg_replace('/^\d+\.\s*/u', '', $block) ?? $block;
        }, $blocks));
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
            if (preg_match('/^(Ver|Consultar|Buscar|Filtrar|Cambiar|Crear|Generar|Enviar|Registrar|Eliminar|Abrir|Entra|Entrar|Usar|Pulsa|Completa|Revisa|Ubica|Marca|Descarga|Sube|Guarda|Si |No |Desde|Vuelve|Primero|Luego|Ana |El |La |Los |Las )/iu', $part)) {
                $verbs++;
            }
        }

        return $verbs >= max(1, (int) floor(count($parts) / 2));
    }
}
