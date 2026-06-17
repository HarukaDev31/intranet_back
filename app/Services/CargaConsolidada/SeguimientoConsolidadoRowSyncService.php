<?php

namespace App\Services\CargaConsolidada;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste última actualización por fila (YIWU / POR RECIBIR) cuando cambian datos en sync.
 */
class SeguimientoConsolidadoRowSyncService
{
    public const TABLA_YIWU = 'yiwu';
    public const TABLA_RECIBIR = 'recibir';

    /**
     * @param int $idContenedor
     * @param array<string, array<int, array<string, mixed>>> $groups
     */
    public function applyUltimaActualizacion($idContenedor, array &$groups)
    {
        if (!Schema::hasTable('contenedor_seguimiento_row_sync')) {
            return;
        }

        foreach ($groups['yiwu'] as $index => $item) {
            $groups['yiwu'][$index]['ultima_actualizacion'] = $this->touchRow(
                (int) $idContenedor,
                self::TABLA_YIWU,
                (int) ($item['id_cotizacion'] ?? 0),
                null,
                $this->hashYiwu($item)
            );
        }

        foreach ($groups['recibir'] as $index => $item) {
            $groups['recibir'][$index]['ultima_actualizacion'] = $this->touchRow(
                (int) $idContenedor,
                self::TABLA_RECIBIR,
                (int) ($item['id_cotizacion'] ?? 0),
                (int) ($item['id_proveedor'] ?? 0),
                $this->hashRecibir($item)
            );
        }
    }

    /**
     * @param int $idContenedor
     * @param string $tabla
     * @param int $idCotizacion
     * @param int|null $idProveedor
     * @param string $dataHash
     * @return string
     */
    private function touchRow($idContenedor, $tabla, $idCotizacion, $idProveedor, $dataHash)
    {
        if ($idContenedor <= 0 || $dataHash === '') {
            return '';
        }

        if ($tabla === self::TABLA_YIWU && $idCotizacion <= 0) {
            return '';
        }

        if ($tabla === self::TABLA_RECIBIR && $idProveedor <= 0) {
            return '';
        }

        $query = DB::table('contenedor_seguimiento_row_sync')
            ->where('id_contenedor', $idContenedor)
            ->where('tabla', $tabla)
            ->where('id_cotizacion', $idCotizacion);

        if ($idProveedor === null) {
            $query->whereNull('id_proveedor');
        } else {
            $query->where('id_proveedor', $idProveedor);
        }

        $existing = $query->first();
        $now = Carbon::now('America/Lima');

        if ($existing && (string) $existing->data_hash === $dataHash) {
            return $this->formatDateTime($existing->ultima_actualizacion);
        }

        $payload = [
            'data_hash' => $dataHash,
            'ultima_actualizacion' => $now->toDateTimeString(),
            'updated_at' => Carbon::now(),
        ];

        if ($existing) {
            DB::table('contenedor_seguimiento_row_sync')
                ->where('id', $existing->id)
                ->update($payload);
        } else {
            DB::table('contenedor_seguimiento_row_sync')->insert(array_merge($payload, [
                'id_contenedor' => $idContenedor,
                'tabla' => $tabla,
                'id_cotizacion' => $idCotizacion ?: null,
                'id_proveedor' => $idProveedor,
                'created_at' => Carbon::now(),
            ]));
        }

        return $this->formatDateTime($now);
    }

    /**
     * @param array<string, mixed> $item
     * @return string
     */
    private function hashYiwu(array $item)
    {
        return hash('sha256', json_encode([
            'cons' => $item['cons'] ?? '',
            'vendedor' => $item['vendedor'] ?? '',
            'cliente' => $item['cliente'] ?? '',
            'cbm' => $item['cbm_yiwu'] ?? '',
            'tipo' => $item['tipo_carga'] ?? '',
            'pago' => $item['estado_pago'] ?? '',
        ]));
    }

    /**
     * @param array<string, mixed> $item
     * @return string
     */
    private function hashRecibir(array $item)
    {
        return hash('sha256', json_encode([
            'cons' => $item['cons'] ?? '',
            'vendedor' => $item['vendedor'] ?? '',
            'cliente' => $item['cliente'] ?? '',
            'cbm' => $item['cbm_recibir'] ?? '',
            'fecha' => $item['fecha_recibir'] ?? '',
            'proveedor' => $item['code_supplier'] ?? '',
        ]));
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function formatDateTime($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            return SeguimientoConsolidadoDateFormatter::formatLimaLocalTimestamp($value);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
