<?php

namespace App\Services\ManualUsuario;

/**
 * Identidad canónica de una captura: módulo + flujo + paso/acción.
 * El rol no forma parte de la identidad (News sí se comparte entre roles).
 */
class ManualUsuarioCapturaShare
{
    /**
     * @param  string|null  $explicit
     */
    public static function expectedKeyFromContext(
        $modulo,
        $flow,
        $stepTitle,
        $stepNumber,
        $explicit = null
    ) {
        $explicitKey = $explicit !== null ? trim((string) $explicit) : '';

        return ManualUsuarioCaptureKey::make(
            (string) $modulo,
            '',
            (string) $flow,
            (string) $stepTitle,
            (int) $stepNumber,
            $explicitKey !== '' ? $explicitKey : null
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function expectedKeyFromSnapshot(
        array $snapshot,
        $moduloFallback = '',
        $flowFallback = '',
        $tituloFallback = '',
        $ordenFallback = 1
    ) {
        $modulo = !empty($snapshot['capture_modulo'])
            ? (string) $snapshot['capture_modulo']
            : (string) $moduloFallback;
        $flow = ManualUsuarioCapturaNombre::stripPasosPrefix(
            !empty($snapshot['capture_flow'])
                ? (string) $snapshot['capture_flow']
                : (string) $flowFallback
        );
        $step = isset($snapshot['capture_step']) && is_array($snapshot['capture_step'])
            ? $snapshot['capture_step']
            : [];
        $stepTitle = ManualUsuarioCapturaNombre::stripFotoPrefix(
            (string) ($step['title'] ?? $tituloFallback)
        );
        $stepNumber = (int) ($step['number'] ?? $ordenFallback);
        if ($stepNumber < 1) {
            $stepNumber = 1;
        }
        if ($flow === '') {
            $flow = 'flujo';
        }
        if ($stepTitle === '') {
            $stepTitle = 'Paso ' . $stepNumber;
        }

        return self::expectedKeyFromContext($modulo, $flow, $stepTitle, $stepNumber);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return string|null
     */
    public static function currentIdentity(array $snapshot)
    {
        try {
            return ManualUsuarioCaptureKey::identityFromSnapshot($snapshot);
        } catch (\InvalidArgumentException $e) {
            return !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
        }
    }

    public static function shouldShare($leftIdentity, $rightIdentity)
    {
        $left = trim((string) $leftIdentity);
        $right = trim((string) $rightIdentity);

        return $left !== '' && $left === $right;
    }

    public static function keyLooksLikeStep($key)
    {
        return (bool) preg_match('/__paso-\d{2}-/', (string) $key);
    }

    public static function shouldRekey($currentIdentity, $expectedKey)
    {
        $current = trim((string) $currentIdentity);
        $expected = trim((string) $expectedKey);
        if ($expected === '') {
            return false;
        }
        if ($current === '' || $current === $expected) {
            return $current === '';
        }

        return !self::keyLooksLikeStep($current) && self::keyLooksLikeStep($expected);
    }

    public static function nombreNeedsRefresh($stored, $derived, $stepTitle, $pageTitulo)
    {
        $stored = trim((string) $stored);
        $derived = trim((string) $derived);
        if ($stored === '') {
            return $derived !== '';
        }
        if ($derived !== '' && strcasecmp($stored, $derived) === 0) {
            return false;
        }
        $pageTitulo = trim((string) $pageTitulo);
        if ($pageTitulo !== '' && mb_stripos($stored, $pageTitulo) === 0) {
            return true;
        }
        $stepTitle = ManualUsuarioCapturaNombre::stripFotoPrefix((string) $stepTitle);
        if ($stepTitle !== '' && $derived !== '' && mb_stripos($stored, $stepTitle) === false
            && mb_strpos($stored, ' — ') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Plan idempotente: rekey, desvincular media_id mal compartido y nombres de paso.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rekey: array<int, string>, unlink_media: int[], rename: array<int, string>, flows: array<int, array<string, mixed>>}
     */
    public static function planUngroup(array $rows)
    {
        $rekey = [];
        $unlink = [];
        $rename = [];
        $mediaBuckets = [];

        foreach ($rows as $row) {
            $blockId = (int) $row['block_id'];
            $expected = trim((string) ($row['expected'] ?? ''));
            $identity = trim((string) ($row['identity'] ?? ''));
            if ($expected !== '' && self::shouldRekey($identity, $expected)) {
                $rekey[$blockId] = $expected;
                $identity = $expected;
            }
            $derived = (string) ($row['derived'] ?? '');
            if (self::nombreNeedsRefresh(
                $row['nombre'] ?? '',
                $derived,
                $row['step_title'] ?? '',
                $row['page_titulo'] ?? ''
            )) {
                $rename[$blockId] = $derived;
            }
            $mediaId = !empty($row['media_id']) ? (int) $row['media_id'] : 0;
            if ($mediaId < 1) {
                continue;
            }
            $mediaBuckets[$mediaId][] = [
                'block_id' => $blockId,
                'identity' => $identity,
                'expected' => $expected,
                'media_alt' => (string) ($row['media_alt'] ?? ''),
                'flow' => (string) ($row['flow'] ?? ''),
                'page' => (string) ($row['page_titulo'] ?? ''),
                'modulo' => (string) ($row['modulo'] ?? ''),
            ];
        }

        $flows = [];
        foreach ($mediaBuckets as $members) {
            $expectedSet = [];
            foreach ($members as $member) {
                $canonical = $member['expected'] !== '' ? $member['expected'] : $member['identity'];
                if ($canonical !== '') {
                    $expectedSet[$canonical] = true;
                }
            }
            if (count($expectedSet) < 2) {
                continue;
            }
            $keepIdentity = null;
            foreach ($members as $member) {
                $alt = $member['media_alt'];
                if ($alt !== '' && ($alt === $member['identity'] || $alt === $member['expected'])) {
                    $keepIdentity = $alt;
                    break;
                }
            }
            $flowKey = $members[0]['modulo'] . '|' . $members[0]['flow'];
            if (!isset($flows[$flowKey])) {
                $flows[$flowKey] = [
                    'modulo' => $members[0]['modulo'],
                    'flow' => $members[0]['flow'],
                    'page' => $members[0]['page'],
                    'unlinked' => 0,
                ];
            }
            foreach ($members as $member) {
                $canonical = $member['expected'] !== '' ? $member['expected'] : $member['identity'];
                if ($keepIdentity !== null && $canonical === $keepIdentity) {
                    continue;
                }
                $unlink[] = $member['block_id'];
                $flows[$flowKey]['unlinked']++;
            }
        }

        return [
            'rekey' => $rekey,
            'unlink_media' => array_values(array_unique($unlink)),
            'rename' => $rename,
            'flows' => array_values($flows),
        ];
    }
}
