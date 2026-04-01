<?php

namespace App\Services\Crm\Bitrix;

/**
 * Helpers para mapear leads landing curso → campos Bitrix (alineado al BitrixCrmService de ejemplo).
 */
class BitrixCursoSyncSupport
{
    /**
     * @return array{name: string, last_name: string}
     */
    public static function parseName($fullName)
    {
        $fullName = trim((string) $fullName);
        $parts = preg_split('/\s+/', $fullName, 2);

        $first = isset($parts[0]) ? $parts[0] : '';
        $second = isset($parts[1]) ? $parts[1] : '';

        return [
            'name' => $first !== '' ? ucfirst(strtolower($first)) : 'Lead',
            'last_name' => $second !== '' ? ucfirst(strtolower($second)) : '',
        ];
    }

    /**
     * Fecha Y-m-d para UF de Bitrix (registro).
     *
     * @param  array<string, mixed>  $leadRow
     */
    public static function registrationDateYmd(array $leadRow)
    {
        $raw = $leadRow['created_at'] ?? null;
        if ($raw === null || $raw === '') {
            return date('Y-m-d');
        }
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        $ts = strtotime((string) $raw);

        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    /**
     * @param  array<int, array{needles: array<int, string>, value: string}>  $pautaRules
     */
    public static function mapPauta($codigoCampana, array $pautaRules)
    {
        $campana = strtolower(trim((string) $codigoCampana));
        if ($campana === '') {
            return null;
        }

        foreach ($pautaRules as $rule) {
            $needles = isset($rule['needles']) && is_array($rule['needles']) ? $rule['needles'] : [];
            $value = isset($rule['value']) ? (string) $rule['value'] : '';
            foreach ($needles as $needle) {
                $n = strtolower((string) $needle);
                if ($n !== '' && strpos($campana, $n) !== false) {
                    return $value;
                }
            }
        }

        return null;
    }
}
