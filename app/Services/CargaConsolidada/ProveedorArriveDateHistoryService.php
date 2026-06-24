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
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
                return $text;
            }

            return \Carbon\Carbon::parse($text)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
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
     * @return array<int, array{has_history:bool,latest:?string}>
     */
    public function historyContextByProveedor(array $idProveedores): array
    {
        $idProveedores = array_values(array_unique(array_filter(array_map('intval', $idProveedores))));
        if ($idProveedores === [] || !Schema::hasTable('contenedor_proveedor_arrive_date_history')) {
            return [];
        }

        $counts = DB::table('contenedor_proveedor_arrive_date_history')
            ->whereIn('id_proveedor', $idProveedores)
            ->groupBy('id_proveedor')
            ->selectRaw('id_proveedor, COUNT(*) as total')
            ->pluck('total', 'id_proveedor');

        $latestRows = DB::table('contenedor_proveedor_arrive_date_history')
            ->whereIn('id_proveedor', $idProveedores)
            ->whereNotNull('value')
            ->orderBy('id_proveedor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id_proveedor', 'value']);

        $latestByProveedor = [];
        foreach ($latestRows as $row) {
            $idProveedor = (int) $row->id_proveedor;
            if (!isset($latestByProveedor[$idProveedor])) {
                $latestByProveedor[$idProveedor] = (string) $row->value;
            }
        }

        $context = [];
        foreach ($idProveedores as $idProveedor) {
            $context[$idProveedor] = [
                'has_history' => ((int) ($counts[$idProveedor] ?? 0)) > 0,
                'latest' => $latestByProveedor[$idProveedor] ?? null,
            ];
        }

        return $context;
    }

    /**
     * Prioridad: arrive_date si ambas existen; luego arrive_date; luego arrive_date_china;
     * si no hay fechas vigentes en proveedor pero sí historial, usa la última del tracking.
     *
     * @param mixed $arriveDate
     * @param mixed $arriveChina
     */
    public function resolveFechaRecibir($arriveDate, $arriveChina, ?string $latestHistory, bool $hasHistory): ?string
    {
        $peru = self::normalizeDate($arriveDate);
        $china = self::normalizeDate($arriveChina);

        if ($peru !== null && $china !== null) {
            return $peru;
        }

        if ($peru !== null) {
            return $peru;
        }

        if ($china !== null) {
            return $china;
        }

        if ($hasHistory && $latestHistory !== null) {
            return self::normalizeDate($latestHistory);
        }

        return null;
    }
}
