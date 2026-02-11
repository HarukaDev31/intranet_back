<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * Helper para buscar un usuario en la tabla users por correo, teléfono o documento.
 * Usa la misma lógica en todos los puntos que necesitan vincular filas (cotización, cliente, etc.)
 * con el usuario: email exacto, teléfono normalizado (whatsapp/phone) con y sin prefijo 51, o DNI.
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
                $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
                $telefonoSin51 = preg_replace('/^51/', '', $telefonoLimpio);
                $normalized = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                $normalizedPhone = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                $q->orWhereRaw("({$normalized} LIKE ? OR {$normalized} LIKE ? OR {$normalizedPhone} LIKE ? OR {$normalizedPhone} LIKE ?)", [
                    "%{$telefonoLimpio}%",
                    "%{$telefonoSin51}%",
                    "%{$telefonoLimpio}%",
                    "%{$telefonoSin51}%"
                ]);
            }
            if (!empty($documento)) {
                $q->orWhere('dni', $documento);
            }
        });

        return $userQuery->first();
    }
}
