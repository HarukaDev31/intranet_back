<?php

namespace App\Services\Calendar;

use App\Models\Usuario;

class CalendarPermissionService
{
    public function isJefeImportaciones(Usuario $user): bool
    {
        return $user->getNombreGrupo() === Usuario::ROL_JEFE_IMPORTACION;
    }

    public function isCoordinacionOrDocumentacion(Usuario $user): bool
    {
        $rol = $user->getNombreGrupo();
        return $rol === Usuario::ROL_COORDINACION || $rol === Usuario::ROL_DOCUMENTACION || $rol === Usuario::ROL_JEFE_IMPORTACION;
    }

    public function canChangeAnyChargeStatus(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user);
    }

    public function canChangeOwnChargeStatus(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user) || $this->isCoordinacionOrDocumentacion($user);
    }

    public function canManageActivities(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user);
    }

    public function canManageResponsables(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user);
    }

    public function canManageColors(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user);
    }

    public function canViewTeamProgress(Usuario $user): bool
    {
        return $this->isJefeImportaciones($user);
    }
}
