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

    /**
     * Resuelve varios contactos en una query y los re-asocia en PHP.
     *
     * @param  array<int|string, array{correo:?string,telefono:?string,documento:?string}>  $contacts
     * @return array<int|string, object|null>
     */
    public static function findUsersIndexedByContact(array $contacts): array
    {
        $result = [];
        foreach ($contacts as $key => $_) {
            $result[$key] = null;
        }

        $correos = [];
        $documentos = [];
        $variantes = [];
        foreach ($contacts as $contact) {
            if (!empty($contact['correo'])) {
                $correos[] = $contact['correo'];
            }
            if (!empty($contact['documento'])) {
                $documentos[] = $contact['documento'];
            }
            if (!empty($contact['telefono'])) {
                foreach (self::phoneSearchVariants($contact['telefono']) as $variante) {
                    $variantes[$variante] = true;
                }
            }
        }

        $correos = array_values(array_unique($correos));
        $documentos = array_values(array_unique($documentos));
        $variantes = array_keys($variantes);

        if ($correos === [] && $documentos === [] && $variantes === []) {
            return $result;
        }

        $users = DB::table('users')->where(function ($q) use ($correos, $documentos, $variantes) {
            if ($correos !== []) {
                $q->orWhereIn('email', $correos);
            }
            if ($documentos !== []) {
                $q->orWhereIn('dni', $documentos);
            }
            if ($variantes !== []) {
                $normalized = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                $normalizedPhone = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                $q->orWhere(function ($q2) use ($normalized, $normalizedPhone, $variantes) {
                    foreach ($variantes as $variante) {
                        $q2->orWhereRaw("{$normalized} LIKE ?", ['%' . $variante . '%'])
                            ->orWhereRaw("{$normalizedPhone} LIKE ?", ['%' . $variante . '%']);
                    }
                });
            }
        })->get();

        foreach ($contacts as $key => $contact) {
            foreach ($users as $user) {
                if (self::userMatchesContact($user, $contact)) {
                    $result[$key] = $user;
                    break;
                }
            }
        }

        return $result;
    }

    private static function userMatchesContact(object $user, array $contact): bool
    {
        if (!empty($contact['correo']) && strcasecmp((string) ($user->email ?? ''), (string) $contact['correo']) === 0) {
            return true;
        }
        if (!empty($contact['documento']) && (string) ($user->dni ?? '') === (string) $contact['documento']) {
            return true;
        }
        if (empty($contact['telefono'])) {
            return false;
        }

        $variantes = self::phoneSearchVariants($contact['telefono']);
        if ($variantes === []) {
            return false;
        }

        $whatsapp = preg_replace('/\D+/', '', (string) ($user->whatsapp ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($user->phone ?? ''));
        foreach ($variantes as $variante) {
            if ($variante !== '' && (str_contains($whatsapp, $variante) || str_contains($phone, $variante))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Variantes de teléfono alineadas con findUserByContact (dígitos y sin prefijo 51).
     *
     * @return array<int, string>
     */
    private static function phoneSearchVariants(?string $telefono): array
    {
        if ($telefono === null || $telefono === '') {
            return [];
        }

        $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
        if ($telefonoLimpio === '') {
            return [];
        }

        $variantes = [$telefonoLimpio];
        $sin51 = preg_replace('/^51/', '', $telefonoLimpio);
        if ($sin51 !== '' && $sin51 !== $telefonoLimpio) {
            $variantes[] = $sin51;
        }

        return $variantes;
    }
}
