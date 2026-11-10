<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Borradores "Por aprobar": consolidados de socios que Almacén China ya cerró y aún no tienen operación.
 * El CBM es el "CBM China" de Carga Consolidada para contenedores cerrados:
 * cotizaciones CONFIRMADO con proveedor LOADED (ver ContenedorController::loadCbmTotalsForContenedores).
 */
class ChinaDraftService
{
    /**
     * @param  array<int, int>  $organizationIds
     * @return Collection<int, array{container_id: int, organization_id: int, issued_on: string, description: string, cbm: float}>
     */
    public function list(array $organizationIds): Collection
    {
        if ($organizationIds === []) {
            return collect();
        }

        $containers = Contenedor::query()
            ->withoutGlobalScopes()
            ->whereIn('organizacion_id', $organizationIds)
            ->where('organizacion_id', '!=', Usuario::ID_ORGANIZACION_ADMIN)
            ->where('estado_china', Contenedor::CONTEDOR_CERRADO)
            ->whereNotIn('id', DB::table('china_operations')->whereNotNull('container_id')->select('container_id'))
            ->whereNotIn('id', DB::table('china_draft_dismissals')->select('container_id'))
            ->get();

        $cbm = $this->cbmByContainer($containers->pluck('id')->all());

        return $containers->map(fn (Contenedor $container) => [
            'container_id' => (int) $container->id,
            'organization_id' => (int) $container->organizacion_id,
            'issued_on' => $container->f_cierre ? $container->f_cierre->toDateString() : now()->toDateString(),
            'description' => (string) $container->formatCargaLabel(),
            'cbm' => round((float) ($cbm[$container->id] ?? 0), 3),
        ])->values();
    }

    /**
     * Un borrador aprobable: el consolidado debe seguir cerrado, ser de un socio y no tener operación.
     *
     * @param  array<int, int>  $organizationIds
     * @return array{container_id: int, organization_id: int, issued_on: string, description: string, cbm: float}
     */
    public function find(int $containerId, array $organizationIds): array
    {
        $draft = $this->list($organizationIds)->firstWhere('container_id', $containerId);
        if (!$draft) {
            throw new ChinaBillingException('Este consolidado ya no tiene una operación por aprobar.', 404);
        }

        return $draft;
    }

    /**
     * @param  array<int, int>  $organizationIds
     */
    public function dismiss(int $containerId, array $organizationIds, ?int $userId): void
    {
        $this->find($containerId, $organizationIds);
        DB::table('china_draft_dismissals')->insertOrIgnore([
            'container_id' => $containerId,
            'dismissed_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $containerIds
     * @return array<int, float>
     */
    private function cbmByContainer(array $containerIds): array
    {
        if ($containerIds === []) {
            return [];
        }

        return DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
            ->join('contenedor_consolidado_cotizacion as cc', 'cc.id', '=', 'cccp.id_cotizacion')
            ->whereIn('cc.id_contenedor', $containerIds)
            ->where('cc.estado_cotizador', 'CONFIRMADO')
            ->where('cccp.estados_proveedor', 'LOADED')
            ->groupBy('cc.id_contenedor')
            ->selectRaw('cc.id_contenedor, COALESCE(SUM(cccp.cbm_total_china), 0) as cbm')
            ->pluck('cbm', 'id_contenedor')
            ->map(fn ($value) => (float) $value)
            ->all();
    }
}
