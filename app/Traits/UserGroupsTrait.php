<?php

namespace App\Traits;

use App\Models\Usuario;
use App\Models\GrupoUsuario;
use App\Models\Grupo;

trait UserGroupsTrait
{
    /**
     * Obtiene los usuarios por grupo
     *
     * @param string $grupoNombre
     * @return array
     */
    protected function getUsersByGrupo($grupoNombre)
    {
        $grupo = Grupo::where('No_Grupo', $grupoNombre)->first();
        
        if (!$grupo) {
            return [];
        }

        return Usuario::join('grupo_usuario', 'usuario.ID_Usuario', '=', 'grupo_usuario.ID_Usuario')
            ->where('grupo_usuario.ID_Grupo', $grupo->ID_Grupo)
            ->select('usuario.*')
            ->get()
            ->toArray();
    }

    /**
     * Verifica si un usuario pertenece a un grupo
     *
     * @param int $userId
     * @param string $grupoNombre
     * @return bool
     */
    protected function userBelongsToGroup($userId, $grupoNombre)
    {
        $grupo = Grupo::where('No_Grupo', $grupoNombre)->first();
        
        if (!$grupo) {
            return false;
        }

        return GrupoUsuario::where('ID_Usuario', $userId)
            ->where('ID_Grupo', $grupo->ID_Grupo)
            ->exists();
    }
}
