<?php

namespace App\Services\CalculadoraImportacion;

use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraTarifasConsolidado;
use App\Models\CalculadoraTipoCliente;
use Carbon\Carbon;

class CalculadoraTarifaService
{
    private const CBM_EPSILON = 0.0001;

    public function totalCbmFromProveedoresPayload(array $proveedores): float
    {
        $total = 0.0;
        foreach ($proveedores as $proveedor) {
            $total += $this->maxCbmFromProveedorPayload($proveedor);
        }

        return round($total, 4);
    }

    public function totalCbmFromCalculadora(CalculadoraImportacion $calculadora): float
    {
        $calculadora->loadMissing('proveedores');

        return round((float) $calculadora->proveedores->sum(function ($proveedor) {
            $cbm = (float) ($proveedor->cbm ?? 0);
            $peso = (float) ($proveedor->peso ?? 0);

            return (float) ($proveedor->maxcbm ?? max($cbm, $peso / 1000));
        }), 4);
    }

    public function cbmChanged(float $before, float $after): bool
    {
        return abs($before - $after) > self::CBM_EPSILON;
    }

    /**
     * @return array{calculadora_tarifa_consolidado_id: int|null, tarifa: float, tarifa_type: string}
     */
    public function resolveSnapshotForCreate(array $data): array
    {
        $cbm = $this->totalCbmFromProveedoresPayload($data['proveedores'] ?? []);
        $tipo = trim((string) ($data['clienteInfo']['tipoCliente'] ?? 'NUEVO'));

        $tarifaInput = is_array($data['tarifa'] ?? null) ? $data['tarifa'] : [];
        if (! empty($tarifaInput['id'])) {
            $row = CalculadoraTarifasConsolidado::find((int) $tarifaInput['id']);
            if ($row) {
                return $this->snapshotFromRow($row);
            }
        }

        $row = $this->findVigenteByTipoYCbm($tipo, $cbm);
        if ($row) {
            return $this->snapshotFromRow($row);
        }

        return [
            'calculadora_tarifa_consolidado_id' => null,
            'tarifa' => (float) ($tarifaInput['tarifa'] ?? $tarifaInput['value'] ?? 0),
            'tarifa_type' => strtoupper(trim((string) ($tarifaInput['type'] ?? 'PLAIN'))) ?: 'PLAIN',
        ];
    }

    /**
     * Con cotización vinculada: congela tarifa salvo cambio de CBM → nueva tarifa vigente.
     *
     * @return array{calculadora_tarifa_consolidado_id: int|null, tarifa: float, tarifa_type: string}
     */
    public function resolveSnapshotForUpdate(
        CalculadoraImportacion $calculadora,
        array $data,
        float $oldCbm,
        float $newCbm
    ): array {
        $tipo = trim((string) ($data['clienteInfo']['tipoCliente'] ?? $calculadora->tipo_cliente ?? 'NUEVO'));
        $hasCotizacion = ! empty($calculadora->id_cotizacion);
        $cbmChanged = $this->cbmChanged($oldCbm, $newCbm);

        if ($hasCotizacion && ! $cbmChanged) {
            return $this->snapshotFromCalculadora($calculadora);
        }

        if ($cbmChanged || ! $calculadora->calculadora_tarifa_consolidado_id) {
            $row = $this->findVigenteByTipoYCbm($tipo, $newCbm);
            if ($row) {
                return $this->snapshotFromRow($row);
            }
        }

        if ($calculadora->calculadora_tarifa_consolidado_id) {
            return $this->snapshotFromCalculadora($calculadora);
        }

        $tarifaInput = is_array($data['tarifa'] ?? null) ? $data['tarifa'] : [];

        return [
            'calculadora_tarifa_consolidado_id' => null,
            'tarifa' => (float) ($tarifaInput['tarifa'] ?? $calculadora->tarifa ?? 0),
            'tarifa_type' => strtoupper(trim((string) ($tarifaInput['type'] ?? $calculadora->tarifa_type ?? 'PLAIN'))) ?: 'PLAIN',
        ];
    }

    /**
     * Snapshot congelado en calculadora (sin consultar tabla maestra).
     *
     * @return array{tarifa: float, type: string, label: string}
     */
    public function snapshotForExcel(CalculadoraImportacion $calculadora): array
    {
        $type = strtoupper(trim((string) ($calculadora->tarifa_type ?? '')));
        if ($type === '' && $calculadora->calculadora_tarifa_consolidado_id) {
            $row = CalculadoraTarifasConsolidado::find($calculadora->calculadora_tarifa_consolidado_id);
            $type = strtoupper(trim((string) ($row->type ?? '')));
        }

        return [
            'tarifa' => (float) ($calculadora->tarifa ?? 0),
            'type' => $type !== '' ? $type : 'PLAIN',
            'label' => (string) ($calculadora->tipo_cliente ?? 'NUEVO'),
        ];
    }

