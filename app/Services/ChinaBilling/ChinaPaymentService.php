<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaPayment;
use App\Models\ChinaBilling\ChinaPaymentAllocation;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo del cobro: registrado (por confirmar) → confirmado (monto recibido) → en caja (RMB).
 */
class ChinaPaymentService
{
    use UsesObjectStorage;

    /**
     * @param  array<string, mixed>  $data  organization_id, paid_on, amount_sent, allocations[{operation_id, amount}]
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function register(array $data, ?UploadedFile $receipt, array $actor, string $registeredBy): ChinaPayment
    {
        $organizationId = (int) $data['organization_id'];
        if (!in_array($organizationId, $actor['organization_ids'], true)) {
            throw new ChinaBillingException('No tienes acceso a ese cliente.', 403);
        }
        if ($registeredBy === ChinaPayment::BY_PARTNER && !$receipt) {
            throw new ChinaBillingException('Adjunta el comprobante del pago.');
        }

        return DB::transaction(function () use ($data, $receipt, $actor, $organizationId, $registeredBy) {
            $payment = ChinaPayment::query()->create([
                'code' => $this->nextCode(),
                'organization_id' => $organizationId,
                'paid_on' => $data['paid_on'],
                'amount_sent' => $data['amount_sent'],
                'registered_by' => $registeredBy,
                'registered_by_user_id' => $actor['id'],
            ]);
            $this->syncAllocations($payment, $data['allocations'] ?? []);
            $this->storeReceipt($payment, $receipt);

            return $payment;
        });
    }

    /**
     * Solo mientras China no confirmó el ingreso.
     *
     * @param  array<string, mixed>  $data
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function update(int $id, array $data, ?UploadedFile $receipt, array $actor): ChinaPayment
    {
        return DB::transaction(function () use ($id, $data, $receipt, $actor) {
            $payment = $this->find($id, $actor, true);
            $this->requireStatus($payment, ChinaBillingCalculator::STATUS_TO_CONFIRM, 'Solo se puede editar un pago por confirmar.');

            $payment->update(['paid_on' => $data['paid_on'], 'amount_sent' => $data['amount_sent']]);
            $this->syncAllocations($payment, $data['allocations'] ?? []);
            $this->storeReceipt($payment, $receipt);

            return $payment;
        });
    }

    /**
     * Paso 2: China ingresa lo realmente recibido. Si llegó menos de lo asignado elige en qué operación cae el faltante;
     * si llegó más, el excedente queda como saldo a favor.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function confirm(int $id, float $amountReceived, ?int $shortfallOperationId, array $actor): ChinaPayment
    {
        return DB::transaction(function () use ($id, $amountReceived, $shortfallOperationId, $actor) {
            $payment = $this->find($id, $actor, true);
            $this->requireStatus($payment, ChinaBillingCalculator::STATUS_TO_CONFIRM, 'Este pago ya fue confirmado.');
            if ($amountReceived <= 0) {
                throw new ChinaBillingException('Indica el monto recibido.');
            }

            $allocated = ChinaBillingCalculator::allocatedTotal($payment);
            $shortfall = ChinaBillingCalculator::round(max(0, $allocated - $amountReceived));
            if ($shortfall > 0) {
                $valid = $payment->allocations->first(
                    fn (ChinaPaymentAllocation $a) => (int) $a->operation_id === (int) $shortfallOperationId && (float) $a->amount >= $shortfall
                );
                if (!$valid) {
                    throw new ChinaBillingException('Elige la operación donde se carga la diferencia de $'.number_format($shortfall, 2).'.');
                }
            }

            $payment->update([
                'amount_received' => $amountReceived,
                'confirmed_at' => now(),
                'confirmed_by' => $actor['id'],
                'shortfall_operation_id' => $shortfall > 0 ? $shortfallOperationId : null,
            ]);

            return $payment;
        });
    }

    /**
     * Vuelve a "por confirmar" mientras no se haya usado el saldo a favor que generó.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function unconfirm(int $id, array $actor): ChinaPayment
    {
        $payment = $this->find($id, $actor, true);
        $this->requireStatus($payment, ChinaBillingCalculator::STATUS_CONFIRMED, 'Solo se puede deshacer un pago confirmado que no esté en caja.');
        if ($payment->creditApplications->isNotEmpty()) {
            throw new ChinaBillingException('Ya se usó el saldo a favor de este pago: deshaz primero esa aplicación.');
        }

        $payment->update(['amount_received' => null, 'confirmed_at' => null, 'confirmed_by' => null, 'shortfall_operation_id' => null]);

        return $payment;
    }

    /**
     * Paso 3: cambio a RMB. RMB = recibido × tipo de cambio.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function cash(int $id, float $exchangeRate, ?UploadedFile $voucher, array $actor): ChinaPayment
    {
        $payment = $this->find($id, $actor, true);
        $this->requireStatus($payment, ChinaBillingCalculator::STATUS_CONFIRMED, 'Confirma el ingreso antes de pasarlo a RMB.');
        if ($exchangeRate <= 0) {
            throw new ChinaBillingException('Indica el tipo de cambio.');
        }

        $payment->update([
            'exchange_rate' => $exchangeRate,
            'yuan_amount' => ChinaBillingCalculator::round((float) $payment->amount_received * $exchangeRate),
            'cashed_at' => now(),
            'cashed_by' => $actor['id'],
        ]);
        if ($voucher) {
            $extension = strtolower($voucher->getClientOriginalExtension() ?: 'pdf');
            $payment->update([
                'cash_voucher_path' => $this->storageFinalizeCdnPath(
                    $this->storageStoreUpload($voucher, 'china-billing/cambios', 'cambio-'.$payment->id.'-'.now()->format('YmdHis').'.'.$extension)
                ),
                'cash_voucher_name' => $voucher->getClientOriginalName(),
            ]);
        }

        return $payment;
    }

    /**
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function uncash(int $id, array $actor): ChinaPayment
    {
        $payment = $this->find($id, $actor, true);
        $this->requireStatus($payment, ChinaBillingCalculator::STATUS_IN_CASH, 'Este pago no está en caja.');

        $payment->update([
            'exchange_rate' => null, 'yuan_amount' => null, 'cashed_at' => null, 'cashed_by' => null,
            'cash_voucher_path' => null, 'cash_voucher_name' => null,
        ]);

        return $payment;
    }

    /**
     * China elimina siempre; el socio solo un pago propio por confirmar.
     * Las asignaciones y el saldo a favor aplicado desde este pago se borran en cascada.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function delete(int $id, array $actor, bool $partnerOnly = false): void
    {
        $payment = $this->find($id, $actor, false);
        if ($partnerOnly) {
            $this->requireStatus($payment, ChinaBillingCalculator::STATUS_TO_CONFIRM, 'Solo se puede anular un pago por confirmar.');
        }
        $payment->delete();
    }

    /**
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function find(int $id, array $actor, bool $withRelations): ChinaPayment
    {
        $query = ChinaPayment::query()->whereIn('organization_id', $actor['organization_ids']);
        if ($withRelations) {
            $query->with(['allocations', 'creditApplications'])->lockForUpdate();
        }
        $payment = $query->find($id);
        if (!$payment) {
            throw new ChinaBillingException('El pago no existe o no tienes acceso.', 404);
        }

        return $payment;
    }

    /**
     * Cada monto ≤ pendiente de su operación (sin contar este mismo pago); el total no supera lo enviado.
     *
     * @param  array<int, array{operation_id: int, amount: float}>  $allocations
     */
    private function syncAllocations(ChinaPayment $payment, array $allocations): void
    {
        $payment->allocations()->delete();
        $total = 0.0;

        foreach ($allocations as $row) {
            $amount = (float) $row['amount'];
            if ($amount <= 0) {
                continue;
            }
            $operation = ChinaOperation::query()
                ->with(['costs', 'allocations.payment', 'creditApplications'])
                ->where('organization_id', $payment->organization_id)
                ->find((int) $row['operation_id']);
            if (!$operation) {
                throw new ChinaBillingException('Una de las operaciones no pertenece a este cliente.');
            }
            $metrics = ChinaBillingCalculator::operationMetrics($operation);
            if ($metrics['transferred'] || $amount > $metrics['pending'] + 0.001) {
                throw new ChinaBillingException('El monto de '.$operation->code.' supera su saldo pendiente de $'.number_format($metrics['pending'], 2).'.');
            }
            $payment->allocations()->create(['operation_id' => $operation->id, 'amount' => $amount]);
            $total += $amount;
        }

        if ($total > (float) $payment->amount_sent + 0.001) {
            throw new ChinaBillingException('Lo asignado supera el monto enviado.');
        }
        $payment->unsetRelation('allocations');
    }

    private function requireStatus(ChinaPayment $payment, string $status, string $message): void
    {
        if (ChinaBillingCalculator::paymentStatus($payment) !== $status) {
            throw new ChinaBillingException($message);
        }
    }

    private function storeReceipt(ChinaPayment $payment, ?UploadedFile $file): void
    {
        if (!$file) {
            return;
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $payment->update([
            'receipt_path' => $this->storageFinalizeCdnPath(
                $this->storageStoreUpload($file, 'china-billing/comprobantes', 'cobro-'.$payment->id.'-'.now()->format('YmdHis').'.'.$extension)
            ),
            'receipt_name' => $file->getClientOriginalName(),
        ]);
    }

    private function nextCode(): string
    {
        $last = ChinaPayment::query()->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? (int) substr($last, 3) : 0;

        return 'PG-'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }
}
