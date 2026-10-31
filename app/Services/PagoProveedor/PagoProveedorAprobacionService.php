<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorAbonoAplicacion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PagoProveedorAprobacionService
{
    public function __construct(
        private readonly PagoProveedorConfiguracionService $configuration,
        private readonly PagoProveedorHistorialService $history,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function list(array $filters, array $actor): LengthAwarePaginator
    {
        $bucket = (string) ($filters['bucket'] ?? PagoProveedorSolicitudProveedor::APPROVAL_BUCKET_PENDING);
        $providers = $this->candidates($bucket);
        if ($bucket !== PagoProveedorSolicitudProveedor::APPROVAL_BUCKET_APPROVED) {
            DB::transaction(function () use ($providers, $actor) {
                foreach ($providers as $provider) {
                    $this->evaluate($provider, $actor);
                }
            });
        }

        $status = (string) ($filters['status'] ?? PagoProveedorSolicitudProveedor::FILTER_ALL);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $visible = $providers->filter(function (PagoProveedorSolicitudProveedor $provider) use ($bucket, $status, $search) {
            if ($bucket === PagoProveedorSolicitudProveedor::APPROVAL_BUCKET_APPROVED) {
                return $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_APPROVED
                    && $this->matchesStatus($provider, $status)
                    && $this->matchesSearch($provider, $search);
            }

            if ((int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
                return false;
            }
            if (!in_array($provider->approval_status, [
                PagoProveedorSolicitudProveedor::APPROVAL_PENDING,
                PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
            ], true)) {
                return false;
            }
            $comparison = $this->compare($provider);
            if (!$this->readyForDecision($comparison)) {
                return false;
            }

            return $this->matchesStatus($provider, $status) && $this->matchesSearch($provider, $search);
        })->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));

        return new Paginator(
            $visible->forPage($page, $perPage)->values(),
            $visible->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public function pendingCount(): int
    {
        return PagoProveedorSolicitudProveedor::query()
            ->where('stage', PagoProveedorSolicitudProveedor::STAGE_VALIDATION)
            ->whereIn('approval_status', [
                PagoProveedorSolicitudProveedor::APPROVAL_PENDING,
                PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
            ])
            ->where('status', '!=', PagoProveedorSolicitudProveedor::STATUS_CANCELLED)
            ->count();
    }

    public function show(int $id): PagoProveedorSolicitudProveedor
    {
        return $this->find($id);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function evaluate(PagoProveedorSolicitudProveedor $provider, array $actor): void
    {
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
            return;
        }
        if ((int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
            return;
        }
        if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_APPROVED) {
            return;
        }

        $this->expireCollect($provider);
        $comparison = $this->compare($provider);
        if (!$this->readyForDecision($comparison)) {
            return;
        }

        $clean = $this->withinTolerance($comparison['category']);
        if ($provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT) {
            if ($clean && $this->autoApprove()) {
                $this->markApproved($provider, $actor, true, null, $comparison);
            } elseif ($comparison['difference'] < 0) {
                $provider->collect_amount = round(abs($comparison['difference']), 2);
                $provider->save();
            }

            return;
        }

        if ($clean && $this->autoApprove()) {
            $this->markApproved($provider, $actor, true, null, $comparison);

            return;
        }

        if ($provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_PENDING) {
            $provider->approval_status = PagoProveedorSolicitudProveedor::APPROVAL_PENDING;
            $provider->save();
        }
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function approve(int $id, ?string $reason, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($id, $reason, $actor) {
            $provider = $this->find($id, true);
            $this->requireOpen($provider);
            $comparison = $this->compare($provider);
            if (!$this->readyForDecision($comparison)) {
                throw new PagoProveedorException('Este pago todavía no se puede aprobar.');
            }
            $reason = $this->cleanReason($reason);
            if ($this->needsReason($comparison['category']) && $reason === null) {
                throw new PagoProveedorException('Indica el motivo para asumir la diferencia.');
            }
            $this->markApproved($provider, $actor, false, $reason, $comparison);

            return $this->refreshed($provider);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function sendToCollect(int $id, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($id, $actor) {
            $provider = $this->find($id, true);
            $this->requireOpen($provider);
            $comparison = $this->compare($provider);
            if ($comparison['difference'] >= -PagoProveedorSolicitudProveedor::MONEY_EPSILON) {
                throw new PagoProveedorException('No hay un faltante para cobrar al cliente.');
            }
            $amount = round(abs($comparison['difference']), 2);
            $provider->approval_status = PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT;
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_OBSERVED;
            $provider->collect_amount = $amount;
            $provider->collect_until = now()->addDays($this->remedyDays());
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Pago por cobrar',
                $actor,
                'Faltan '.$amount.'. Ventas debe cobrarlos al cliente.'
            );

            return $this->refreshed($provider);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function extendCollect(int $id, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($id, $actor) {
            $provider = $this->find($id, true);
            if ($provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT || !$provider->collect_until) {
                throw new PagoProveedorException('Este pago no está por cobrar.');
            }
            $base = $provider->collect_until->isFuture() ? $provider->collect_until : now();
            $provider->collect_until = $base->copy()->addDays($this->remedyDays());
            $provider->approval_status = PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT;
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_OBSERVED;
            $provider->stage = PagoProveedorSolicitudProveedor::STAGE_VALIDATION;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Plazo de cobro extendido',
                $actor,
                $provider->code_supplier
            );

            return $this->refreshed($provider);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function cancel(int $id, string $reason, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($id, $reason, $actor) {
            $provider = $this->find($id, true);
            $this->requireOpen($provider);
            $reason = $this->cleanReason($reason);
            if ($reason === null) {
                throw new PagoProveedorException('Indica el motivo de la anulación.');
            }
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_CANCELLED;
            $provider->approval_status = null;
            $provider->collect_until = null;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Pago anulado',
                $actor,
                $reason
            );

            return $this->refreshed($provider);
        });
    }

    public function release(PagoProveedorAbono $deposit): void
    {
        $deposit->loadMissing('aplicaciones.proveedor');
        foreach ($deposit->aplicaciones as $application) {
            $provider = $application->proveedor;
            if (!$provider || (int) $provider->stage > PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
                continue;
            }
            if (!in_array($provider->approval_status, [
                PagoProveedorSolicitudProveedor::APPROVAL_PENDING,
                PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
            ], true)) {
                continue;
            }
            $provider->approval_status = null;
            $provider->approval_mode = null;
            $provider->collect_amount = null;
            $provider->collect_until = null;
            if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED) {
                $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
            }
            $provider->save();
        }
    }

    public function outstanding(PagoProveedorSolicitudProveedor $provider): float
    {
        if ($provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT) {
            return 0.0;
        }
        $comparison = $this->compare($provider);
        $waiting = 0.0;
        foreach ($provider->aplicaciones as $application) {
            $deposit = $application->abono;
            if (!$deposit || in_array($deposit->status, [PagoProveedorAbono::STATUS_CANCELLED, PagoProveedorAbono::STATUS_VALIDATED], true)) {
                continue;
            }
            $waiting += (float) $application->amount;
        }

        return round(max(0, $comparison['charged'] - $comparison['received'] - $waiting), 2);
    }

    /**
     * @return array{
     *     charged: float,
     *     received: float,
     *     difference: float,
     *     category: string,
     *     unverified: int,
     *     observed: int,
     *     shared_pending: bool,
     *     data_ready: bool,
     *     has_validated: bool,
     *     tolerance: float,
     *     exception_limit: float
     * }
     */
    public function compare(PagoProveedorSolicitudProveedor $provider): array
    {
        $provider->loadMissing(['datos', 'aplicaciones.abono.aplicaciones', 'solicitud']);
        $tolerance = $this->tolerance();
        $charged = $this->charged($provider);
        $received = 0.0;
        $unverified = 0;
        $observed = 0;
        $sharedPending = false;
        $hasValidated = false;

        foreach ($provider->aplicaciones as $application) {
            $deposit = $application->abono;
            if (!$deposit || $deposit->status === PagoProveedorAbono::STATUS_CANCELLED) {
                continue;
            }
            if ($deposit->status === PagoProveedorAbono::STATUS_OBSERVED) {
                $observed++;
                continue;
            }
            if ($deposit->status !== PagoProveedorAbono::STATUS_VALIDATED) {
                $unverified++;
                continue;
            }
            $hasValidated = true;
            $received += $this->attributed($provider, $deposit, $tolerance, $sharedPending);
        }

        $difference = round($received - $charged, 2);

        return [
            'charged' => $charged,
            'received' => round($received, 2),
            'difference' => $difference,
            'category' => $this->classify($difference, $tolerance, $this->exceptionLimit()),
            'unverified' => $unverified,
            'observed' => $observed,
            'shared_pending' => $sharedPending,
            'data_ready' => $this->dataReady($provider),
            'has_validated' => $hasValidated,
            'tolerance' => $tolerance,
            'exception_limit' => $this->exceptionLimit(),
        ];
    }

    public function classify(float $difference, float $tolerance, float $exceptionLimit): string
    {
        $amount = abs($difference);
        if ($amount < PagoProveedorSolicitudProveedor::MONEY_EPSILON) {
            return PagoProveedorSolicitudProveedor::GAP_MATCH;
        }
        $within = $amount <= $tolerance + 0.001;
        $exceptional = $amount <= $exceptionLimit + 0.001;
        if ($difference < 0) {
            if ($within) {
                return PagoProveedorSolicitudProveedor::GAP_SHORT_TOLERANCE;
            }
            if ($exceptional) {
                return PagoProveedorSolicitudProveedor::GAP_SHORT_EXCEPTION;
            }

            return PagoProveedorSolicitudProveedor::GAP_SHORT;
        }

        return $within
            ? PagoProveedorSolicitudProveedor::GAP_OVER_TOLERANCE
            : PagoProveedorSolicitudProveedor::GAP_OVER;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'tolerance' => $this->tolerance(),
            'exception_limit' => $this->exceptionLimit(),
            'remedy_days' => $this->remedyDays(),
            'verify_hours' => max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_VERIFY_HOURS, 2)),
            'undo_minutes' => max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_UNDO_MINUTES, 5)),
            'undo_hours' => max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_UNDO_HOURS, 24)),
            'decision_hours' => max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_DECISION_HOURS, 72)),
            'auto_approve' => $this->autoApprove(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveSettings(array $data): array
    {
        if ((float) $data['exception_limit'] + 0.001 < (float) $data['tolerance']) {
            throw new PagoProveedorException('El límite excepcional no puede ser menor que la tolerancia.');
        }
        $map = [
            'tolerance' => PagoProveedorSolicitudProveedor::SETTING_TOLERANCE,
            'exception_limit' => PagoProveedorSolicitudProveedor::SETTING_EXCEPTION_LIMIT,
            'remedy_days' => PagoProveedorSolicitudProveedor::SETTING_REMEDY_DAYS,
            'verify_hours' => PagoProveedorSolicitudProveedor::SETTING_VERIFY_HOURS,
            'undo_minutes' => PagoProveedorSolicitudProveedor::SETTING_UNDO_MINUTES,
            'undo_hours' => PagoProveedorSolicitudProveedor::SETTING_UNDO_HOURS,
            'decision_hours' => PagoProveedorSolicitudProveedor::SETTING_DECISION_HOURS,
        ];
        foreach ($map as $input => $key) {
            $this->configuration->save($key, $data[$input]);
        }
        $this->configuration->save(PagoProveedorSolicitudProveedor::SETTING_AUTO_APPROVE, (bool) $data['auto_approve']);

        return $this->settings();
    }

    public function needsReason(string $category): bool
    {
        return in_array($category, [
            PagoProveedorSolicitudProveedor::GAP_SHORT_EXCEPTION,
            PagoProveedorSolicitudProveedor::GAP_SHORT,
            PagoProveedorSolicitudProveedor::GAP_OVER,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['solicitud', 'datos', 'aplicaciones.abono.aplicaciones'];
    }

    /**
     * @param  array{charged: float, received: float, difference: float, category: string, unverified: int, observed: int, shared_pending: bool, data_ready: bool, has_validated: bool, tolerance: float, exception_limit: float}  $comparison
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function markApproved(PagoProveedorSolicitudProveedor $provider, array $actor, bool $automatic, ?string $reason, array $comparison): void
    {
        $shortage = $comparison['difference'] < 0 ? round(abs($comparison['difference']), 2) : 0.0;
        $provider->approval_status = PagoProveedorSolicitudProveedor::APPROVAL_APPROVED;
        $provider->approval_mode = $automatic
            ? PagoProveedorSolicitudProveedor::APPROVAL_AUTOMATIC
            : PagoProveedorSolicitudProveedor::APPROVAL_MANUAL;
        $provider->assumed_amount = $shortage >= PagoProveedorSolicitudProveedor::MONEY_EPSILON ? $shortage : null;
        $provider->assumed_reason = $shortage >= PagoProveedorSolicitudProveedor::MONEY_EPSILON ? $reason : null;
        $provider->approved_at = now();
        $provider->approved_by_user_id = $actor['id'];
        $provider->stage = PagoProveedorSolicitudProveedor::STAGE_EXECUTION;
        $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
        $provider->collect_amount = null;
        $provider->collect_until = null;
        $provider->save();

        if ($comparison['category'] === PagoProveedorSolicitudProveedor::GAP_OVER) {
            $this->markSurplusToConsult($provider);
        }

        $detail = $automatic ? 'Aprobación automática' : ($reason ?: 'Importes coinciden');
        if ($comparison['category'] === PagoProveedorSolicitudProveedor::GAP_OVER) {
            $detail .= '. Sobran '.round($comparison['difference'], 2).': Ventas consulta al cliente.';
        }
        $this->history->registrar(
            PagoProveedorHistorial::ENTITY_REQUEST,
            (int) $provider->request_id,
            'Pago aprobado',
            $actor,
            $detail
        );
    }

    private function markSurplusToConsult(PagoProveedorSolicitudProveedor $provider): void
    {
        foreach ($provider->aplicaciones as $application) {
            $deposit = $application->abono;
            if (!$deposit || $deposit->status !== PagoProveedorAbono::STATUS_VALIDATED) {
                continue;
            }
            if ((float) $deposit->surplus <= $this->tolerance() || $deposit->surplus_decision) {
                continue;
            }
            $deposit->surplus_decision = PagoProveedorAbono::EXCESS_TO_CONSULT;
            $deposit->save();
        }
    }

    private function expireCollect(PagoProveedorSolicitudProveedor $provider): void
    {
        if ($provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT || !$provider->collect_until) {
            return;
        }
        if ($provider->collect_until->isFuture()) {
            return;
        }
        $provider->approval_status = PagoProveedorSolicitudProveedor::APPROVAL_PENDING;
        $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
        $provider->save();
    }

    private function charged(PagoProveedorSolicitudProveedor $provider): float
    {
        $installments = $provider->summary_json['cuotas'] ?? [];
        $index = max(0, ((int) $provider->current_installment) - 1);
        if (is_array($installments) && isset($installments[$index]['total'])) {
            return round((float) $installments[$index]['total'], 2);
        }

        return round((float) $provider->total_amount, 2);
    }

    private function attributed(PagoProveedorSolicitudProveedor $provider, PagoProveedorAbono $deposit, float $tolerance, bool &$sharedPending): float
    {
        $deposit->loadMissing('aplicaciones');
        $applications = $deposit->aplicaciones;
        $bank = round((float) ($deposit->bank_amount ?? $deposit->amount), 2);
        if ($applications->count() < 2) {
            return $bank;
        }

        $applied = round((float) $applications->sum('amount'), 2);
        $shortfall = round($applied - $bank, 2);
        $ownRow = $applications->firstWhere('request_provider_id', $provider->id);
        $own = round((float) ($ownRow->amount ?? 0), 2);
        if ($shortfall > $tolerance + 0.001) {
            $undecided = $applications->contains(fn (PagoProveedorAbonoAplicacion $row) => $row->continues === null);
            if ($undecided) {
                $sharedPending = true;

                return $own;
            }
            if ($ownRow && $ownRow->continues !== null && !(bool) $ownRow->continues) {
                return 0.0;
            }

            return $own;
        }
        if ($shortfall >= PagoProveedorSolicitudProveedor::MONEY_EPSILON) {
            $carrier = $applications->sortByDesc(fn (PagoProveedorAbonoAplicacion $row) => $row->amount)->first();
            if ($carrier && (int) $carrier->request_provider_id === (int) $provider->id) {
                return round($own - $shortfall, 2);
            }
        }

        return $own;
    }

    private function dataReady(PagoProveedorSolicitudProveedor $provider): bool
    {
        $data = $provider->datos;
        if (!$data) {
            return false;
        }
        if ($provider->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
            return trim((string) $data->seller_name) !== '' && trim((string) $data->wechat) !== '';
        }

        return $data->status === PagoProveedorDatoProveedor::STATUS_RECEIVED;
    }

    /**
     * @param  array{data_ready: bool, unverified: int, observed: int, has_validated: bool, shared_pending: bool}  $comparison
     */
    private function readyForDecision(array $comparison): bool
    {
        return $comparison['data_ready']
            && $comparison['unverified'] === 0
            && $comparison['observed'] === 0
            && $comparison['has_validated']
            && !$comparison['shared_pending'];
    }

    private function withinTolerance(string $category): bool
    {
        return in_array($category, [
            PagoProveedorSolicitudProveedor::GAP_MATCH,
            PagoProveedorSolicitudProveedor::GAP_SHORT_TOLERANCE,
            PagoProveedorSolicitudProveedor::GAP_OVER_TOLERANCE,
        ], true);
    }

    /**
     * @return Collection<int, PagoProveedorSolicitudProveedor>
     */
    private function candidates(string $bucket): Collection
    {
        $query = PagoProveedorSolicitudProveedor::query()
            ->with($this->relations())
            ->where('status', '!=', PagoProveedorSolicitudProveedor::STATUS_CANCELLED);

        if ($bucket === PagoProveedorSolicitudProveedor::APPROVAL_BUCKET_APPROVED) {
            $query->where('approval_status', PagoProveedorSolicitudProveedor::APPROVAL_APPROVED)
                ->orderByDesc('approved_at');
        } else {
            $query->where('stage', PagoProveedorSolicitudProveedor::STAGE_VALIDATION)
                ->where(function ($inner) {
                    $inner->whereNull('approval_status')
                        ->orWhereIn('approval_status', [
                            PagoProveedorSolicitudProveedor::APPROVAL_PENDING,
                            PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT,
                        ]);
                })
                ->orderByDesc('id');
        }

        return $query->get();
    }

    private function matchesStatus(PagoProveedorSolicitudProveedor $provider, string $status): bool
    {
        if ($status === '' || $status === PagoProveedorSolicitudProveedor::FILTER_ALL) {
            return true;
        }

        return $provider->approval_status === $status;
    }

    private function matchesSearch(PagoProveedorSolicitudProveedor $provider, string $search): bool
    {
        if ($search === '') {
            return true;
        }
        $request = $provider->solicitud;
        $haystack = mb_strtolower(implode(' ', array_filter([
            $request?->client_name,
            $request?->consolidation,
            $provider->supplier_name,
            $provider->code_supplier,
            $provider->request_code,
        ])));

        return str_contains($haystack, $search);
    }

    private function refreshed(PagoProveedorSolicitudProveedor $provider): PagoProveedorSolicitudProveedor
    {
        $fresh = $provider->fresh($this->relations());
        if (!$fresh) {
            throw new PagoProveedorException('El pago no existe.', 404);
        }

        return $fresh;
    }

    private function find(int $id, bool $lock = false): PagoProveedorSolicitudProveedor
    {
        $query = PagoProveedorSolicitudProveedor::query()->with($this->relations());
        if ($lock) {
            $query->lockForUpdate();
        }
        $provider = $query->find($id);
        if (!$provider) {
            throw new PagoProveedorException('El pago no existe.', 404);
        }

        return $provider;
    }

    private function requireOpen(PagoProveedorSolicitudProveedor $provider): void
    {
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED
            || $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_APPROVED
            || (int) $provider->stage > PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
            throw new PagoProveedorException('Este pago ya no está pendiente de aprobar.');
        }
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }

    private function tolerance(): float
    {
        return (float) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_TOLERANCE, 1);
    }

    private function exceptionLimit(): float
    {
        return (float) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_EXCEPTION_LIMIT, 2);
    }

    private function remedyDays(): int
    {
        return max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_REMEDY_DAYS, 7));
    }

    private function autoApprove(): bool
    {
        $value = $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_AUTO_APPROVE, true);
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'si', 'sí'], true);
        }

        return true;
    }
}
