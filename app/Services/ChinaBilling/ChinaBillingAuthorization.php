<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Services\Organizacion\OrganizacionClienteService;
use Illuminate\Support\Facades\DB;

class ChinaBillingAuthorization
{
    /**
     * Crear y modificar operaciones y cobros: solo Encargado China, sobre todas las organizaciones.
     *
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    public function requireWarehouse(Usuario $user): array
    {
        // Gestionar (crear, editar, confirmar) sigue siendo solo del Encargado China.
        if (!$this->isEncargadoChina($user)) {
            throw new ChinaBillingException('No tienes permiso para gestionar operaciones de China.', 403);
        }

        // El encargado ve y opera las operaciones de todas las organizaciones, no las de organizacionesPermitidas().
        return $this->actor($user, $this->organizationIdsForOperations($user, $this->activeOrganizationIds()));
    }

    /**
     * Ver operaciones y cobros. El Encargado China ve las de cualquier organización.
     * El resto solo ve la organización del usuario, aunque su rol vea contenedores de otras.
     *
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    public function requireOperationView(Usuario $user): array
    {
        $all = $this->isEncargadoChina($user) ? $this->activeOrganizationIds() : [];

        return $this->actor($user, $this->organizationIdsForOperations($user, $all));
    }

    /**
     * @param  array<int, int>  $allActiveIds
     * @return array<int, int>
     */
    public function organizationIdsForOperations(Usuario $user, array $allActiveIds): array
    {
        if ($this->isEncargadoChina($user)) {
            return array_values(array_unique(array_map('intval', $allActiveIds)));
        }

        $own = (int) $user->getAttribute('ID_Organizacion');
        if ($own <= 0) {
            throw new ChinaBillingException('No tienes permiso para ver operaciones.', 403);
        }

        return [$own];
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

    private function isEncargadoChina(Usuario $user): bool
    {
        return Usuario::normalizarRol($this->groupName($user)) === Usuario::normalizarRol(Usuario::ROL_ENCARGADO_CHINA);
    }

    /**
     * @return array<int, int>
     */
    private function activeOrganizationIds(): array
    {
        return Organizacion::query()
            ->where('Nu_Estado', 1)
            ->pluck('ID_Organizacion')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();
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
