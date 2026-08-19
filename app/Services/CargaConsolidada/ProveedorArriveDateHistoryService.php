<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProveedorArriveDateHistoryService
{
    public const FIELD_ARRIVE_DATE = 'arrive_date';
    public const FIELD_ARRIVE_DATE_CHINA = 'arrive_date_china';

    /**
     * @param mixed $value
     */
    public static function normalizeDate($value): ?string
    {
        return SeguimientoConsolidadoDateFormatter::calendarDayYmd($value)
            ?: SeguimientoConsolidadoDateFormatter::parseCellToYmd($value);
    }

    public function record(int $idProveedor, ?int $idContenedor, string $field, $value, string $source = 'system'): void
    {
        if ($idProveedor <= 0 || !Schema::hasTable('contenedor_proveedor_arrive_date_history')) {
            return;
        }

        DB::table('contenedor_proveedor_arrive_date_history')->insert([
            'id_proveedor' => $idProveedor,
            'id_contenedor' => $idContenedor,
            'field' => $field,
            'value' => self::normalizeDate($value),
            'source' => $source,
            'created_at' => now(),
        ]);
    }

    public function recordFromProveedorChanges(CotizacionProveedor $proveedor, string $source = 'proveedor_update'): void
    {
        if ($proveedor->wasChanged(self::FIELD_ARRIVE_DATE)) {
            $this->record(
                (int) $proveedor->id,
                $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                self::FIELD_ARRIVE_DATE,
                $proveedor->arrive_date,
                $source
            );
        }

        if ($proveedor->wasChanged(self::FIELD_ARRIVE_DATE_CHINA)) {
            $this->record(
                (int) $proveedor->id,
                $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                self::FIELD_ARRIVE_DATE_CHINA,
                $proveedor->arrive_date_china,
                $source
            );
        }
    }

    public function recordInitialDates(CotizacionProveedor $proveedor, string $source = 'proveedor_create'): void
    {
        $arriveDate = self::normalizeDate($proveedor->arrive_date);
        if ($arriveDate !== null) {
            $this->record(
                (int) $proveedor->id,
                $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                self::FIELD_ARRIVE_DATE,
                $arriveDate,
                $source
            );
        }

        $arriveDateChina = self::normalizeDate($proveedor->arrive_date_china);
        if ($arriveDateChina !== null) {
            $this->record(
                (int) $proveedor->id,
                $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                self::FIELD_ARRIVE_DATE_CHINA,
                $arriveDateChina,
                $source
            );
        }
    }

    /**
     * @param array<int> $idProveedores
     * @return array<int, array{
     *   has_history:bool,
     *   has_history_for_contenedor:bool,
     *   latest:?string,
     *   latest_by_field:array<string, array{value:?string,id_contenedor:?int,created_at?:string}>
     * }>
     */
    public function historyContextByProveedor(array $idProveedores, ?int $idContenedor = null): array
    {
        $idProveedores = array_values(array_unique(array_filter(array_map('intval', $idProveedores))));
        if ($idProveedores === [] || !Schema::hasTable('contenedor_proveedor_arrive_date_history')) {
            return [];
        }

        $idContenedorFilter = $idContenedor !== null && $idContenedor > 0 ? (int) $idContenedor : null;

        $counts = DB::table('contenedor_proveedor_arrive_date_history')
            ->whereIn('id_proveedor', $idProveedores)
            ->groupBy('id_proveedor')
            ->selectRaw('id_proveedor, COUNT(*) as total')
            ->pluck('total', 'id_proveedor');

        $latestRows = DB::table('contenedor_proveedor_arrive_date_history')
            ->whereIn('id_proveedor', $idProveedores)
            ->orderBy('id_proveedor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id_proveedor', 'id_contenedor', 'field', 'value', 'created_at']);

        $latestByProveedor = [];
        $latestByField = [];
        $hasHistoryForContenedor = [];
        foreach ($latestRows as $row) {
            $idProveedor = (int) $row->id_proveedor;
            $field = (string) $row->field;
            $rowContenedor = $row->id_contenedor !== null ? (int) $row->id_contenedor : null;
            $value = self::normalizeDate($row->value);

            if ($idContenedorFilter !== null && $rowContenedor === $idContenedorFilter) {
                $hasHistoryForContenedor[$idProveedor] = true;
            }

            if (!isset($latestByField[$idProveedor][$field])) {
                $latestByField[$idProveedor][$field] = [
                    'value' => $value,
                    'id_contenedor' => $rowContenedor,
                    'created_at' => (string) ($row->created_at ?? ''),
                ];
            }

            if ($value === null) {
                continue;
            }

            if ($idContenedorFilter !== null) {
                if ($rowContenedor !== $idContenedorFilter) {
                    continue;
                }
            }

            if (!isset($latestByProveedor[$idProveedor])) {
                $latestByProveedor[$idProveedor] = $value;
            }
        }

        $context = [];
        foreach ($idProveedores as $idProveedor) {
            $context[$idProveedor] = [
                'has_history' => ((int) ($counts[$idProveedor] ?? 0)) > 0,
                'has_history_for_contenedor' => !empty($hasHistoryForContenedor[$idProveedor]),
                'latest' => $latestByProveedor[$idProveedor] ?? null,
                'latest_by_field' => $latestByField[$idProveedor] ?? [],
            ];
        }

        return $context;
    }

    /**
     * Prioridad: arrive_date si ambas existen; luego arrive_date; luego arrive_date_china.
     * Solo se ignora una fecha actual si es el mismo valor que el último historial
     * de otro contenedor (roleo). Si el usuario ya la cambió (p. ej. 17 vs 11), se usa.
     *
     * @param mixed $arriveDate
     * @param mixed $arriveChina
     * @param array{has_history?:bool,latest?:?string,latest_by_field?:array} $context
     */
    public function resolveFechaRecibir($arriveDate, $arriveChina, array $context, ?int $idContenedor = null): ?string
    {
        $peru = $this->currentDateIfForContenedor(
            $arriveDate,
            self::FIELD_ARRIVE_DATE,
            $context,
            $idContenedor
        );
        $china = $this->currentDateIfForContenedor(
            $arriveChina,
            self::FIELD_ARRIVE_DATE_CHINA,
            $context,
            $idContenedor
        );

        if ($peru !== null && $china !== null) {
            $peruAt = strtotime((string) (($context['latest_by_field'][self::FIELD_ARRIVE_DATE]['created_at'] ?? ''))) ?: 0;
            $chinaAt = strtotime((string) (($context['latest_by_field'][self::FIELD_ARRIVE_DATE_CHINA]['created_at'] ?? ''))) ?: 0;
            if ($chinaAt > $peruAt) {
                return $china;
            }

            return $peru;
        }

        if ($peru !== null) {
            return $peru;
        }

        if ($china !== null) {
            return $china;
        }

        $latestInContenedor = $context['latest'] ?? null;
        if (!empty($context['has_history_for_contenedor']) && $latestInContenedor !== null) {
            return self::normalizeDate($latestInContenedor);
        }

        if (empty($context['has_history_for_contenedor'])) {
            return $this->fallbackFechaDesdeProveedor($arriveDate, $arriveChina);
        }

        return null;
    }

    /**
     * Fecha en ficha del proveedor (arrive_date / arrive_date_china) sin filtro roleo.
     *
     * @param mixed $arriveDate
     * @param mixed $arriveChina
     */
    public function fallbackFechaDesdeProveedor($arriveDate, $arriveChina): ?string
    {
        $peru = self::normalizeDate($arriveDate);
        $china = self::normalizeDate($arriveChina);

        if ($peru !== null && $china !== null) {
            return $peru;
        }

        if ($peru !== null) {
            return $peru;
        }

        return $china;
    }

    /**
     * @param mixed $value
     * @param array{latest_by_field?:array<string, array{value:?string,id_contenedor:?int}>} $context
     */
    private function currentDateIfForContenedor($value, string $field, array $context, ?int $idContenedor): ?string
    {
        $current = self::normalizeDate($value);
        if ($current === null) {
            return null;
        }

        if ($idContenedor === null || $idContenedor <= 0) {
            return $current;
        }

        // Sin historial de fechas en ESTE consolidado: confiar en la ficha (misma fuente que coordinación).
        if (empty($context['has_history_for_contenedor'])) {
            return $current;
        }

        $latestField = $context['latest_by_field'][$field] ?? null;
        if (!is_array($latestField)) {
            return $current;
        }

        $histContenedor = isset($latestField['id_contenedor']) ? (int) $latestField['id_contenedor'] : 0;
        $histValue = self::normalizeDate($latestField['value'] ?? null);

        if ($histContenedor > 0 && $histContenedor !== $idContenedor && $histValue === $current) {
            return null;
        }

        return $current;
    }
}
