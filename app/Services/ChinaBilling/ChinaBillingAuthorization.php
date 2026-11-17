<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\Usuario;
use App\Services\Organizacion\OrganizacionClienteService;
use Illuminate\Support\Facades\DB;

class ChinaBillingAuthorization
{
    /**
     * Operaciones y cobros los gestiona Almacén China (ContenedorAlmacen).
     *
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    public function requireWarehouse(Usuario $user): array
    {
        if ($this->groupName($user) !== Usuario::ROL_ALMACEN_CHINA) {
            throw new ChinaBillingException('No tienes permiso para gestionar operaciones de China.', 403);
        }

        // PRY-26-0003 (#1963): Pro Business (organización 1) también es cliente de Almacén China, así que se
        // puede elegir al registrar operaciones y cobros. Sus consolidados no generan borradores (ChinaDraftService).
        $organizationIds = array_values(array_unique(array_map('intval', $user->organizacionesPermitidas())));

        return $this->actor($user, $organizationIds);
    }

    /**
     * El socio (o importador) solo ve y paga lo de su propia organización.
     * Entra quien tenga el rol Socio/Importador o el permiso del menú «Pagos» en BD (sin restricción de rol en el front).
     *
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    public function requirePartner(Usuario $user): array
    {
        $organizationId = (int) $user->getAttribute('ID_Organizacion');
        $role = Usuario::normalizarRol($this->groupName($user));
        $allowed = in_array($role, [Usuario::normalizarRol(Usuario::ROL_SOCIO), Usuario::normalizarRol(OrganizacionClienteService::GRUPO_IMPORTADOR)], true)
            || $this->hasPaymentsMenu($user);
        if (!$allowed || $organizationId <= 0) {
            throw new ChinaBillingException('No tienes permiso para ver los pagos del socio.', 403);
        }

        return $this->actor($user, [$organizationId]);
    }

    /** Permiso del menú «Pagos» del socio en menu_acceso (por cualquiera de sus roles). */
    private function hasPaymentsMenu(Usuario $user): bool
    {
        $userId = $user->getAttribute('ID_Usuario');
        if ($userId === null) {
            return false;
        }

        return DB::table('menu_acceso as ma')
            ->join('grupo_usuario as gu', 'gu.ID_Grupo_Usuario', '=', 'ma.ID_Grupo_Usuario')
            ->join('menu as m', 'm.ID_Menu', '=', 'ma.ID_Menu')
            ->where('gu.ID_Usuario', (int) $userId)
            ->where('m.url_intranet_v2', OrganizacionClienteService::MENU_PAGOS_URL)
            ->exists();
    }

    private function groupName(Usuario $user): string
    {
        $user->loadMissing('grupo');
        $group = $user->getRelationValue('grupo');

        return trim((string) ($group instanceof \Illuminate\Database\Eloquent\Model ? $group->getAttribute('No_Grupo') : ''));
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    private function actor(Usuario $user, array $organizationIds): array
    {
        return [
            'id' => $user->getAttribute('ID_Usuario') !== null ? (int) $user->getAttribute('ID_Usuario') : null,
            'name' => (string) ($user->getAttribute('No_Nombres_Apellidos') ?: $user->getAttribute('No_Usuario')),
            'organization_ids' => $organizationIds,
        ];
    }
}
