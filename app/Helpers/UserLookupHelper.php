<?php

namespace App\Helpers;

use App\Support\Phone\CountryPhoneHelper;
use Illuminate\Support\Facades\DB;

/**
 * Helper para buscar un usuario en la tabla users por correo, teléfono o documento.
 * Usa la misma lógica en todos los puntos que necesitan vincular filas (cotización, cliente, etc.)
 * con el usuario: email exacto, teléfono (internacional/nacional vía CountryPhoneHelper), o DNI.
 */
class UserLookupHelper
{
    /**
     * Busca el primer usuario que coincida por correo, teléfono o documento.
     *
     * @param string|null $correo   Email del contacto
     * @param string|null $telefono Teléfono (se normaliza para comparar con whatsapp/phone)
     * @param string|null $documento DNI del contacto
     * @return object|null Registro de users o null
     */
    public static function findUserByContact(?string $correo, ?string $telefono, ?string $documento): ?object
    {
        if (empty($correo) && empty($telefono) && empty($documento)) {
            return null;
        }

        $userQuery = DB::table('users')->where(function ($q) use ($correo, $telefono, $documento) {
            if (!empty($correo)) {
                $q->orWhere('email', $correo);
            }
            if (!empty($telefono)) {
                $variantes = CountryPhoneHelper::searchVariants($telefono);
                $normalized = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                $normalizedPhone = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                if ($variantes !== []) {
                    $q->orWhere(function ($q2) use ($normalized, $normalizedPhone, $variantes) {
                        foreach ($variantes as $variante) {
                            $q2->orWhereRaw("{$normalized} LIKE ?", ['%' . $variante . '%'])
                                ->orWhereRaw("{$normalizedPhone} LIKE ?", ['%' . $variante . '%']);
                        }
                    });
                }
            }
            if (!empty($documento)) {
                $q->orWhere('dni', $documento);
            }
        });

        return $userQuery->first();
    }
}
