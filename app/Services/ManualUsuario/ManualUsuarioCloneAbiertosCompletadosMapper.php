<?php

namespace App\Services\ManualUsuario;

/**
 * Empareja un paso de Completados con el equivalente de Abiertos.
 * No mezcla flujos distintos ni el paso N de un rol con el paso N de otro.
 */
class ManualUsuarioCloneAbiertosCompletadosMapper
{
    public const MATCH_ROLE_FLOW_STEP = 'role_flow_step';
    public const MATCH_SWAPPED_KEY = 'swapped_key';
    public const MATCH_FLOW_TITLE = 'flow_title';

    public static function flowKey($flow): string
    {
        return mb_strtolower(ManualUsuarioCapturaNombre::stripPasosPrefix((string) $flow));
    }

    public static function stepTitleKey($title): string
    {
        return mb_strtolower(ManualUsuarioCapturaNombre::stripFotoPrefix((string) $title));
    }

    public static function swappedCaptureKey($sourceKey, $sourceModulo, $targetModulo): ?string
    {
        $sourceSlug = ManualUsuarioCaptureKey::screenId((string) $sourceModulo);
        $targetSlug = ManualUsuarioCaptureKey::screenId((string) $targetModulo);
        $key = trim((string) $sourceKey);
        if ($key === '' || $sourceSlug === '' || $targetSlug === '' || $sourceSlug === $targetSlug) {
            return null;
        }
        if (!str_starts_with($key, $sourceSlug . '__')) {
            return null;
        }

        return $targetSlug . substr($key, strlen($sourceSlug));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @param  array<string, mixed>  $targetRow
     * @return array<string, mixed>|null
     */
    public static function match(array $sourceRows, array $targetRow): ?array
    {
        $byRole = self::preferWithMedia(self::filter($sourceRows, function (array $src) use ($targetRow) {
            return ($src['role'] ?? '') === ($targetRow['role'] ?? '')
                && self::sameFlow($src, $targetRow)
                && self::sameStepNumber($src, $targetRow);
        }));
        if ($byRole) {
            return $byRole + ['match' => self::MATCH_ROLE_FLOW_STEP];
        }

        $targetKey = trim((string) ($targetRow['capture_key'] ?? ''));
        $swapped = self::preferWithMedia(self::filter($sourceRows, function (array $src) use ($targetRow, $targetKey) {
            if ($targetKey === '') {
                return false;
            }
            $expected = self::swappedCaptureKey(
                $src['capture_key'] ?? '',
                $src['modulo'] ?? '',
                $targetRow['modulo'] ?? ''
            );

            return $expected !== null && $expected === $targetKey;
        }));
        if ($swapped) {
            return $swapped + ['match' => self::MATCH_SWAPPED_KEY];
        }

        $byTitle = self::preferWithMedia(self::filter($sourceRows, function (array $src) use ($targetRow) {
            return self::sameFlow($src, $targetRow)
                && self::sameStepTitle($src, $targetRow);
        }));
        if ($byTitle) {
            return $byTitle + ['match' => self::MATCH_FLOW_TITLE];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @param  array<int, array<string, mixed>>  $targetRows
     * @return array{links: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public static function plan(array $sourceRows, array $targetRows): array
    {
        $groups = [];
        foreach ($targetRows as $row) {
            $key = trim((string) ($row['capture_key'] ?? ''));
            if ($key === '') {
                $key = 'block-' . (int) ($row['block_id'] ?? 0);
            }
            $groups[$key][] = $row;
        }

        $links = [];
        $skipped = [];
        foreach ($groups as $targetKey => $rows) {
            $matched = null;
            foreach ($rows as $row) {
                $matched = self::match($sourceRows, $row);
                if ($matched) {
                    break;
                }
            }
            if ($matched === null) {
                $skipped[] = [
                    'target_key' => $targetKey,
                    'reason' => 'no_equivalent',
                    'flow' => (string) ($rows[0]['flow'] ?? ''),
                    'step_number' => (int) ($rows[0]['step_number'] ?? 0),
                    'step_title' => (string) ($rows[0]['step_title'] ?? ''),
                    'target_block_ids' => array_map(fn (array $r) => (int) $r['block_id'], $rows),
                ];
                continue;
            }
            $links[] = [
                'match' => $matched['match'],
                'flow' => (string) ($rows[0]['flow'] ?? $matched['flow'] ?? ''),
                'step_number' => (int) ($rows[0]['step_number'] ?? $matched['step_number'] ?? 0),
                'source_title' => (string) ($matched['step_title'] ?? ''),
                'target_title' => (string) ($rows[0]['step_title'] ?? ''),
                'source_key' => (string) ($matched['capture_key'] ?? ''),
                'target_key' => $targetKey,
                'source_media_id' => !empty($matched['media_id']) ? (int) $matched['media_id'] : null,
                'source_path' => $matched['media_path'] ?? null,
                'source_output' => (string) ($matched['capture_output'] ?? (($matched['capture_key'] ?? '') !== '' ? $matched['capture_key'] . '.png' : '')),
                'source_block_ids' => self::blockIdsForKey($sourceRows, (string) ($matched['capture_key'] ?? '')),
                'target_block_ids' => array_map(fn (array $r) => (int) $r['block_id'], $rows),
            ];
        }

        return ['links' => $links, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>):bool  $pred
     * @return array<int, array<string, mixed>>
     */
    private static function filter(array $rows, callable $pred): array
    {
        return array_values(array_filter($rows, $pred));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function preferWithMedia(array $rows): ?array
    {
        if (!$rows) {
            return null;
        }
        foreach ($rows as $row) {
            if (!empty($row['media_id'])) {
                return $row;
            }
        }

        return $rows[0];
    }

    private static function sameFlow(array $left, array $right): bool
    {
        $a = self::flowKey($left['flow'] ?? '');
        $b = self::flowKey($right['flow'] ?? '');

        return $a !== '' && $a === $b;
    }

    private static function sameStepNumber(array $left, array $right): bool
    {
        $a = (int) ($left['step_number'] ?? 0);
        $b = (int) ($right['step_number'] ?? 0);

        return $a >= 1 && $a === $b;
    }

    private static function sameStepTitle(array $left, array $right): bool
    {
        $a = self::stepTitleKey($left['step_title'] ?? '');
        $b = self::stepTitleKey($right['step_title'] ?? '');

        return $a !== '' && $a === $b;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return int[]
     */
    private static function blockIdsForKey(array $rows, string $key): array
    {
        if ($key === '') {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            if ((string) ($row['capture_key'] ?? '') === $key) {
                $ids[] = (int) $row['block_id'];
            }
        }

        return array_values(array_unique($ids));
    }
}
