<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProveedorEstadosProveedorHistoryService
{
    public const ESTADO_NO_CONTACTADO = 'NC';
    public const ESTADO_CONTACTADO = 'C';
    public const ESTADO_NO_LOADED = 'NO LOADED';

    /**
     * @param mixed $estado
     */
    public static function normalizeEstado($estado): string
    {
        return strtoupper(trim((string) $estado));
    }

    /**
     * Contactado: C u otro estado distinto de NC, NO LOADED y DATOS PROVEEDOR.
     *
     * @param mixed $estado
     */
    public function isEstadoContactado($estado): bool
    {
        $estado = self::normalizeEstado($estado);

        if ($estado === '' || $estado === self::ESTADO_NO_CONTACTADO) {
            return false;
        }

        if ($estado === self::ESTADO_NO_LOADED || $estado === 'DATOS PROVEEDOR') {
            return false;
        }

        return true;
    }

    public function record(int $idProveedor, ?int $idContenedor, ?string $estado, string $source = 'system'): void
    {
        if ($idProveedor <= 0 || !Schema::hasTable('contenedor_proveedor_estados_proveedor_history')) {
            return;
        }

        DB::table('contenedor_proveedor_estados_proveedor_history')->insert([
            'id_proveedor' => $idProveedor,
            'id_contenedor' => $idContenedor,
            'estado' => self::normalizeEstado($estado) ?: null,
            'source' => $source,
            'created_at' => now(),
        ]);
    }

    public function recordFromProveedorChanges(CotizacionProveedor $proveedor, string $source = 'proveedor_update'): void
    {
        if (!$proveedor->wasChanged('estados_proveedor')) {
            return;
        }

        $this->record(
            (int) $proveedor->id,
            $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
            $proveedor->estados_proveedor,
            $source
        );
    }

    public function recordInitialEstado(CotizacionProveedor $proveedor, string $source = 'proveedor_create'): void
    {
        $estado = self::normalizeEstado($proveedor->estados_proveedor);
        if ($estado === '') {
            return;
        }

        $this->record(
            (int) $proveedor->id,
            $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
            $estado,
            $source
        );
    }

    /**
     * @param array<int> $idProveedores
     * @return array<int, bool>
     */
    public function fueContactadoByProveedor(array $idProveedores): array
    {
        $idProveedores = array_values(array_unique(array_filter(array_map('intval', $idProveedores))));
        if ($idProveedores === [] || !Schema::hasTable('contenedor_proveedor_estados_proveedor_history')) {
            return [];
        }

        $rows = DB::table('contenedor_proveedor_estados_proveedor_history')
            ->whereIn('id_proveedor', $idProveedores)
            ->get(['id_proveedor', 'estado']);

        $result = [];
        foreach ($idProveedores as $idProveedor) {
            $result[$idProveedor] = false;
        }

        foreach ($rows as $row) {
            $idProveedor = (int) $row->id_proveedor;
            if (isset($result[$idProveedor]) && $this->isEstadoContactado($row->estado)) {
                $result[$idProveedor] = true;
            }
        }

        return $result;
    }
}
