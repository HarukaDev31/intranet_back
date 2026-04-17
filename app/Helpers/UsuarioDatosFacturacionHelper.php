<?php

namespace App\Helpers;

use App\Models\UsuarioDatosFacturacion;

class UsuarioDatosFacturacionHelper
{
    /**
     * Alineado con Entrega / front: 1 = Lima, 0 = Provincia.
     *
     * @param string|null $destino Valor enum Lima|Provincia desde usuario_datos_facturacion.destino
     * @return int|null
     */
    public static function destinoToTypeForm($destino)
    {
        if ($destino === null || $destino === '') {
            return null;
        }
        $d = trim((string) $destino);
        if (strcasecmp($d, 'Lima') === 0) {
            return 1;
        }
        if (strcasecmp($d, 'Provincia') === 0) {
            return 0;
        }

        return null;
    }

    /**
     * Última fila del usuario con destino Lima o Provincia (mayor id) → type_form.
     *
     * @param int $idUser
     * @return int|null
     */
    public static function getLatestTypeFormForUserId($idUser)
    {
        $idUser = (int) $idUser;
        if ($idUser <= 0) {
            return null;
        }

        $row = UsuarioDatosFacturacion::query()
            ->where('id_user', $idUser)
            ->whereIn('destino', ['Lima', 'Provincia'])
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return null;
        }

        return self::destinoToTypeForm($row->destino);
    }

    /**
     * Igual que getLatestTypeFormForUserId pero recibe la fila de users ya resuelta (no uses id_usuario de la cotización).
     *
     * @param object|null $user Registro de users (p. ej. DB::table('users')->first() o UserLookupHelper)
     * @return int|null
     */
    public static function getLatestTypeFormForUser($user)
    {
        if ($user === null || empty($user->id)) {
            return null;
        }

        return self::getLatestTypeFormForUserId((int) $user->id);
    }
}
