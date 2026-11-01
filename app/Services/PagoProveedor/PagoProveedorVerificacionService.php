<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PagoProveedorVerificacionService
{
    public function __construct(
        private readonly PagoProveedorConfiguracionService $configuration,
        private readonly PagoProveedorHistorialService $history,
        private readonly PagoProveedorAprobacionService $approval,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = PagoProveedorAbono::query()
            ->with(['solicitud.proveedores', 'aplicaciones'])
            ->orderByDesc('id');

        $status = (string) ($filters['status'] ?? PagoProveedorAbono::STATUS_IN_VALIDATION);
        if ($status !== PagoProveedorAbono::FILTER_ALL) {
            $query->where('status', $status);
        }

        if (!empty($filters['search'])) {
            $text = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($inner) use ($text) {
                $inner->where('operation_number', 'like', $text)
                    ->orWhere('amount', 'like', $text)
                    ->orWhereHas('solicitud', function ($request) use ($text) {
                        $request->where('client_name', 'like', $text)
                            ->orWhere('consolidation', 'like', $text)
                            ->orWhereHas('proveedores', function ($providers) use ($text) {
                                $providers->where('request_code', 'like', $text)
                                    ->orWhere('code_supplier', 'like', $text);
                            });
                    });
            });
        }

        $bank = (string) ($filters['bank'] ?? '');
        if ($bank !== '' && $bank !== PagoProveedorAbono::FILTER_ALL) {
            $query->where('bank', $bank);
        }

        $consolidation = trim((string) ($filters['consolidation'] ?? ''));
        if ($consolidation !== '' && $consolidation !== PagoProveedorAbono::FILTER_ALL) {
            $query->whereHas('solicitud', fn ($request) => $request->where('consolidation', $consolidation));
        }

        if (!empty($filters['paid_from'])) {
            $query->whereDate('paid_on', '>=', $filters['paid_from']);
        }
        if (!empty($filters['paid_to'])) {
            $query->whereDate('paid_on', '<=', $filters['paid_to']);
        }

        return $query->paginate(max(1, (int) ($filters['per_page'] ?? 15)));
    }

    public function pendingCount(): int
    {
        return PagoProveedorAbono::query()
            ->where('status', PagoProveedorAbono::STATUS_IN_VALIDATION)
            ->count();
    }

    /**
     * @return array<int, string>
     */
    public function consolidations(): array
    {
        return PagoProveedorSolicitud::query()
            ->whereNotNull('consolidation')
            ->where('consolidation', '!=', '')
            ->distinct()
            ->orderBy('consolidation')
            ->pluck('consolidation')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    public function show(int $id): PagoProveedorAbono
    {
        return $this->find($id);
    }

    /**
     * Corrige banco, fecha o número de operación. No valida el depósito.
     *
     * @param  array{bank: string, paid_on: string, operation_number: string}  $data
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function updateDetails(int $id, array $data, array $actor): PagoProveedorAbono
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $deposit = $this->find($id, true);
            $this->requireOpen($deposit);
            $deposit->bank = $data['bank'];
            $deposit->paid_on = \Illuminate\Support\Carbon::parse($data['paid_on']);
            $deposit->operation_number = trim($data['operation_number']);
            $deposit->corrected_by_admin = true;
            $deposit->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Depósito corregido',
                $actor,
                $deposit->operation_number
            );

            return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function validateDeposit(int $id, float $bankAmount, array $actor): PagoProveedorAbono
    {
        return DB::transaction(function () use ($id, $bankAmount, $actor) {
            $deposit = $this->find($id, true);
            $this->requireOpen($deposit);
            if ($bankAmount <= 0) {
                throw new PagoProveedorException('El importe del banco debe ser mayor a 0.');
            }

            if (abs($bankAmount - (float) $deposit->amount) >= 0.005 && $deposit->recorded_amount === null) {
                $deposit->recorded_amount = $deposit->amount;
            }
            $applied = round((float) $deposit->aplicaciones->sum('amount'), 2);
            $deposit->amount = round($bankAmount, 2);
            $deposit->bank_amount = round($bankAmount, 2);
            $deposit->surplus = round(max(0, $bankAmount - $applied), 2);
            $deposit->status = PagoProveedorAbono::STATUS_VALIDATED;
            $deposit->observation_reason = null;
            $deposit->observation_detail = null;
            $deposit->verified_at = now();
            $deposit->verified_by_user_id = $actor['id'];
            $deposit->save();

            $note = $deposit->recorded_amount !== null && abs((float) $deposit->recorded_amount - $bankAmount) >= 0.005
                ? 'Ventas registró '.$deposit->recorded_amount.' · se ajustó al importe del banco '.$deposit->bank_amount
                : 'Importe del banco '.$deposit->bank_amount;
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Depósito validado',
                $actor,
                $note
            );
            $deposit->load(['aplicaciones.proveedor.datos', 'aplicaciones.proveedor.aplicaciones.abono.aplicaciones', 'aplicaciones.proveedor.solicitud']);
            foreach ($deposit->aplicaciones as $application) {
                if ($application->proveedor) {
                    $this->approval->evaluate($application->proveedor, $actor);
                }
            }

            return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function observe(int $id, string $reason, ?string $detail, ?string $newLabel, array $actor): PagoProveedorAbono
    {
        return DB::transaction(function () use ($id, $reason, $detail, $newLabel, $actor) {
            $deposit = $this->find($id, true);
            $this->requireOpen($deposit);
            $code = $this->resolveReason($reason, $newLabel);
            $deposit->status = PagoProveedorAbono::STATUS_OBSERVED;
            $deposit->observation_reason = $code;
            $deposit->observation_detail = $detail !== null && trim($detail) !== '' ? trim($detail) : null;
            $deposit->verified_at = now();
            $deposit->verified_by_user_id = $actor['id'];
            $deposit->save();
            $label = $this->reasonLabel($code);
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Depósito observado',
                $actor,
                trim($label.' '.($deposit->observation_detail ?? ''))
            );

            return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function undo(int $id, array $actor): PagoProveedorAbono
    {
        return DB::transaction(function () use ($id, $actor) {
            $deposit = $this->find($id, true);
            if (!in_array($deposit->status, [PagoProveedorAbono::STATUS_VALIDATED, PagoProveedorAbono::STATUS_OBSERVED], true)) {
                throw new PagoProveedorException('Este depósito no tiene una verificación para deshacer.');
            }
            if ($this->providerMovedOn($deposit)) {
                throw new PagoProveedorException('No se puede deshacer: un proveedor de este abono ya pasó de etapa.');
            }
            if (!$deposit->verified_at) {
                throw new PagoProveedorException('Este depósito no tiene fecha de verificación.');
            }

            $minutes = (int) abs($deposit->verified_at->diffInMinutes(now()));
            $silentMinutes = max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_UNDO_MINUTES, 5));
            $noticeHours = max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_UNDO_HOURS, 24));
            if ($minutes > $noticeHours * 60) {
                throw new PagoProveedorException('Ya pasó el plazo para deshacer esta verificación.');
            }

            if ($deposit->recorded_amount !== null) {
                $deposit->amount = $deposit->recorded_amount;
                $deposit->recorded_amount = null;
            }
            $deposit->bank_amount = null;
            $deposit->status = PagoProveedorAbono::STATUS_IN_VALIDATION;
            $deposit->observation_reason = null;
            $deposit->observation_detail = null;
            $deposit->verified_at = null;
            $deposit->verified_by_user_id = null;
            $deposit->save();

            $this->approval->release($deposit);
            $notifies = $minutes > $silentMinutes;
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Verificación deshecha',
                $actor,
                $notifies ? 'Se avisó a Ventas.' : 'Corrección dentro del plazo corto.'
            );

            return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']);
        });
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function reasons(): array
    {
        $reasons = $this->defaultReasons();
        $saved = $this->configuration->value(PagoProveedorAbono::SETTING_OBSERVATION_REASONS, []);
        if (!is_array($saved)) {
            return $reasons;
        }

        foreach ($saved as $row) {
            if (!is_array($row) || empty($row['code']) || empty($row['label'])) {
                continue;
            }
            $code = (string) $row['code'];
            if (collect($reasons)->contains(fn (array $item) => $item['code'] === $code)) {
                continue;
            }
            $reasons[] = ['code' => $code, 'label' => (string) $row['label']];
        }

        return $reasons;
    }

    public function providerMovedOn(PagoProveedorAbono $deposit): bool
    {
        $request = $deposit->solicitud;

        return $deposit->aplicaciones->contains(function ($application) use ($request) {
            $provider = $request?->proveedores?->firstWhere('id', $application->request_provider_id);

            return $provider && (int) $provider->stage > PagoProveedorSolicitudProveedor::STAGE_VALIDATION;
        });
    }

    private function find(int $id, bool $lock = false): PagoProveedorAbono
    {
        $query = PagoProveedorAbono::query()->with(['solicitud.proveedores', 'aplicaciones']);
        if ($lock) {
            $query->lockForUpdate();
        }
        $deposit = $query->find($id);
        if (!$deposit) {
            throw new PagoProveedorException('El depósito no existe.', 404);
        }

        return $deposit;
    }

    private function requireOpen(PagoProveedorAbono $deposit): void
    {
        if (!in_array($deposit->status, [PagoProveedorAbono::STATUS_IN_VALIDATION, PagoProveedorAbono::STATUS_OBSERVED], true)) {
            throw new PagoProveedorException('Este depósito ya no está pendiente de verificar.');
        }
    }

    private function resolveReason(string $reason, ?string $newLabel): string
    {
        $reason = trim($reason);
        $known = collect($this->reasons())->keyBy('code');
        if ($reason !== '' && $known->has($reason)) {
            return $reason;
        }

        $label = trim((string) $newLabel);
        if ($label === '') {
            throw new PagoProveedorException('Elige un motivo de observación.');
        }

        $code = Str::slug($label, '_');
        if ($code === '') {
            throw new PagoProveedorException('El motivo no es válido.');
        }
        if (!$known->has($code)) {
            $saved = $this->configuration->value(PagoProveedorAbono::SETTING_OBSERVATION_REASONS, []);
            $saved = is_array($saved) ? $saved : [];
            $saved[] = ['code' => $code, 'label' => $label];
            $this->configuration->save(PagoProveedorAbono::SETTING_OBSERVATION_REASONS, array_values($saved));
        }

        return $code;
    }

    private function reasonLabel(string $code): string
    {
        $match = collect($this->reasons())->firstWhere('code', $code);

        return $match['label'] ?? $code;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function defaultReasons(): array
    {
        return [
            ['code' => PagoProveedorAbono::REASON_NOT_RECEIVED, 'label' => 'No ingresó'],
            ['code' => PagoProveedorAbono::REASON_OTHER_ACCOUNT, 'label' => 'Depósito a otra cuenta'],
            ['code' => PagoProveedorAbono::REASON_FAKE_VOUCHER, 'label' => 'Voucher falso'],
        ];
    }
}
