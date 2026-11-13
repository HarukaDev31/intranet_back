<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorReembolso;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PagoProveedorReembolsoService
{
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorHistorialService $history,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? PagoProveedorReembolso::STATUS_REQUESTED);
        $query = PagoProveedorReembolso::query()
            ->with(['solicitud', 'proveedor'])
            ->orderByDesc('id');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate(max(1, (int) ($filters['per_page'] ?? 15)));
    }

    public function pendingCount(): int
    {
        return PagoProveedorReembolso::query()->where('status', PagoProveedorReembolso::STATUS_REQUESTED)->count();
    }

    /**
     * El reembolso lo solicita Ventas a pedido del cliente (RN-120): nace por consultar salvo que Ventas ya lo haya decidido.
     */
    public function ensureSurplus(PagoProveedorAbono $deposit, array $actor, bool $requested = false): PagoProveedorReembolso
    {
        $existing = PagoProveedorReembolso::query()->where('deposit_id', $deposit->id)->where('origin', PagoProveedorReembolso::ORIGIN_SURPLUS)->first();
        if ($existing) {
            return $existing;
        }
        $deposit->loadMissing('aplicaciones');
        $amount = round((float) $deposit->surplus, 2);
        if ($amount <= 0) {
            throw new PagoProveedorException('No hay excedente para reembolsar.');
        }
        $providerId = $this->surplusOwnerId($deposit);
        $refund = PagoProveedorReembolso::query()->create([
            'request_id' => $deposit->request_id,
            'request_provider_id' => $providerId,
            'deposit_id' => $deposit->id,
            'origin' => PagoProveedorReembolso::ORIGIN_SURPLUS,
            'status' => $requested ? PagoProveedorReembolso::STATUS_REQUESTED : PagoProveedorReembolso::STATUS_CONSULT,
            'amount' => $amount,
            'breakdown' => ['surplus' => $amount],
            'requested_at' => $requested ? now() : null,
        ]);
        if ($requested) {
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, (int) $deposit->request_id, 'Reembolso solicitado', $actor, 'Excedente $'.number_format($amount, 2, '.', ''));
        }

        return $refund;
    }

    /**
     * Proveedor que lleva el excedente de un abono y su reembolso. En un voucher compartido el excedente
     * no es de ningún proveedor: lo lleva uno solo para no duplicarlo. Si ya hay reembolso, el de ese
     * reembolso; si no, el primero (por orden) que ya pasó a Validación de pago, o el primero activo.
     */
    public function surplusOwnerId(PagoProveedorAbono $deposit): ?int
    {
        $refund = PagoProveedorReembolso::query()
            ->where('deposit_id', $deposit->id)
            ->where('origin', PagoProveedorReembolso::ORIGIN_SURPLUS)
            ->first();
        if ($refund && $refund->request_provider_id) {
            return (int) $refund->request_provider_id;
        }
        $deposit->loadMissing('aplicaciones.proveedor');
        $providers = $deposit->aplicaciones
            ->map(fn ($row) => $row->proveedor)
            ->filter(fn (?PagoProveedorSolicitudProveedor $provider) => $provider !== null && $provider->status !== PagoProveedorSolicitudProveedor::STATUS_CANCELLED)
            ->sortBy(fn (PagoProveedorSolicitudProveedor $provider) => [(int) $provider->position, (int) $provider->id])
            ->values();
        $owner = $providers->first(fn (PagoProveedorSolicitudProveedor $provider) => (int) $provider->stage >= PagoProveedorSolicitudProveedor::STAGE_VALIDATION)
            ?? $providers->first();

        return $owner ? (int) $owner->id : null;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function ensureExchange(PagoProveedorSolicitudProveedor $provider, array $actor): PagoProveedorReembolso
    {
        $existing = $this->findOpen($provider->id, PagoProveedorReembolso::ORIGIN_EXCHANGE);
        if ($existing) {
            return $existing;
        }
        $amount = 0.0;
        foreach ($provider->ejecuciones as $execution) {
            if ($execution->exchange_result === PagoProveedorEjecucion::RESULT_REFUND) {
                $amount += abs((float) $execution->exchange_difference_usd);
            }
        }
        $amount = round($amount, 2);

        return PagoProveedorReembolso::query()->create([
            'request_id' => $provider->request_id,
            'request_provider_id' => $provider->id,
            'origin' => PagoProveedorReembolso::ORIGIN_EXCHANGE,
            'status' => PagoProveedorReembolso::STATUS_CONSULT,
            'amount' => $amount,
            'breakdown' => ['difference_usd' => $amount],
        ]);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function ensurePaymentTwo(PagoProveedorSolicitudProveedor $provider, string $reason, array $actor): PagoProveedorReembolso
    {
        $existing = $this->findOpen($provider->id, PagoProveedorReembolso::ORIGIN_PAYMENT);
        if ($existing) {
            return $existing;
        }
        $parts = app(PagoProveedorConfirmacionService::class)->paymentTwoRefund($provider);
        $cargo = $parts['cargo'];
        $admin = $parts['admin'];
        $amount = $parts['total'];
        $refund = PagoProveedorReembolso::query()->create([
            'request_id' => $provider->request_id,
            'request_provider_id' => $provider->id,
            'origin' => PagoProveedorReembolso::ORIGIN_PAYMENT,
            'status' => PagoProveedorReembolso::STATUS_REQUESTED,
            'amount' => $amount,
            'breakdown' => ['cargo' => $cargo, 'admin' => $admin],
            'reason' => $reason,
            'requested_at' => now(),
        ]);
        $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, (int) $provider->request_id, 'Reembolso solicitado', $actor, 'Pago 2 $'.number_format($amount, 2, '.', ''));

        return $refund;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function request(int $requestId, int $providerId, array $actor): \App\Models\PagoProveedor\PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $providerId, $actor) {
            $refund = PagoProveedorReembolso::query()
                ->where('request_id', $requestId)
                ->where('request_provider_id', $providerId)
                ->where('status', PagoProveedorReembolso::STATUS_CONSULT)
                ->lockForUpdate()
                ->first();
            if (!$refund) {
                throw new PagoProveedorException('No hay un reembolso por consultar en este proveedor.', 404);
            }
            $refund->status = PagoProveedorReembolso::STATUS_REQUESTED;
            $refund->requested_at = now();
            // El motivo del rechazo anterior ya se atendió; el del pago 2 anulado sigue siendo el origen.
            if ($refund->origin !== PagoProveedorReembolso::ORIGIN_PAYMENT) {
                $refund->reason = null;
            }
            $refund->save();
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $requestId, 'Reembolso solicitado', $actor, ($refund->origin === PagoProveedorReembolso::ORIGIN_SURPLUS ? 'Excedente $' : 'Diferencia de cambio $').number_format((float) $refund->amount, 2, '.', ''));

            return app(PagoProveedorEtapa1Service::class)->detalle($requestId);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function approve(int $id, float $amount, string $paidOn, string $bank, ?string $operation, UploadedFile $photo, array $actor): PagoProveedorReembolso
    {
        $result = DB::transaction(function () use ($id, $amount, $paidOn, $bank, $operation, $photo, $actor) {
            $refund = $this->lockRequested($id);
            if ($amount <= 0) {
                throw new PagoProveedorException('El importe del voucher tiene que ser mayor que cero.');
            }
            $extension = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
            $path = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                $photo,
                'pago-proveedor/reembolsos',
                'reembolso-'.$refund->id.'-'.now()->format('YmdHis').'.'.$extension
            ));
            $refund->status = PagoProveedorReembolso::STATUS_APPROVED;
            $refund->approved_amount = round($amount, 2);
            $refund->paid_on = \Illuminate\Support\Carbon::parse($paidOn);
            $refund->bank = $bank;
            $refund->operation_number = $operation !== null && trim($operation) !== '' ? trim($operation) : null;
            $refund->voucher_path = $path;
            $refund->voucher_name = $photo->getClientOriginalName();
            $refund->decided_at = now();
            $refund->decided_by_user_id = $actor['id'];
            $refund->save();
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, (int) $refund->request_id, 'Reembolso aprobado', $actor, '$'.number_format($amount, 2, '.', ''));
            $this->refreshRequest((int) $refund->request_id);

            return $refund->load(['solicitud', 'proveedor']);
        });
        \App\Services\Verificacion\VerificacionContadoresBroadcaster::emit(['supplier_approvals']);

        return $result;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function reject(int $id, string $reason, array $actor): PagoProveedorReembolso
    {
        $result = DB::transaction(function () use ($id, $reason, $actor) {
            $refund = $this->lockRequested($id);
            $note = trim($reason);
            if ($note === '') {
                throw new PagoProveedorException('Indica el motivo del rechazo.');
            }
            // A rejected refund goes back to Sales, who consults the client again (functional mock).
            $refund->status = PagoProveedorReembolso::STATUS_CONSULT;
            $refund->requested_at = null;
            $refund->reason = $note;
            $refund->decided_at = now();
            $refund->decided_by_user_id = $actor['id'];
            $refund->save();
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, (int) $refund->request_id, 'Reembolso rechazado', $actor, $note);
            $this->refreshRequest((int) $refund->request_id);

            return $refund->load(['solicitud', 'proveedor']);
        });
        \App\Services\Verificacion\VerificacionContadoresBroadcaster::emit(['supplier_approvals']);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function explanationLines(PagoProveedorReembolso $refund): array
    {
        $parts = is_array($refund->breakdown) ? $refund->breakdown : [];
        $lines = [];
        if (isset($parts['cargo'])) {
            $lines[] = 'Carga del pago 2 $'.number_format((float) $parts['cargo'], 2, '.', '');
        }
        if (isset($parts['admin'])) {
            $lines[] = 'Administrativa del 2.° pago $'.number_format((float) $parts['admin'], 2, '.', '').'. La comisión bancaria no se devuelve.';
        }
        if (isset($parts['surplus'])) {
            $lines[] = 'Abono de más $'.number_format((float) $parts['surplus'], 2, '.', '');
        }
        if (isset($parts['difference_usd'])) {
            $lines[] = 'Diferencia de cambio a favor $'.number_format((float) $parts['difference_usd'], 2, '.', '');
        }
        $sum = round((float) ($parts['cargo'] ?? 0) + (float) ($parts['admin'] ?? 0) + (float) ($parts['surplus'] ?? 0) + (float) ($parts['difference_usd'] ?? 0), 2);
        if ($lines === [] || abs($sum - (float) $refund->amount) > 0.02) {
            return ['Total $'.number_format((float) $refund->amount, 2, '.', '')];
        }

        return $lines;
    }

    public function explain(PagoProveedorReembolso $refund): string
    {
        return implode(' · ', $this->explanationLines($refund));
    }

    public function isOpen(PagoProveedorReembolso $refund): bool
    {
        return in_array($refund->status, PagoProveedorReembolso::OPEN_STATUSES, true);
    }

    private function findOpen(int $providerId, string $origin): ?PagoProveedorReembolso
    {
        return PagoProveedorReembolso::query()
            ->where('request_provider_id', $providerId)
            ->where('origin', $origin)
            ->whereIn('status', PagoProveedorReembolso::OPEN_STATUSES)
            ->first();
    }

    private function lockRequested(int $id): PagoProveedorReembolso
    {
        $refund = PagoProveedorReembolso::query()->lockForUpdate()->find($id);
        if (!$refund || $refund->status !== PagoProveedorReembolso::STATUS_REQUESTED) {
            throw new PagoProveedorException('Este reembolso no está solicitado.', 404);
        }

        return $refund;
    }

    private function refreshRequest(int $requestId): void
    {
        $request = \App\Models\PagoProveedor\PagoProveedorSolicitud::query()->find($requestId);
        if ($request) {
            app(PagoProveedorConfirmacionService::class)->refresh($request);
        }
    }
}
