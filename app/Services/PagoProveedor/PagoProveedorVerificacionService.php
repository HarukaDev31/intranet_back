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

        $page = $query->paginate(max(1, (int) ($filters['per_page'] ?? 15)));
        foreach ($page->items() as $deposit) {
            if ($deposit instanceof PagoProveedorAbono) {
                $this->escalateIfDue($deposit);
            }
        }

        return $page;
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
        $deposit = $this->find($id);
        $this->escalateIfDue($deposit);

        return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']) ?: $deposit;
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
            $deposit->observe_until = null;
            $deposit->observation_escalated_at = null;
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
            $deposit->observe_until = now()->addDays($this->remedyDays());
            $deposit->observation_escalated_at = null;
            $deposit->verified_at = now();
            $deposit->verified_by_user_id = $actor['id'];
            $deposit->save();
            $label = $this->reasonLabel($code);
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Depósito observado',
                $actor,
                trim($label.' '.($deposit->observation_detail ?? '').' · plazo '.$this->remedyDays().' días')
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
            $deposit->observe_until = null;
            $deposit->observation_escalated_at = null;
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
     * Extiende el plazo de una observación vencida (RN-112).
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function extendObservation(int $id, string $reason, ?int $days, array $actor): PagoProveedorAbono
    {
        return DB::transaction(function () use ($id, $reason, $days, $actor) {
            $deposit = $this->find($id, true);
            if ($deposit->status !== PagoProveedorAbono::STATUS_OBSERVED || !$deposit->observe_until) {
                throw new PagoProveedorException('Este depósito no tiene un plazo de observación para extender.');
            }
            if ($deposit->observe_until->isFuture()) {
                throw new PagoProveedorException('El plazo de la observación todavía no venció.');
            }
            $note = trim($reason);
            if ($note === '') {
                throw new PagoProveedorException('Indica el motivo para extender el plazo.');
            }
            $extra = max(1, $days ?? $this->remedyDays());
            $deposit->observe_until = now()->addDays($extra);
            $deposit->observation_escalated_at = null;
            $deposit->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Plazo de observación extendido',
                $actor,
                $extra.' día'.($extra === 1 ? '' : 's').': '.$note
            );

            return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']);
        });
    }

    /**
     * Anula los pagos vinculados a un depósito con observación vencida (RN-112).
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function cancelObservedPayment(int $id, string $reason, array $actor): PagoProveedorAbono
    {
        return DB::transaction(function () use ($id, $reason, $actor) {
            $deposit = $this->find($id, true);
            if ($deposit->status !== PagoProveedorAbono::STATUS_OBSERVED || !$deposit->observe_until) {
                throw new PagoProveedorException('Solo se anula un pago con observación de depósito vencida.');
            }
            if ($deposit->observe_until->isFuture()) {
                throw new PagoProveedorException('El plazo de la observación todavía no venció.');
            }
            $note = trim($reason);
            if ($note === '') {
                throw new PagoProveedorException('Indica el motivo de la anulación.');
            }

            $deposit->loadMissing(['aplicaciones.proveedor', 'solicitud.proveedores']);
            $cancelled = 0;
            foreach ($deposit->aplicaciones as $application) {
                $provider = $application->proveedor
                    ?: $deposit->solicitud?->proveedores?->firstWhere('id', $application->request_provider_id);
                if (!$provider || $provider->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                    continue;
                }
                if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_APPROVED
                    || (int) $provider->stage > PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
                    continue;
                }
                $provider->status = PagoProveedorSolicitudProveedor::STATUS_CANCELLED;
                $provider->approval_status = null;
                $provider->collect_until = null;
                $provider->save();
                $cancelled++;
            }
            if ($cancelled === 0) {
                throw new PagoProveedorException('No hay un pago abierto vinculado a este depósito para anular.');
            }

            $deposit->status = PagoProveedorAbono::STATUS_CANCELLED;
            $deposit->observe_until = null;
            $deposit->observation_escalated_at = null;
            $deposit->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $deposit->request_id,
                'Pago anulado por observación vencida',
                $actor,
                $note
            );

            return $deposit->fresh(['solicitud.proveedores', 'aplicaciones']);
        });
    }

    /**
     * Aviso único a jefatura cuando vence el plazo de 7 días (RN-112 / PV-11).
     */
    public function escalateIfDue(PagoProveedorAbono $deposit): void
    {
        if ($deposit->status !== PagoProveedorAbono::STATUS_OBSERVED || !$deposit->observe_until) {
            return;
        }
        if ($deposit->observe_until->isFuture() || $deposit->observation_escalated_at) {
            return;
        }
        $deposit->observation_escalated_at = now();
        $deposit->save();
        $this->history->registrar(
            PagoProveedorHistorial::ENTITY_REQUEST,
            (int) $deposit->request_id,
            'Observación vencida',
            ['id' => null, 'nombre' => 'Sistema', 'area' => 'Sistema'],
            'Más de '.$this->remedyDays().' días sin subsanar. Administración decide: extender el plazo o anular el pago.'
        );
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

    private function remedyDays(): int
    {
        return max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_REMEDY_DAYS, 7));
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
