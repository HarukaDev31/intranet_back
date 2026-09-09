<?php

namespace App\Models\CargaConsolidada\Scopes;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Filtra Carga Consolidada por las organizaciones del usuario staff autenticado.
 *
 * Sin usuario autenticado (comandos artisan, jobs, tests) no se aplica filtro:
 * ese código corre con confianza total, igual que el resto de queries internas.
 * Con usuario autenticado, se restringe SIEMPRE a organizacionesPermitidas() —
 * un usuario sin ninguna organización asignada no ve ningún contenedor.
 */
class OrganizacionScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $usuario = Auth::guard('api')->user();

        if (!($usuario instanceof Usuario)) {
            return;
        }

        $builder->whereIn($model->getTable() . '.organizacion_id', $usuario->organizacionesPermitidas());
    }
}
