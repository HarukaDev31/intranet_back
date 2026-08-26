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
     * Con cotización: congela tarifa si CBM no cambia.
     * Si CBM cambia: usa el rango del nuevo CBM en la misma generación
     * (vigente en el momento de la tarifa anterior), no las tarifas nuevas.
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

        if ($cbmChanged) {
            $row = $hasCotizacion
                ? $this->resolveTarifaForCbmChange($calculadora, $tipo, $newCbm)
                : $this->findVigenteByTipoYCbm($tipo, $newCbm);
            if ($row) {
                return $this->snapshotFromRow($row);
            }
        }

        if (! $calculadora->calculadora_tarifa_consolidado_id) {
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
        return $this->findByTipoYCbmAt($tipoCliente, $cbmTotal, Carbon::now());
    }

    /**
     * Al cambiar CBM: buscar el rango del nuevo CBM en la misma generación que la tarifa
     * congelada (vigente en el instante de esa versión), no las tarifas vigentes actuales.
     */
    public function resolveTarifaForCbmChange(
        CalculadoraImportacion $calculadora,
        string $tipoCliente,
        float $newCbm
    ): ?CalculadoraTarifasConsolidado {
        $at = $this->referenceInstantForCalculadora($calculadora);
        if ($at) {
            $row = $this->findByTipoYCbmAt($tipoCliente, $newCbm, $at);
            if ($row) {
                return $row;
            }
        }

        return $this->findVigenteByTipoYCbm($tipoCliente, $newCbm);
    }

    /**
     * Instantánea de referencia para congelar generación de tarifas (id snapshot o created_at).
     */
    public function referenceInstantForCalculadora(CalculadoraImportacion $calculadora): ?Carbon
    {
        if ($calculadora->calculadora_tarifa_consolidado_id) {
            $anterior = CalculadoraTarifasConsolidado::find($calculadora->calculadora_tarifa_consolidado_id);
            if ($anterior?->created_at) {
                return Carbon::parse($anterior->created_at);
            }
        }

        if ($calculadora->created_at) {
            return Carbon::parse($calculadora->created_at);
        }

        return null;
    }

    /**
     * Backfill: resuelve fila de tarifa por tipo + CBM + monto guardado, con fallbacks.
     */
    public function findTarifaAtCalculadoraCreation(CalculadoraImportacion $calculadora): ?CalculadoraTarifasConsolidado
    {
        $tipo = trim((string) ($calculadora->tipo_cliente ?? 'NUEVO'));
        if (strtoupper($tipo) === 'MANUAL') {
            return null;
        }

        $cbm = $this->totalCbmFromCalculadora($calculadora);
        $storedTarifa = (float) ($calculadora->tarifa ?? 0);
        $at = $calculadora->created_at ? Carbon::parse($calculadora->created_at) : null;

        // 1) Mejor señal legacy: tipo + rango CBM + monto guardado (p. ej. 300)
        if ($storedTarifa > 0) {
            $byValue = $this->findByTipoCbmAndValue($tipo, $cbm, $storedTarifa, $at);
            if ($byValue) {
                return $byValue;
            }

            // 1b) Solo tipo + monto (CBM vacío o rango distinto al guardado)
            $byValueOnly = $this->findByTipoAndValueOnly($tipo, $storedTarifa, $at);
            if ($byValueOnly) {
                return $byValueOnly;
            }
        }

        // 2) Vigente en la fecha de creación de la calculadora
        if ($at) {
            $row = $this->findByTipoYCbmAt($tipo, $cbm, $at);
            if ($row) {
                return $row;
            }
        }

        // 3) Sin filtro temporal (tarifas seed con created_at posterior al registro)
        $inRange = $this->findByTipoCbmInRange($tipo, $cbm);
        if ($inRange) {
            return $inRange;
        }

        // 4) Último recurso: vigente hoy
        return $this->findVigenteByTipoYCbm($tipo, $cbm);
    }

    /**
     * Tarifa por tipo + CBM + valor (prioriza vigencia histórica si se pasa $at).
     */
    public function findByTipoCbmAndValue(
        string $tipoCliente,
        float $cbmTotal,
        float $value,
        ?Carbon $at = null
    ): ?CalculadoraTarifasConsolidado {
        $tipo = $this->resolveTipoCliente($tipoCliente, true);
        if (! $tipo) {
            return null;
        }

        $base = $this->baseTarifaQueryForTipo($tipo->id, $cbmTotal)
            ->whereRaw('ABS(value - ?) < 0.01', [$value]);

        if ($at) {
            $historical = (clone $base)
                ->where(function ($q) use ($at) {
                    $q->whereNull('created_at')->orWhere('created_at', '<=', $at);
                })
                ->where(function ($q) use ($at) {
                    $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $at);
                })
                ->orderByDesc('created_at')
                ->first();
            if ($historical) {
                return $historical;
            }
        }

        return (clone $base)->orderBy('id')->first();
    }

    /**
     * Tarifa por tipo + CBM sin filtro de fechas (filas seed / legacy).
     */
    public function findByTipoCbmInRange(string $tipoCliente, float $cbmTotal): ?CalculadoraTarifasConsolidado
    {
        $tipo = $this->resolveTipoCliente($tipoCliente, true);
        if (! $tipo) {
            return null;
        }

        $inRange = $this->baseTarifaQueryForTipo($tipo->id, $cbmTotal)
            ->orderBy('id')
            ->first();
        if ($inRange) {
            return $inRange;
        }

        return CalculadoraTarifasConsolidado::withTrashed()
            ->where('calculadora_tipo_cliente_id', $tipo->id)
            ->orderByDesc('limit_sup')
            ->orderBy('id')
            ->first();
    }

    /**
     * Tarifa del tipo/CBM que estaba vigente en el instante $at
     * (created_at <= at AND (vigente_hasta IS NULL OR vigente_hasta > at)).
     */
    public function findByTipoYCbmAt(string $tipoCliente, float $cbmTotal, Carbon $at): ?CalculadoraTarifasConsolidado
    {
        $tipo = $this->resolveTipoCliente($tipoCliente, true);
        if (! $tipo) {
            return null;
        }

        $base = $this->tarifaQuery()
            ->where('calculadora_tipo_cliente_id', $tipo->id)
            ->where(function ($q) use ($at) {
                $q->whereNull('created_at')->orWhere('created_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $at);
            });

        $tarifa = $this->applyCbmRangeToQuery(clone $base, $cbmTotal)
            ->orderByDesc('created_at')
            ->first();

        if ($tarifa) {
            return $tarifa;
        }

        return (clone $base)
            ->orderByDesc('limit_sup')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Tarifa por tipo + monto (sin filtrar CBM).
     */
    public function findByTipoAndValueOnly(
        string $tipoCliente,
        float $value,
        ?Carbon $at = null
    ): ?CalculadoraTarifasConsolidado {
        $tipo = $this->resolveTipoCliente($tipoCliente, true);
        if (! $tipo) {
            return null;
        }

        $base = $this->tarifaQuery()
            ->where('calculadora_tipo_cliente_id', $tipo->id)
            ->whereRaw('ABS(value - ?) < 0.01', [$value]);

        if ($at) {
            $historical = (clone $base)
                ->where(function ($q) use ($at) {
                    $q->whereNull('created_at')->orWhere('created_at', '<=', $at);
                })
                ->where(function ($q) use ($at) {
                    $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $at);
                })
                ->orderByDesc('created_at')
                ->first();
            if ($historical) {
                return $historical;
            }
        }

        return (clone $base)->orderBy('id')->first();
    }

    /**
     * @return array{updated: int, skipped: int, details: list<array<string, mixed>>}
     */
    public function runBackfillTarifaIds(?int $onlyId = null, bool $dryRun = false, bool $force = false): array
    {
        $updated = 0;
        $skipped = 0;
        $details = [];

        $query = CalculadoraImportacion::query()->with('proveedores');
        if (! $force) {
            $query->whereNull('calculadora_tarifa_consolidado_id');
        }
        if ($onlyId) {
            $query->where('id', $onlyId);
        }

        $query->orderBy('id')->chunkById(200, function ($calculadoras) use (&$updated, &$skipped, &$details, $dryRun) {
            foreach ($calculadoras as $calculadora) {
                $row = $this->findTarifaAtCalculadoraCreation($calculadora);
                if (! $row) {
                    $skipped++;
                    $details[] = [
                        'id' => $calculadora->id,
                        'status' => 'skipped',
                        'tipo' => $calculadora->tipo_cliente,
                        'tarifa' => $calculadora->tarifa,
                        'cbm' => $this->totalCbmFromCalculadora($calculadora),
                    ];

                    continue;
                }

                $tarifaType = strtoupper(trim((string) ($calculadora->tarifa_type ?? '')));
                if ($tarifaType === '') {
                    $tarifaType = strtoupper(trim((string) $row->type)) ?: 'PLAIN';
                }

                if (! $dryRun) {
                    \Illuminate\Support\Facades\DB::table('calculadora_importacion')
                        ->where('id', $calculadora->id)
                        ->update([
                            'calculadora_tarifa_consolidado_id' => (int) $row->id,
                            'tarifa_type' => $tarifaType,
                            'updated_at' => now(),
                        ]);
                }

                $updated++;
                $details[] = [
                    'id' => $calculadora->id,
                    'status' => 'updated',
                    'tarifa_row_id' => (int) $row->id,
                    'tarifa_row_value' => (float) $row->value,
                    'stored_tarifa' => (float) ($calculadora->tarifa ?? 0),
                ];
            }
        });

        return compact('updated', 'skipped', 'details');
    }

    /**
     * Todas las tarifas vigentes en un instante (una fila por tipo + rango CBM).
     *
     * @return \Illuminate\Support\Collection<int, CalculadoraTarifasConsolidado>
     */
    public function findAllTarifasVigentesAt(Carbon $at)
    {
        $rows = $this->tarifaQuery()
            ->with(['tipoCliente' => fn ($q) => $q->withTrashed()])
            ->where(function ($q) use ($at) {
                $q->whereNull('created_at')->orWhere('created_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $at);
            })
            ->whereHas('tipoCliente', fn ($q) => $q->withTrashed())
            ->orderBy('calculadora_tipo_cliente_id')
            ->orderBy('limit_inf')
            ->orderByDesc('created_at')
            ->get();

        return $rows->unique(function (CalculadoraTarifasConsolidado $row) {
            return $row->calculadora_tipo_cliente_id.'|'.$row->limit_inf.'|'.$row->limit_sup;
        })->values();
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

    private function resolveTipoCliente(string $tipoCliente, bool $includeTrashed = false): ?CalculadoraTipoCliente
    {
        $nombre = trim($tipoCliente);
        if ($nombre === '') {
            $nombre = 'NUEVO';
        }

        $query = CalculadoraTipoCliente::query();
        if ($includeTrashed) {
            $query->withTrashed();
        }

        if (ctype_digit($nombre)) {
            $byId = (clone $query)->where('id', (int) $nombre)->first();
            if ($byId) {
                return $byId;
            }
        }

        $tipo = $query->where('nombre', $nombre)->first();
        if (! $tipo) {
            $fallback = CalculadoraTipoCliente::query();
            if ($includeTrashed) {
                $fallback->withTrashed();
            }
            $tipo = $fallback->where('nombre', 'NUEVO')->first();
        }

        return $tipo;
    }

    private function tarifaQuery()
    {
        return CalculadoraTarifasConsolidado::withTrashed();
    }

    private function baseTarifaQueryForTipo(int $tipoClienteId, float $cbmTotal)
    {
        $cbmCents = $this->cbmToCents($cbmTotal);

        return $this->tarifaQuery()
            ->where('calculadora_tipo_cliente_id', $tipoClienteId)
            ->whereRaw('ROUND(limit_inf * 100) <= ?', [$cbmCents])
            ->whereRaw('ROUND(limit_sup * 100) >= ?', [$cbmCents]);
    }

    private function applyCbmRangeToQuery($query, float $cbmTotal)
    {
        $cbmCents = $this->cbmToCents($cbmTotal);

        return $query
            ->whereRaw('ROUND(limit_inf * 100) <= ?', [$cbmCents])
            ->whereRaw('ROUND(limit_sup * 100) >= ?', [$cbmCents]);
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
