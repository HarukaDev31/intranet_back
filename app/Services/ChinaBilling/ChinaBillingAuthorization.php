<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\Usuario;

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

        $organizationIds = array_values(array_filter(
            $user->organizacionesPermitidas(),
            fn (int $id) => $id !== Usuario::ID_ORGANIZACION_ADMIN
        ));

        return $this->actor($user, $organizationIds);
    }

    /**
     * El socio solo ve y paga lo de su propia organización.
     *
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    public function requirePartner(Usuario $user): array
    {
        $organizationId = (int) $user->getAttribute('ID_Organizacion');
        if ($this->groupName($user) !== Usuario::ROL_SOCIO || $organizationId <= 0) {
            throw new ChinaBillingException('No tienes permiso para ver los pagos del socio.', 403);
        }

        return $this->actor($user, [$organizationId]);
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
