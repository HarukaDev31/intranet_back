<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PagoProveedorEjecucionService
{
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorHistorialService $history,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PagoProveedorSolicitudProveedor|PagoProveedorEjecucion>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $bucket = (string) ($filters['bucket'] ?? PagoProveedorEjecucion::BUCKET_PENDING);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $rows = $bucket === PagoProveedorEjecucion::BUCKET_PAID
            ? $this->paid($filters, $search)
            : $this->pending($search);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public function pendingCount(): int
    {
        return $this->pending('')->count();
    }

    public function noticeText(int $providerId, float $amount): string
    {
        $provider = PagoProveedorSolicitudProveedor::query()->with('solicitud')->find($providerId);
        if (!$provider) {
            throw new PagoProveedorException('El pago no existe.', 404);
        }

        return app(PagoProveedorMensajeService::class)->usdNoticeText($provider, $amount);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function confirmNotice(int $providerId, float $amount, array $actor): PagoProveedorSolicitudProveedor
    {
        $provider = PagoProveedorSolicitudProveedor::query()->with('solicitud')->find($providerId);
        if (!$provider) {
            throw new PagoProveedorException('El pago no existe.', 404);
        }
        $this->requireReady($provider);
        app(PagoProveedorMensajeService::class)->storeUsdNotice($provider, $amount, $actor);

        return $provider->load(['solicitud', 'datos', 'ejecuciones']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{count: int, cargo: float, charged_bank_fee: float, real_bank_fee: float, bank_difference: float, admin_fee: float, profit: float}
     */
    public function totals(array $filters): array
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $rows = $this->paid($filters, $search);
        $cargo = 0.0;
        $charged = 0.0;
        $real = 0.0;
        $admin = 0.0;
        foreach ($rows as $execution) {
            $cargo += (float) $execution->cargo_amount;
            $charged += (float) $execution->charged_bank_fee;
            $real += (float) $execution->real_bank_fee;
            $admin += (float) $execution->charged_admin_fee;
        }
        $difference = round($charged - $real, 2);

        return [
            'count' => $rows->count(),
            'cargo' => round($cargo, 2),
            'charged_bank_fee' => round($charged, 2),
            'real_bank_fee' => round($real, 2),
            'bank_difference' => $difference,
            'admin_fee' => round($admin, 2),
            'profit' => round($difference + $admin, 2),
        ];
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function register(int $providerId, float $realBankFee, UploadedFile $receipt, array $actor): PagoProveedorEjecucion
    {
        return DB::transaction(function () use ($providerId, $realBankFee, $receipt, $actor) {
            $provider = PagoProveedorSolicitudProveedor::query()
                ->with(['solicitud', 'datos', 'ejecuciones'])
                ->lockForUpdate()
                ->find($providerId);
            if (!$provider) {
                throw new PagoProveedorException('El pago no existe.', 404);
            }
            $this->requireReady($provider);
            if ($realBankFee < 0) {
                throw new PagoProveedorException('La comisión bancaria real no puede ser negativa.');
            }

            $quote = $this->amounts($provider);
            $difference = round($quote['charged_bank_fee'] - $realBankFee, 2);
            $extension = strtolower($receipt->getClientOriginalExtension() ?: 'pdf');
            $path = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                $receipt,
                'pago-proveedor/constancias',
                'constancia-'.$provider->id.'-'.now()->format('YmdHis').'.'.$extension
            ));

            $execution = PagoProveedorEjecucion::query()->create([
                'request_provider_id' => $provider->id,
                'installment' => (int) $provider->current_installment,
                'supplier_payment' => (int) $provider->current_payment,
                'cargo_amount' => $quote['cargo'],
                'charged_bank_fee' => $quote['charged_bank_fee'],
                'real_bank_fee' => round($realBankFee, 2),
                'charged_admin_fee' => $quote['charged_admin_fee'],
                'bank_difference' => $difference,
                'profit' => round($difference + $quote['charged_admin_fee'], 2),
                'receipt_path' => $path,
                'receipt_name' => $receipt->getClientOriginalName(),
                'executed_on' => now(PagoProveedorVigenciaService::ZONA)->toDateString(),
                'user_id' => $actor['id'],
            ]);

            $messages = app(PagoProveedorMensajeService::class);
            if (!$messages->noticeStored($provider)) {
                throw new PagoProveedorException('Primero envía el aviso al cliente por WhatsApp.');
            }
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Pago ejecutado',
                $actor,
                $provider->code_supplier.' · constancia '.$execution->receipt_name
            );

            $payments = $provider->summary_json['pagos_proveedor'] ?? [];
            $hasAnotherPart = is_array($payments) && count($payments) > (int) $provider->current_payment;
            if ($hasAnotherPart) {
                $provider->current_payment = (int) $provider->current_payment + 1;
                $provider->save();
            } else {
                $provider->stage = PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION;
                $provider->save();
                app(PagoProveedorConfirmacionService::class)->open($provider);
            }

            $execution->load(['proveedor.solicitud', 'proveedor.datos']);

            return $execution;
        });
    }

    /**
     * @return array{cargo: float, charged_bank_fee: float, charged_admin_fee: float}
     */
    public function amounts(PagoProveedorSolicitudProveedor $provider): array
    {
        $summary = is_array($provider->summary_json) ? $provider->summary_json : [];
        $installments = is_array($summary['cuotas'] ?? null) ? $summary['cuotas'] : [];
        $payments = is_array($summary['pagos_proveedor'] ?? null) ? $summary['pagos_proveedor'] : [];
        $installment = $installments[max(0, (int) $provider->current_installment - 1)] ?? [];
        $payment = $payments[max(0, (int) $provider->current_payment - 1)] ?? [];
        $cargo = isset($payment['carga']) ? (float) $payment['carga'] : (float) ($installment['carga'] ?? $provider->cargo_value_usd);
        $chargeFees = (int) $provider->current_payment <= PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT;
        $bank = $chargeFees ? (float) ($installment['bancaria'] ?? $provider->bank_fee_amount) : 0.0;
        $admin = $chargeFees ? (float) ($installment['administrativa'] ?? $provider->admin_fee_amount) : 0.0;

        return [
            'cargo' => round($cargo, 2),
            'charged_bank_fee' => round($bank, 2),
            'charged_admin_fee' => round($admin, 2),
        ];
    }

    public function bankReady(PagoProveedorSolicitudProveedor $provider): bool
    {
        $data = $provider->datos;
        if (!$data || $data->status !== PagoProveedorDatoProveedor::STATUS_RECEIVED) {
            return false;
        }

        foreach (['beneficiary_name', 'beneficiary_account', 'bank_name', 'bank_code'] as $field) {
            if (trim((string) $data->{$field}) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, PagoProveedorSolicitudProveedor>
     */
    private function pending(string $search): Collection
    {
        return PagoProveedorSolicitudProveedor::query()
            ->with(['solicitud', 'datos', 'ejecuciones'])
            ->where('currency', PagoProveedorCotizacionProveedor::CURRENCY_USD)
            ->where('payment_type', PagoProveedorCotizacionProveedor::PAYMENT_TT)
            ->where('stage', PagoProveedorSolicitudProveedor::STAGE_EXECUTION)
            ->where('approval_status', PagoProveedorSolicitudProveedor::APPROVAL_APPROVED)
            ->where('status', '!=', PagoProveedorSolicitudProveedor::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->get()
            ->filter(function (PagoProveedorSolicitudProveedor $provider) use ($search) {
                if (!$this->bankReady($provider) || $this->alreadyExecuted($provider)) {
                    return false;
                }

                return $this->matches($provider, $search);
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PagoProveedorEjecucion>
     */
    private function paid(array $filters, string $search): Collection
    {
        $query = PagoProveedorEjecucion::query()
            ->with(['proveedor.solicitud', 'proveedor.datos'])
            ->orderByDesc('executed_on')
            ->orderByDesc('id');

        if (!empty($filters['paid_from'])) {
            $query->whereDate('executed_on', '>=', $filters['paid_from']);
        }
        if (!empty($filters['paid_to'])) {
            $query->whereDate('executed_on', '<=', $filters['paid_to']);
        }

        return $query->get()->filter(function (PagoProveedorEjecucion $execution) use ($search) {
            $provider = $execution->proveedor;

            return $provider && $this->matches($provider, $search);
        })->values();
    }

    private function alreadyExecuted(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $provider->ejecuciones->contains(function (PagoProveedorEjecucion $execution) use ($provider) {
            return (int) $execution->installment === (int) $provider->current_installment
                && (int) $execution->supplier_payment === (int) $provider->current_payment;
        });
    }

    private function requireReady(PagoProveedorSolicitudProveedor $provider): void
    {
        if ($provider->currency !== PagoProveedorCotizacionProveedor::CURRENCY_USD
            || $provider->payment_type !== PagoProveedorCotizacionProveedor::PAYMENT_TT) {
            throw new PagoProveedorException('Este pago no se ejecuta en dólares.');
        }
        if ((int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_EXECUTION
            || $provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_APPROVED) {
            throw new PagoProveedorException('Este pago no está listo para transferir.');
        }
        if (!$this->bankReady($provider)) {
            throw new PagoProveedorException('Faltan los datos bancarios del proveedor.');
        }
        if ($this->alreadyExecuted($provider)) {
            throw new PagoProveedorException('Este pago ya fue registrado.');
        }
    }

    private function matches(PagoProveedorSolicitudProveedor $provider, string $search): bool
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
}