    public function findVigenteByTipoYCbm(string $tipoCliente, float $cbmTotal): ?CalculadoraTarifasConsolidado
    {
        $tipo = CalculadoraTipoCliente::where('nombre', trim($tipoCliente))->first();
        if (! $tipo) {
            $tipo = CalculadoraTipoCliente::where('nombre', 'NUEVO')->first();
        }
        if (! $tipo) {
            return null;
        }

        $cbmCents = $this->cbmToCents($cbmTotal);

        $tarifa = CalculadoraTarifasConsolidado::vigente()
            ->where('calculadora_tipo_cliente_id', $tipo->id)
            ->whereRaw('ROUND(limit_inf * 100) <= ?', [$cbmCents])
            ->whereRaw('ROUND(limit_sup * 100) >= ?', [$cbmCents])
            ->first();

        if ($tarifa) {
            return $tarifa;
        }

        return CalculadoraTarifasConsolidado::vigente()
            ->where('calculadora_tipo_cliente_id', $tipo->id)
            ->orderByDesc('limit_sup')
            ->first();
    }

    public function versionTarifa(CalculadoraTarifasConsolidado $current, float $value, string $type): CalculadoraTarifasConsolidado
    {
        $now = Carbon::now();
        $current->vigente_hasta = $now;
        $current->save();

        return CalculadoraTarifasConsolidado::create([
            'limit_inf' => $current->limit_inf,
            'limit_sup' => $current->limit_sup,
            'value' => $value,
            'type' => $type,
            'calculadora_tipo_cliente_id' => $current->calculadora_tipo_cliente_id,
            'vigente_hasta' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(CalculadoraTarifasConsolidado $tarifa): array
    {
        $tarifa->loadMissing('tipoCliente');

        return [
            'id' => $tarifa->id,
            'limit_inf' => $tarifa->limit_inf,
            'limit_sup' => $tarifa->limit_sup,
            'type' => $tarifa->type,
            'tarifa' => $tarifa->value,
            'label' => $tarifa->tipoCliente?->nombre,
            'id_tipo_cliente' => $tarifa->tipoCliente?->id,
            'value' => $tarifa->tipoCliente?->nombre,
            'vigente_desde' => $tarifa->created_at?->toIso8601String(),
            'vigente_hasta' => $tarifa->vigente_hasta?->toIso8601String(),
            'created_at' => $tarifa->created_at?->toIso8601String(),
            'updated_at' => $tarifa->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{calculadora_tarifa_consolidado_id: int|null, tarifa: float, tarifa_type: string}
     */
    private function snapshotFromRow(CalculadoraTarifasConsolidado $row): array
    {
        return [
            'calculadora_tarifa_consolidado_id' => (int) $row->id,
            'tarifa' => (float) $row->value,
            'tarifa_type' => strtoupper(trim((string) $row->type)) ?: 'PLAIN',
        ];
    }

    /**
     * @return array{calculadora_tarifa_consolidado_id: int|null, tarifa: float, tarifa_type: string}
     */
    private function snapshotFromCalculadora(CalculadoraImportacion $calculadora): array
    {
        $type = strtoupper(trim((string) ($calculadora->tarifa_type ?? '')));
        if ($type === '' && $calculadora->calculadora_tarifa_consolidado_id) {
            $row = CalculadoraTarifasConsolidado::find($calculadora->calculadora_tarifa_consolidado_id);
            $type = strtoupper(trim((string) ($row->type ?? '')));
        }

        return [
            'calculadora_tarifa_consolidado_id' => $calculadora->calculadora_tarifa_consolidado_id
                ? (int) $calculadora->calculadora_tarifa_consolidado_id
                : null,
            'tarifa' => (float) ($calculadora->tarifa ?? 0),
            'tarifa_type' => $type !== '' ? $type : 'PLAIN',
        ];
    }

    private function maxCbmFromProveedorPayload(array $proveedor): float
    {
        $cbm = (float) ($proveedor['cbm'] ?? 0);
        $peso = (float) ($proveedor['peso'] ?? 0);
        if (isset($proveedor['maxcbm']) && is_numeric($proveedor['maxcbm'])) {
            return (float) $proveedor['maxcbm'];
        }

        return max($cbm, $peso / 1000);
    }

    private function cbmToCents(float $cbm): int
    {
        return (int) round($cbm * 100);
    }
}
