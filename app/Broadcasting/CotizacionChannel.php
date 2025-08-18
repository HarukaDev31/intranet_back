<?php

namespace App\Broadcasting;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\Usuario;

class CotizacionChannel
{
    /**
     * Authenticate the user's access to the channel.
     *
     * @param  Usuario  $user
     * @param  Cotizacion  $cotizacion
     * @return array|bool
     */
    public function join(Usuario $user, Cotizacion $cotizacion)
    {
        // Verificar si el usuario tiene acceso a esta cotización
        return $user->ID_Usuario === $cotizacion->id_usuario;
    }
}
