<?php

namespace App\Helpers;

use App\Support\Phone\CountryPhoneHelper;
use Illuminate\Support\Facades\DB;

/**
 * Busca una fila en `clientes` por correo, teléfono o documento, dentro de una org.
 * El teléfono usa CountryPhoneHelper: dígitos internacionales + nacionales
 * (se quita el prefijo del país, no solo +51).
 */
class ClienteLookupHelper
{
    /**
     * @param string|null $correo
     * @param string|null $telefono
     * @param string|null $documento
     * @param int $organizacionId
     * @param string|null $callingCode prefijo del contenedor (si la org es multi-país)
     * @return object|null
     */
    public static function findClienteByContact($correo, $telefono, $documento, $organizacionId, $callingCode = null)
    {
        $correo = $correo !== null ? trim($correo) : '';
        $telefono = $telefono !== null ? trim($telefono) : '';
        $documento = $documento !== null ? trim($documento) : '';
        $organizacionId = (int) $organizacionId;

        if ($correo === '' && $telefono === '' && $documento === '') {
            return null;
        }

        if ($organizacionId <= 0) {
            return null;
        }

        $query = DB::table('clientes')->where('organizacion_id', $organizacionId);

        $query->where(function ($q) use ($correo, $telefono, $documento, $callingCode) {
            if ($telefono !== '') {
                self::applyPhoneMatch($q, $telefono, 'telefono', $callingCode);
            }
            if ($documento !== '') {
                $q->orWhere('documento', $documento);
            }
            if ($correo !== '') {
                $q->orWhere(function ($q2) use ($correo) {
                    $q2->whereNotNull('correo')
                        ->where('correo', '!=', '')
                        ->where('correo', $correo);
                });
            }
        });

        return $query->first();
    }

    /**
     * Compara teléfono: REPLACE de espacios/-/()/+ y variantes
     * internacional / nacional del país (CountryPhoneHelper).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $telefono
     * @param string $column  columna o alias.columna
     * @param string|null $callingCode
     * @return void
     */
    public static function applyPhoneMatch($query, $telefono, $column = 'telefono', $callingCode = null)
    {
        $full = CountryPhoneHelper::ensureCountryCode($telefono, $callingCode);
        $digits = $full !== '' ? $full : CountryPhoneHelper::digits($telefono);
        $national = CountryPhoneHelper::nationalNumber($digits);
        $variantes = [];
        foreach ([$digits, $national] as $v) {
            if ($v !== '' && strlen($v) >= 7) {
                $variantes[$v] = true;
            }
        }
        $variantes = array_keys($variantes);
        if ($variantes === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $normalizedCol = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $column . ', " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';

        $query->where(function ($q) use ($normalizedCol, $variantes) {
            foreach ($variantes as $i => $variante) {
                $method = $i === 0 ? 'whereRaw' : 'orWhereRaw';
                $q->{$method}("{$normalizedCol} = ?", [$variante]);
            }
        });
    }

    /**
     * @param string $telefono
     * @return array<int, string>
     */
    public static function phoneSearchVariants($telefono)
    {
        return CountryPhoneHelper::searchVariants($telefono);
    }
}
