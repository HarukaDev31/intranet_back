<?php

namespace App\Models\CargaConsolidada\Concerns;

use App\Models\CargaConsolidada\Scopes\OrganizacionScope;

/**
 * organizacion_id nunca se setea a mano: se copia del padre (la relacion que
 * cada modelo declara en organizacionRelacion()) al crear el registro, y se
 * filtra con OrganizacionScope como columna propia -- sin joins ni whereHas.
 *
 * Evita el problema de whereHas()/addGlobalScope en cascada: eso NO protege
 * queries hechas con DB::table() (query builder puro), que en este proyecto
 * son mayoria en varios controllers. Con la columna propia, esas queries
 * pueden (y deben) agregar ->where('organizacion_id', ...) explicitamente.
 */
trait SincronizaOrganizacionId
{
    protected static function bootSincronizaOrganizacionId()
    {
        static::creating(function ($model) {
            if ($model->organizacion_id !== null) {
                return;
            }

            $relacion = static::organizacionRelacion();
            $padre = $model->{$relacion};

            if ($padre !== null) {
                $model->organizacion_id = $padre->organizacion_id;
            }
        });

        static::addGlobalScope(new OrganizacionScope());
    }

    /**
     * Nombre de la relacion (belongsTo) desde la que se copia organizacion_id.
     * El modelo destino de esa relacion debe tener su propia organizacion_id
     * ya resuelta (columna propia o, en la raiz, Contenedor).
     */
    abstract protected static function organizacionRelacion(): string;
}
