<?php

namespace App\Support\Organizacion;

use App\Models\Organizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Países con los que una org ≠ 1 puede crear consolidados.
 * Org 1 (Probusiness) no tiene restricción.
 */
class OrganizacionPaisesHabilitados
{
    const ID_ORGANIZACION_ADMIN = 1;

    /**
     * IDs permitidos, o null si puede usar cualquiera (org 1 o tabla aún no migrada).
     *
     * @param  mixed  $organizacionId
     * @return int[]|null
     */
    public static function idsPermitidos($organizacionId)
    {
        $orgId = (int) $organizacionId;
        if ($orgId === self::ID_ORGANIZACION_ADMIN) {
            return null;
        }
        if (!Schema::hasTable('organizacion_paises_habilitados')) {
            return null;
        }

        $ids = [];
        $rows = DB::table('organizacion_paises_habilitados')
            ->where('organizacion_id', $orgId)
            ->pluck('id_pais');
        foreach ($rows as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  mixed  $organizacionId
     * @param  mixed  $idPais
     * @return bool
     */
    public static function permite($organizacionId, $idPais)
    {
        $permitidos = self::idsPermitidos($organizacionId);
        if ($permitidos === null) {
            return true;
        }

        return in_array((int) $idPais, $permitidos, true);
    }

    /**
     * @param  mixed  $ids
     * @return int[]
     */
    public static function normalizarIds($ids)
    {
        $clean = [];
        if (!is_array($ids)) {
            return [];
        }
        foreach ($ids as $id) {
            if (is_array($id) && isset($id['value'])) {
                $id = $id['value'];
            }
            $n = (int) $id;
            if ($n > 0) {
                $clean[$n] = $n;
            }
        }

        return array_values($clean);
    }

    /**
     * @param  mixed  $ids
     */
    public static function sync(Organizacion $organizacion, $ids)
    {
        $orgId = (int) $organizacion->getAttribute('ID_Organizacion');
        if ($orgId === self::ID_ORGANIZACION_ADMIN) {
            return;
        }
        if (!Schema::hasTable('organizacion_paises_habilitados')) {
            return;
        }

        $organizacion->paisesHabilitados()->sync(self::normalizarIds($ids));
    }
}
