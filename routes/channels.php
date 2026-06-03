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

// Canal privado para usuarios (nombre genérico Laravel)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->ID_Usuario === (int) $id;
});

// Canal privado por usuario (modelo Usuario, ID_Usuario) - usado por calendario
Broadcast::channel('App.Models.Usuario.{id}', function ($user, $id) {
    return (int) $user->ID_Usuario === (int) $id;
});

// Canal privado para ContenedorConsolidado - Solo roles específicos
Broadcast::channel('ContenedorConsolidado-notifications', function ($user) {
    $allowedRoles = [
        Usuario::ROL_ALMACEN_CHINA,
        Usuario::ROL_COORDINACION,
        Usuario::ROL_ADMINISTRACION,
        Usuario::ROL_COTIZADOR,
        Usuario::ROL_DOCUMENTACION,
        Usuario::ROL_JEFE_IMPORTACION
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

// WhatsApp Inbox coordinación — tiempo real del chat Meta
Broadcast::channel('whatsapp-inbox.coordinacion', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_COORDINACION;
});
Broadcast::channel('Administracion-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_ADMINISTRACION;
});
Broadcast::channel('Cotizador-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_COTIZADOR;
}); 
Broadcast::channel('JefeImportacion-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_JEFE_IMPORTACION;
});
Broadcast::channel('Contabilidad-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_CONTABILIDAD;
});
Broadcast::channel('Soporte-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_SOPORTE;
});
Broadcast::channel('PM-notifications', function ($user) {
    return $user->grupo && $user->grupo->No_Grupo === Usuario::ROL_PM;
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

// Chat Soporte TI — private-soporte-ti.chat.{chatUuid}
Broadcast::channel('soporte-ti.chat.{chatUuid}', function ($user, $chatUuid) {
    if (!$user) {
        return false;
    }

    $sala = \App\Models\SoporteTi\SoporteTiChatSala::where('chat_uuid', $chatUuid)->first();
    if (!$sala) {
        return false;
    }

    $solicitud = $sala->solicitud;
    if (!$solicitud) {
        return false;
    }

    $user->loadMissing('grupo');
    $grupo = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
    $esStaff = $grupo === strtolower(\App\Models\Usuario::ROL_PM)
        || $grupo === strtolower(\App\Models\Usuario::ROL_SOPORTE);

    if ($esStaff) {
        return true;
    }

    $uid = (int) $user->ID_Usuario;
    return $solicitud->solicitante_user_id !== null
        && (int) $solicitud->solicitante_user_id === $uid;
});