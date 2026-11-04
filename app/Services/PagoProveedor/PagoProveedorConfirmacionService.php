<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorReembolso;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PagoProveedorConfirmacionService
{
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorConfiguracionService $configuration,
        private readonly PagoProveedorHistorialService $history,
    ) {
    }

    public function refresh(PagoProveedorSolicitud $solicitud): void
    {
        $solicitud->loadMissing(['proveedores.ejecuciones', 'proveedores.aplicaciones.abono', 'proveedores.exchangeDeposits', 'proveedores.refunds']);
        foreach ($solicitud->proveedores as $provider) {
            if ((int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION) {
                continue;
            }
            if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED) {
                continue;
            }
            $this->armDeadline($provider);
            $this->tryComplete($provider);
        }
    }

    public function open(PagoProveedorSolicitudProveedor $provider): void
    {
        $provider->loadMissing(['ejecuciones', 'aplicaciones.abono', 'solicitud', 'exchangeDeposits', 'refunds']);
        $this->armDeadline($provider);
        $this->tryComplete($provider);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function decideSecondInstallment(int $requestId, int $providerId, string $choice, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $providerId, $choice, $actor) {
            $provider = $this->find($requestId, $providerId);
            if (!$this->awaitsInstallmentChoice($provider)) {
                throw new PagoProveedorException('Esta cuota 2 ya no está por decidir.');
            }
            if (!in_array($choice, [PagoProveedorSolicitudProveedor::CHOICE_PROCEED, PagoProveedorSolicitudProveedor::CHOICE_CANCEL], true)) {
                throw new PagoProveedorException('La decisión de la cuota 2 no es válida.');
            }

            $provider->second_installment_choice = $choice;
            $provider->decision_due_at = null;
            if ($choice === PagoProveedorSolicitudProveedor::CHOICE_PROCEED) {
                $provider->current_installment = PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT + 1;
                $provider->stage = PagoProveedorSolicitudProveedor::STAGE_DATA;
                $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
                $provider->approval_status = null;
                $provider->approval_mode = null;
                $provider->approved_at = null;
                $provider->approved_by_user_id = null;
                $provider->exchange_rate_pending = true;
                $provider->save();
                $this->history->registrar(
                    PagoProveedorHistorial::ENTITY_REQUEST,
                    $requestId,
                    'Cuota 2 se realizará',
                    $actor,
                    'Vuelve a etapa 1. Hay que confirmar el tipo de cambio del día.'
                );
            } else {
                $provider->status = PagoProveedorSolicitudProveedor::STATUS_COMPLETED;
                $provider->save();
                $this->history->registrar(
                    PagoProveedorHistorial::ENTITY_REQUEST,
                    $requestId,
                    'Cuota 2 no se realizará',
                    $actor,
                    'Se cierra sin cobrar la comisión ni el segundo abono.'
                );
            }

            return $this->reload($requestId);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function confirmExchangeRate(int $requestId, float $rate, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $rate, $actor) {
            $solicitud = PagoProveedorSolicitud::query()->with('proveedores')->lockForUpdate()->find($requestId);
            if (!$solicitud) {
                throw new PagoProveedorException('La solicitud no existe.', 404);
            }
            if ($rate <= 0) {
                throw new PagoProveedorException('El tipo de cambio debe ser mayor a 0.');
            }
            $pending = $solicitud->proveedores->contains(fn (PagoProveedorSolicitudProveedor $provider) => $provider->exchange_rate_pending);
            if (!$pending) {
                throw new PagoProveedorException('No hay una cuota 2 esperando tipo de cambio.');
            }
            $solicitud->exchange_rate = round($rate, 4);
            $solicitud->exchange_rate_confirmed = true;
            $solicitud->save();
            foreach ($solicitud->proveedores as $provider) {
                if (!$provider->exchange_rate_pending) {
                    continue;
                }
                $provider->exchange_rate_pending = false;
                $provider->save();
            }
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $requestId,
                'Tipo de cambio de la cuota 2',
                $actor,
                (string) $solicitud->exchange_rate
            );

            return $this->reload($requestId);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function decideSecondPayment(int $requestId, int $providerId, string $choice, ?string $reason, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $providerId, $choice, $reason, $actor) {
            $provider = $this->find($requestId, $providerId);
            if (!$this->awaitsPaymentChoice($provider)) {
                throw new PagoProveedorException('Este proveedor no está esperando la decisión del pago 2.');
            }
            if ($choice === PagoProveedorSolicitudProveedor::CHOICE_PROCEED) {
                $provider->second_payment_choice = $choice;
                $provider->decision_due_at = null;
                $provider->current_payment = 2;
                $provider->stage = PagoProveedorSolicitudProveedor::STAGE_EXECUTION;
                $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
                $provider->save();
                $this->history->registrar(
                    PagoProveedorHistorial::ENTITY_REQUEST,
                    $requestId,
                    'Pago 2 se realizará',
                    $actor,
                    'Vuelve a ejecución para que China registre el segundo pago.'
                );

                return $this->reload($requestId);
            }

            $note = trim((string) $reason);
            if ($note === '') {
                throw new PagoProveedorException('Indica el motivo para no realizar el pago 2.');
            }
            $provider->second_payment_choice = $choice;
            $provider->decision_due_at = null;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $requestId,
                $choice === PagoProveedorSolicitudProveedor::PAYMENT_REFUND ? 'Pago 2 anulado con reembolso' : 'Pago 2 anulado',
                $actor,
                $note
            );
            if ($choice === PagoProveedorSolicitudProveedor::PAYMENT_REFUND) {
                app(PagoProveedorReembolsoService::class)->ensurePaymentTwo($provider, $note, $actor);
            } else {
                $provider->load(['exchangeDeposits', 'refunds']);
                $this->tryComplete($provider);
            }

            return $this->reload($requestId);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{solicitud: PagoProveedorSolicitud, pdf: array{filename: string, base64: string}}
     */
    public function sendBalance(int $requestId, int $providerId, array $actor): array
    {
        return DB::transaction(function () use ($requestId, $providerId, $actor) {
            $provider = PagoProveedorSolicitudProveedor::query()
                ->with(['solicitud', 'ejecuciones'])
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->find($providerId);
            if (!$provider) {
                throw new PagoProveedorException('El proveedor no existe.', 404);
            }
            if (!in_array($provider->exchange_result, [PagoProveedorEjecucion::RESULT_COLLECT, PagoProveedorEjecucion::RESULT_REFUND], true)) {
                throw new PagoProveedorException('Este pago no tiene diferencia para avisarle al cliente.');
            }
            app(PagoProveedorMensajeService::class)->storeBalance($provider, $actor);
            $pdf = app(PagoProveedorPdfService::class)->balance($provider, $actor);

            return [
                'solicitud' => $provider->solicitud,
                'pdf' => $pdf,
            ];
        });
    }

    /**
     * @return array{filename: string, base64: string}
     */
    public function downloadBalance(int $requestId, int $providerId, array $actor): array
    {
        $provider = $this->find($requestId, $providerId);
        $provider->loadMissing('ejecuciones');

        return app(PagoProveedorPdfService::class)->balance($provider, $actor);
    }

    /**
     * @return array{cargo: float, admin: float, total: float}
     */
    public function paymentTwoRefund(PagoProveedorSolicitudProveedor $provider): array
    {
        $summary = is_array($provider->summary_json) ? $provider->summary_json : [];
        $payments = is_array($summary['pagos_proveedor'] ?? null) ? $summary['pagos_proveedor'] : [];
        $second = $payments[1] ?? [];
        $cargo = round((float) ($second['carga'] ?? 0), 2);
        $admin = round((float) ($second['administrativa'] ?? 0), 2);

        return [
            'cargo' => $cargo,
            'admin' => $admin,
            'total' => round($cargo + $admin, 2),
        ];
    }

    public function registerExchangeVoucher(
        int $requestId,
        int $providerId,
        float $amount,
        string $paidOn,
        string $bank,
        string $operation,
        UploadedFile $voucher,
        array $actor
    ): PagoProveedorSolicitud {
        return DB::transaction(function () use ($requestId, $providerId, $amount, $paidOn, $bank, $operation, $voucher, $actor) {
            $provider = $this->find($requestId, $providerId);
            if ($provider->exchange_result !== PagoProveedorEjecucion::RESULT_COLLECT) {
                throw new PagoProveedorException('Este proveedor no tiene una diferencia de cambio por cobrar.');
            }
            if ($amount <= 0) {
                throw new PagoProveedorException('El importe de la diferencia tiene que ser mayor que cero.');
            }
            $extension = strtolower($voucher->getClientOriginalExtension() ?: 'pdf');
            $path = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                $voucher,
                'pago-proveedor/vouchers',
                'diferencia-'.$provider->id.'-'.now()->format('YmdHis').'.'.$extension
            ));
            PagoProveedorAbono::query()->create([
                'request_id' => $requestId,
                'purpose' => PagoProveedorAbono::PURPOSE_EXCHANGE,
                'exchange_provider_id' => $provider->id,
                'amount' => round($amount, 2),
                'paid_on' => $paidOn,
                'operation_number' => trim($operation) !== '' ? trim($operation) : 'sin-numero',
                'bank' => $bank,
                'interbank' => false,
                'voucher_path' => $path,
                'voucher_name' => $voucher->getClientOriginalName(),
                'status' => PagoProveedorAbono::STATUS_IN_VALIDATION,
                'surplus' => 0,
                'repeated_operation' => false,
                'user_id' => $actor['id'],
            ]);
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $requestId,
                'Voucher de diferencia de cambio',
                $actor,
                $provider->code_supplier.' · $'.number_format($amount, 2, '.', '')
            );

            return $this->reload($requestId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PagoProveedorSolicitudProveedor $provider): array
    {
        $provider->loadMissing(['ejecuciones', 'aplicaciones.abono', 'exchangeDeposits', 'refunds']);
        $items = $this->items($provider);
        $blocking = array_values(array_filter($items, fn (array $item) => $item['blocks'] && !$item['done']));
        $requirements = array_values(array_filter($items, fn (array $item) => $item['blocks']));
        $done = count(array_filter($requirements, fn (array $item) => $item['done']));
        $visible = (int) $provider->stage >= PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION
            || $provider->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED
            || $provider->second_installment_choice !== null
            || $provider->second_payment_choice !== null;

        return [
            'verification' => $this->verification($provider),
            'executions' => $provider->ejecuciones->map(fn (PagoProveedorEjecucion $execution) => [
                'id' => $execution->id,
                'cargo_amount' => (float) $execution->cargo_amount,
                'executed_on' => optional($execution->executed_on)->toDateString(),
                'receipt_name' => $execution->receipt_name,
                'receipt_path' => $execution->receipt_path,
            ])->values()->all(),
            'confirmation' => [
                'visible' => $visible,
                'completed' => $provider->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED,
                'items' => $items,
                'done_count' => $done,
                'total' => count($requirements),
                'missing' => array_map(fn (array $item) => $item['label'], $blocking),
                'can_decide_installment' => $this->awaitsInstallmentChoice($provider),
                'can_decide_payment' => $this->awaitsPaymentChoice($provider),
                'second_installment_choice' => $provider->second_installment_choice,
                'second_payment_choice' => $provider->second_payment_choice,
                'can_register_exchange' => $provider->exchange_result === PagoProveedorEjecucion::RESULT_COLLECT && !$this->exchangeCovered($provider),
                'can_send_balance' => in_array($provider->exchange_result, [PagoProveedorEjecucion::RESULT_COLLECT, PagoProveedorEjecucion::RESULT_REFUND], true)
                    && !app(PagoProveedorMensajeService::class)->balanceStored($provider),
                'can_download_balance' => $provider->ejecuciones->isNotEmpty(),
                'can_request_refund' => $provider->refunds->contains(fn (PagoProveedorReembolso $refund) => $refund->status === PagoProveedorReembolso::STATUS_CONSULT),
                'exchange_due_usd' => $this->exchangeDue($provider),
                'payment_two_refund' => $this->awaitsPaymentChoice($provider) ? $this->paymentTwoRefund($provider) : null,
                'decision_due_at' => optional($provider->decision_due_at)->toIso8601String(),
                'decision_overdue' => $provider->decision_due_at ? $provider->decision_due_at->isPast() : false,
                'decision_due_text' => $provider->decision_due_at
                    ? ($provider->decision_due_at->isPast()
                        ? 'El plazo ya venció. Igual puedes elegir.'
                        : PagoProveedorPlazo::remainingText($provider->decision_due_at))
                    : null,
                'pending_refund' => $this->pendingRefundSummary($provider),
                'exchange_rate_pending' => (bool) $provider->exchange_rate_pending,
            ],
        ];
    }

    private function tryComplete(PagoProveedorSolicitudProveedor $provider): void
    {
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED) {
            return;
        }
        if ((int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION) {
            return;
        }
        $blocking = array_filter($this->items($provider), fn (array $item) => $item['blocks'] && !$item['done']);
        if ($blocking !== []) {
            return;
        }
        $provider->status = PagoProveedorSolicitudProveedor::STATUS_COMPLETED;
        $provider->save();
        $this->history->registrar(
            PagoProveedorHistorial::ENTITY_REQUEST,
            (int) $provider->request_id,
            'Completado',
            $this->systemActor(),
            'Automático: no quedaba nada pendiente.'
        );
    }

    private function armDeadline(PagoProveedorSolicitudProveedor $provider): void
    {
        $awaits = $this->awaitsInstallmentChoice($provider) || $this->awaitsPaymentChoice($provider);
        if (!$awaits || $provider->decision_due_at) {
            return;
        }
        $hours = max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_DECISION_HOURS, 72));
        $provider->decision_due_at = now()->addHours($hours);
        $provider->save();
    }

    /**
     * @return array<int, array{key: string, label: string, done: bool, blocks: bool, owner: string}>
     */
    private function items(PagoProveedorSolicitudProveedor $provider): array
    {
        $paid = $provider->ejecuciones->isNotEmpty();
        $items = [[
            'key' => 'payment',
            'label' => 'Pago realizado',
            'done' => $paid,
            'blocks' => true,
            'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
        ]];
        if ($provider->currency === PagoProveedorCotizacionProveedor::CURRENCY_USD) {
            $items[] = [
                'key' => 'receipt',
                'label' => 'Constancia registrada',
                'done' => $paid,
                'blocks' => true,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
            ];
            $items[] = [
                'key' => 'client_notice',
                'label' => 'Aviso al cliente por WhatsApp',
                'done' => app(PagoProveedorMensajeService::class)->noticeStored($provider),
                'blocks' => true,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
            ];
        }
        if ($provider->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
            $items[] = [
                'key' => 'client_notice',
                'label' => 'Pago informado al cliente',
                'done' => app(PagoProveedorMensajeService::class)->noticeStored($provider),
                'blocks' => false,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
            ];
            $exchange = $this->exchangeItem($provider);
            if ($exchange) {
                $items[] = $exchange;
            }
        }
        if ($this->needsInstallmentChoice($provider) || $provider->second_installment_choice !== null) {
            $items[] = [
                'key' => 'installment',
                'label' => 'Decisión de la cuota 2',
                'done' => $provider->second_installment_choice !== null,
                'blocks' => $provider->second_installment_choice === null,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_SALES,
            ];
        }
        if ($this->needsPaymentChoice($provider) || $provider->second_payment_choice !== null) {
            $items[] = [
                'key' => 'supplier_payment',
                'label' => 'Decisión del pago 2',
                'done' => $provider->second_payment_choice !== null,
                'blocks' => $provider->second_payment_choice === null,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_SALES,
            ];
        }
        if ($provider->second_payment_choice === PagoProveedorSolicitudProveedor::PAYMENT_REFUND) {
            $paymentRefund = $provider->refunds->firstWhere('origin', PagoProveedorReembolso::ORIGIN_PAYMENT);
            $items[] = [
                'key' => 'payment_refund',
                'label' => 'Reembolso del pago 2',
                'done' => $paymentRefund && !$this->refundOpen($paymentRefund),
                'blocks' => true,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
            ];
        }
        $openRefund = $this->openRefund($provider);
        if ($openRefund && $provider->second_payment_choice !== PagoProveedorSolicitudProveedor::PAYMENT_REFUND) {
            $items[] = [
                'key' => 'refund',
                'label' => 'Reembolso pendiente',
                'done' => false,
                'blocks' => true,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
            ];
        } elseif ($this->hasPendingSurplusRefund($provider)) {
            $items[] = [
                'key' => 'refund',
                'label' => 'Reembolso pendiente',
                'done' => false,
                'blocks' => true,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_OTHER,
            ];
        }
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED) {
            $items[] = [
                'key' => 'observed',
                'label' => 'Observación abierta',
                'done' => false,
                'blocks' => true,
                'owner' => PagoProveedorSolicitudProveedor::OWNER_SALES,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function verification(PagoProveedorSolicitudProveedor $provider): array
    {
        $deposits = [];
        foreach ($provider->aplicaciones as $application) {
            $deposit = $application->abono;
            if (!$deposit || $deposit->status === PagoProveedorAbono::STATUS_CANCELLED) {
                continue;
            }
            $deposits[] = [
                'id' => $deposit->id,
                'amount' => (float) ($deposit->bank_amount ?? $deposit->amount),
                'status' => $deposit->status,
                'status_label' => match ($deposit->status) {
                    PagoProveedorAbono::STATUS_VALIDATED => 'Validado',
                    PagoProveedorAbono::STATUS_OBSERVED => 'Observado',
                    default => 'Por validar',
                },
                'paid_on' => optional($deposit->paid_on)->toDateString(),
                'operation_number' => $deposit->operation_number,
            ];
        }

        return [
            'deposits' => $deposits,
            'approval_status' => $provider->approval_status,
            'approval_label' => match ($provider->approval_status) {
                PagoProveedorSolicitudProveedor::APPROVAL_APPROVED => $provider->approval_mode === PagoProveedorSolicitudProveedor::APPROVAL_AUTOMATIC
                    ? 'Aprobación automática'
                    : 'Aprobado por Administración',
                PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT => 'Por cobrar',
                PagoProveedorSolicitudProveedor::APPROVAL_PENDING => 'Pendiente de aprobación',
                default => (int) $provider->stage < PagoProveedorSolicitudProveedor::STAGE_VALIDATION ? 'Aún no llega' : 'En validación',
            },
        ];
    }

    private function hasPendingRefund(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $this->openRefund($provider) !== null || $this->hasPendingSurplusRefund($provider);
    }

    private function openRefund(PagoProveedorSolicitudProveedor $provider): ?PagoProveedorReembolso
    {
        $provider->loadMissing('refunds');

        return $provider->refunds->first(fn (PagoProveedorReembolso $refund) => $this->refundOpen($refund));
    }

    private function hasPendingSurplusRefund(PagoProveedorSolicitudProveedor $provider): bool
    {
        $provider->loadMissing('refunds');

        return $provider->aplicaciones->contains(function ($application) use ($provider) {
            $deposit = $application->abono;
            if (!$deposit || $deposit->status === PagoProveedorAbono::STATUS_CANCELLED || $deposit->surplus_decision !== PagoProveedorAbono::EXCESS_REFUND) {
                return false;
            }

            return !$provider->refunds->contains(fn (PagoProveedorReembolso $refund) => (int) $refund->deposit_id === (int) $deposit->id && !$this->refundOpen($refund));
        });
    }

    /**
     * @return array{amount: float, status: string, status_label: string, origin_label: string, blocks_close: bool}|null
     */
    private function pendingRefundSummary(PagoProveedorSolicitudProveedor $provider): ?array
    {
        $refund = $this->openRefund($provider);
        if (!$refund) {
            if (!$this->hasPendingSurplusRefund($provider)) {
                return null;
            }
            $amount = 0.0;
            foreach ($provider->aplicaciones as $application) {
                $deposit = $application->abono;
                if ($deposit && $deposit->surplus_decision === PagoProveedorAbono::EXCESS_REFUND) {
                    $amount += (float) $deposit->surplus;
                }
            }

            return [
                'amount' => round($amount, 2),
                'status' => PagoProveedorReembolso::STATUS_REQUESTED,
                'status_label' => 'Solicitado',
                'origin_label' => 'Excedente del cliente',
                'blocks_close' => true,
            ];
        }

        $originLabel = match ($refund->origin) {
            PagoProveedorReembolso::ORIGIN_PAYMENT => 'Anulación del pago 2',
            PagoProveedorReembolso::ORIGIN_EXCHANGE => 'Diferencia de cambio',
            default => 'Excedente del cliente',
        };
        $statusLabel = match ($refund->status) {
            PagoProveedorReembolso::STATUS_CONSULT => 'Por consultar',
            PagoProveedorReembolso::STATUS_REQUESTED => 'Solicitado',
            default => $refund->status,
        };

        return [
            'amount' => round((float) $refund->amount, 2),
            'status' => $refund->status,
            'status_label' => $statusLabel,
            'origin_label' => $originLabel,
            'blocks_close' => true,
        ];
    }

    private function refundOpen(PagoProveedorReembolso $refund): bool
    {
        return in_array($refund->status, PagoProveedorReembolso::OPEN_STATUSES, true);
    }

    /**
     * @return array{key: string, label: string, done: bool, blocks: bool, owner: string}|null
     */
    private function exchangeItem(PagoProveedorSolicitudProveedor $provider): ?array
    {
        $result = $provider->exchange_result;
        if (!in_array($result, [PagoProveedorEjecucion::RESULT_WITHIN, PagoProveedorEjecucion::RESULT_COLLECT, PagoProveedorEjecucion::RESULT_REFUND], true)) {
            return null;
        }
        $covered = $result === PagoProveedorEjecucion::RESULT_COLLECT && $this->exchangeCovered($provider);
        $exchangeRefund = $provider->refunds->firstWhere('origin', PagoProveedorReembolso::ORIGIN_EXCHANGE);
        $refundSettled = $exchangeRefund && !$this->refundOpen($exchangeRefund);
        $label = match ($result) {
            PagoProveedorEjecucion::RESULT_COLLECT => $covered ? 'Diferencia de cambio cobrada' : 'Diferencia de cambio por cobrar',
            PagoProveedorEjecucion::RESULT_REFUND => 'Diferencia de cambio a favor del cliente',
            default => 'Diferencia de cambio en tolerancia',
        };

        return [
            'key' => 'exchange',
            'label' => $label,
            'done' => $result === PagoProveedorEjecucion::RESULT_WITHIN || $covered || $refundSettled,
            'blocks' => $result !== PagoProveedorEjecucion::RESULT_WITHIN,
            'owner' => $result === PagoProveedorEjecucion::RESULT_COLLECT
                ? PagoProveedorSolicitudProveedor::OWNER_SALES
                : PagoProveedorSolicitudProveedor::OWNER_OTHER,
        ];
    }

    private function exchangeDue(PagoProveedorSolicitudProveedor $provider): float
    {
        $due = 0.0;
        foreach ($provider->ejecuciones as $execution) {
            if ($execution->exchange_result === PagoProveedorEjecucion::RESULT_COLLECT) {
                $due += abs((float) $execution->exchange_difference_usd);
            }
        }

        return round($due, 2);
    }

    private function exchangeCovered(PagoProveedorSolicitudProveedor $provider): bool
    {
        $due = $this->exchangeDue($provider);
        if ($due <= PagoProveedorSolicitudProveedor::MONEY_EPSILON) {
            return false;
        }
        $covered = 0.0;
        foreach ($provider->exchangeDeposits as $deposit) {
            if ($deposit->purpose !== PagoProveedorAbono::PURPOSE_EXCHANGE || $deposit->status !== PagoProveedorAbono::STATUS_VALIDATED) {
                continue;
            }
            $covered += (float) ($deposit->bank_amount ?? $deposit->amount);
        }
        $tolerance = (float) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_TOLERANCE, 1);

        return $covered + $tolerance + PagoProveedorSolicitudProveedor::MONEY_EPSILON >= $due;
    }

    private function awaitsPaymentChoice(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $this->needsPaymentChoice($provider)
            && (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION
            && $provider->status !== PagoProveedorSolicitudProveedor::STATUS_COMPLETED
            && $provider->second_payment_choice === null;
    }

    private function needsPaymentChoice(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $provider->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB
            && $provider->supplier_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $provider->current_payment === PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT;
    }

    private function awaitsInstallmentChoice(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $this->needsInstallmentChoice($provider)
            && (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION
            && $provider->status !== PagoProveedorSolicitudProveedor::STATUS_COMPLETED
            && $provider->second_installment_choice === null;
    }

    private function needsInstallmentChoice(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $provider->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT
            && (int) $provider->current_installment === PagoProveedorSolicitudProveedor::FIRST_INSTALLMENT;
    }

    private function find(int $requestId, int $providerId): PagoProveedorSolicitudProveedor
    {
        $provider = PagoProveedorSolicitudProveedor::query()
            ->with(['ejecuciones', 'aplicaciones.abono'])
            ->where('request_id', $requestId)
            ->lockForUpdate()
            ->find($providerId);
        if (!$provider) {
            throw new PagoProveedorException('El proveedor no pertenece a esta solicitud.', 404);
        }

        return $provider;
    }

    private function reload(int $requestId): PagoProveedorSolicitud
    {
        $solicitud = PagoProveedorSolicitud::query()
            ->with(['proveedores.datos', 'proveedores.consolidatedProvider', 'proveedores.aplicaciones.abono', 'proveedores.ejecuciones', 'proveedores.exchangeDeposits', 'proveedores.refunds', 'abonos.aplicaciones', 'cotizacion'])
            ->find($requestId);
        if (!$solicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $solicitud;
    }

    /**
     * @return array{id: null, nombre: string, area: string}
     */
    private function systemActor(): array
    {
        return [
            'id' => null,
            'nombre' => 'Sistema',
            'area' => PagoProveedorHistorial::AREA_SYSTEM,
        ];
    }
}
