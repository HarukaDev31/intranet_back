<?php

namespace App\Helpers;

use App\Models\UsuarioDatosFacturacion;

/**
 * Resuelve id_user buscando en usuario_datos_facturacion por DNI o RUC
 * (cuando UserLookupHelper no encuentra fila en users).
 */
class UsuarioDatosFacturacionLookupHelper
{
    /**
     * @param string|null $dni  DNI u otro documento de persona
     * @param string|null $ruc  RUC (si es distinto del primer argumento)
     * @return int|null id_user de la fila más reciente que coincida
     */
    public static function findIdUserByDniOrRuc(?string $dni = null, ?string $ruc = null): ?int
    {
        $digitsList = [];
        foreach ([$dni, $ruc] as $value) {
            $normalized = self::normalizeDocumentDigits($value);
            if ($normalized !== '') {
                $digitsList[$normalized] = true;
            }
        }

        if ($digitsList === []) {
            return null;
        }

        $dniCol = self::sqlDigitsOnly('dni');
        $rucCol = self::sqlDigitsOnly('ruc');

        $row = UsuarioDatosFacturacion::query()
            ->where(function ($q) use ($digitsList, $dniCol, $rucCol) {
                foreach (array_keys($digitsList) as $digits) {
                    $q->orWhere(function ($sub) use ($digits, $dniCol, $rucCol) {
                        $sub->whereRaw("{$dniCol} = ?", [$digits])
                            ->orWhereRaw("{$rucCol} = ?", [$digits]);
                    });
                }
            })
            ->whereNotNull('id_user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (!$row || empty($row->id_user)) {
            return null;
        }

        return (int) $row->id_user;
    }

    /**
     * Atajo cuando solo se dispone de un documento (cotización.documento: DNI o RUC).
     */
    public static function findIdUserByDocumento(?string $documento): ?int
    {
        return self::findIdUserByDniOrRuc($documento, $documento);
    }

    private static function normalizeDocumentDigits(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private static function sqlDigitsOnly(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '.', ''), '/', ''), '+', '')";
    }
}
