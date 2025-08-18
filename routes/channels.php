<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Usuario;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Canal privado para usuarios
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->ID_Usuario === (int) $id;
});

// Canal privado para ContenedorConsolidado - Solo roles específicos
Broadcast::channel('ContenedorConsolidado-notifications', function ($user) {
    $allowedRoles = [
        Usuario::ROL_ALMACEN_CHINA,
        Usuario::ROL_COORDINACION,
        Usuario::ROL_ADMINISTRACION,
        Usuario::ROL_COTIZADOR,
        Usuario::ROL_DOCUMENTACION
    ];
    
    return $user->grupo && in_array($user->grupo->No_Grupo, $allowedRoles);
});

// Canal privado para Documentación - Solo rol de documentación
Broadcast::channel('Documentacion-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_DOCUMENTACION;
});
Broadcast::channel('Coordinacion-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_COORDINACION;
});
Broadcast::channel('Administracion-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_ADMINISTRACION;
});
Broadcast::channel('Cotizador-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_COTIZADOR;
});

// Canal privado para todos los usuarios autenticados
Broadcast::channel('User-notifications', function ($user) {
    // Cualquier usuario autenticado puede acceder
    return true;
});

// Canal privado para cotizaciones específicas
Broadcast::channel('cotizacion.{id}', function ($user, $cotizacion) {
    return $user->ID_Usuario === $cotizacion->id_usuario;
});