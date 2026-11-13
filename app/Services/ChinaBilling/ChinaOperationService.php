<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaBilling\ChinaCreditApplication;
use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaOperationCost;
use App\Models\ChinaBilling\ChinaPayment;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ChinaOperationService
{
    use UsesObjectStorage;

    public function __construct(private readonly ChinaDraftService $drafts)
    {
    }

    /**
     * Crea una operación. Con container_id aprueba el borrador de ese consolidado.
     *
     * @param  array<string, mixed>  $data
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function create(array $data, ?UploadedFile $packing, array $actor): ChinaOperation
    {
        $organizationId = (int) $data['organization_id'];
        $this->requireOrganization($organizationId, $actor);
        $this->validateAmounts($data);

        $containerId = !empty($data['container_id']) ? (int) $data['container_id'] : null;
        if ($containerId) {
            $draft = $this->drafts->find($containerId, $actor['organization_ids']);
            if ((int) $draft['organization_id'] !== $organizationId) {
                throw new ChinaBillingException('El consolidado no pertenece a ese cliente.');
            }
        }

        return DB::transaction(function () use ($data, $packing, $actor, $organizationId, $containerId) {
            $operation = new ChinaOperation();
            $operation->fill($this->fields($data));
            $operation->fill([
                'code' => $this->nextCode(),
                'organization_id' => $organizationId,
                'container_id' => $containerId,
                'created_by' => $actor['id'],
            ]);
            $operation->save();
            $this->syncCosts($operation, $data['costs'] ?? []);
            $this->storePacking($operation, $packing);

            return $operation;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function update(int $id, array $data, ?UploadedFile $packing, array $actor): ChinaOperation
    {
        $operation = $this->find($id, $actor);
        $this->validateAmounts($data);

        return DB::transaction(function () use ($operation, $data, $packing) {
            $operation->update($this->fields($data));
            $this->syncCosts($operation, $data['costs'] ?? []);
            $this->storePacking($operation, $packing);

            return $operation;
        });
    }

    /**
     * Al eliminar, el dinero que tenía asignado queda sin asignar en cada cobro y vuelve como saldo a favor
     * (las asignaciones y el saldo aplicado se borran en cascada). Las operaciones trasladadas a esta vuelven a mostrar su diferencia.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function delete(int $id, array $actor): void
    {
        $operation = $this->find($id, $actor);

        DB::transaction(function () use ($operation) {
            ChinaOperation::query()->where('transferred_to_operation_id', $operation->id)->update(['transferred_to_operation_id' => null]);
            ChinaOperationCost::query()->where('source_operation_id', $operation->id)->delete();
            $operation->delete();
        });
    }

    /**
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function dismissDraft(int $containerId, array $actor): void
    {
        $this->drafts->dismiss($containerId, $actor['organization_ids'], $actor['id']);
    }

    /**
     * Traslada la diferencia por cobrar de una operación como costo "Diferencia OP-xxxx" de otra pendiente del mismo cliente.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function transferDifference(int $id, int $targetId, array $actor): void
    {
        $source = $this->find($id, $actor);
        $target = $this->find($targetId, $actor);
        $this->requireSameOrganization($source, $target);

        $metrics = $this->metrics($source);
        if ($metrics['transferred'] || ($metrics['difference'] ?? 0) <= 0) {
            throw new ChinaBillingException('Esta operación no tiene una diferencia por cobrar para trasladar.');
        }
        $targetMetrics = $this->metrics($target);
        if ($targetMetrics['transferred'] || $targetMetrics['pending'] <= 0) {
            throw new ChinaBillingException('Elige una operación pendiente del mismo cliente.');
        }

        DB::transaction(function () use ($source, $target, $metrics) {
            ChinaOperationCost::query()->create([
                'operation_id' => $target->id,
                'name' => 'Diferencia '.$source->code,
                'amount' => $metrics['difference'],
                'source_operation_id' => $source->id,
            ]);
            $source->update(['transferred_to_operation_id' => $target->id]);
        });
    }

    /**
     * Deshace el traslado. Devuelve true si la operación destino ya tenía pagos (para avisar).
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function undoTransferDifference(int $id, array $actor): bool
    {
        $source = $this->find($id, $actor);
        if (!$source->transferred_to_operation_id) {
            throw new ChinaBillingException('Esta operación no fue trasladada.');
        }
        $target = ChinaOperation::query()->with('allocations')->find($source->transferred_to_operation_id);

        DB::transaction(function () use ($source) {
            ChinaOperationCost::query()
                ->where('operation_id', $source->transferred_to_operation_id)
                ->where('source_operation_id', $source->id)
                ->delete();
            $source->update(['transferred_to_operation_id' => null]);
        });

        return (bool) $target?->allocations->isNotEmpty();
    }

    /**
     * Aplica saldo a favor del cliente a una operación: consume primero los cobros más antiguos.
     *
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function applyCredit(int $id, float $amount, ?int $fromOperationId, array $actor): void
    {
        $operation = $this->find($id, $actor);
        $metrics = $this->metrics($operation);
        if ($metrics['transferred'] || $metrics['pending'] <= 0) {
            throw new ChinaBillingException('Esta operación no tiene saldo pendiente.');
        }

        DB::transaction(function () use ($operation, $amount, $fromOperationId, $metrics) {
            $payments = ChinaPayment::query()
                ->with(['allocations', 'creditApplications'])
                ->where('organization_id', $operation->organization_id)
                ->whereNotNull('confirmed_at')
                ->orderBy('paid_on')->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = ChinaBillingCalculator::creditBalance($payments);
            $limit = ChinaBillingCalculator::round(min($available, $metrics['pending']));
            if ($amount <= 0 || $amount > $limit) {
                throw new ChinaBillingException('El monto debe ser mayor a 0 y no superar $'.number_format($limit, 2).'.');
            }

            $left = $amount;
            foreach ($payments as $payment) {
                if ($left <= 0) {
                    break;
                }
                $free = ChinaBillingCalculator::paymentCredit($payment) - (float) $payment->creditApplications->sum('amount');
                if ($free <= 0) {
                    continue;
                }
                $take = ChinaBillingCalculator::round(min($free, $left));
                ChinaCreditApplication::query()->create([
                    'payment_id' => $payment->id,
                    'operation_id' => $operation->id,
                    'amount' => $take,
                    'applied_on' => now()->toDateString(),
                    'from_operation_id' => $fromOperationId,
                ]);
                $left = ChinaBillingCalculator::round($left - $take);
            }
        });
    }

    /**
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function undoCredit(int $applicationId, array $actor): void
    {
        $application = ChinaCreditApplication::query()->findOrFail($applicationId);
        $this->find((int) $application->operation_id, $actor);
        $application->delete();
    }

    /**
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    public function find(int $id, array $actor): ChinaOperation
    {
        $operation = ChinaOperation::query()->whereIn('organization_id', $actor['organization_ids'])->find($id);
        if (!$operation) {
            throw new ChinaBillingException('La operación no existe o no tienes acceso.', 404);
        }

        return $operation;
    }

    /** @return array<string, mixed> */
    private function metrics(ChinaOperation $operation): array
    {
        return ChinaBillingCalculator::operationMetrics(
            $operation->load(['costs', 'allocations.payment', 'creditApplications'])
        );
    }

    /**
     * @param  array{id: int|null, organization_ids: array<int, int>}  $actor
     */
    private function requireOrganization(int $organizationId, array $actor): void
    {
        if (!in_array($organizationId, $actor['organization_ids'], true)) {
            throw new ChinaBillingException('No tienes acceso a ese cliente.', 403);
        }
    }

    private function requireSameOrganization(ChinaOperation $a, ChinaOperation $b): void
    {
        if ($a->id === $b->id || $a->organization_id !== $b->organization_id) {
            throw new ChinaBillingException('Las operaciones deben ser del mismo cliente.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateAmounts(array $data): void
    {
        if (($data['billing_type'] ?? '') === ChinaOperation::BILLING_CBM) {
            if ((float) ($data['cbm'] ?? 0) <= 0 || (float) ($data['rate'] ?? 0) <= 0) {
                throw new ChinaBillingException('Indica el CBM y la tarifa por CBM.');
            }

            return;
        }
        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw new ChinaBillingException('Indica el monto del servicio.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $isCbm = ($data['billing_type'] ?? '') === ChinaOperation::BILLING_CBM;

        return [
            'service_name' => $data['service_name'],
            'billing_type' => $isCbm ? ChinaOperation::BILLING_CBM : ChinaOperation::BILLING_FIXED,
            'variant_name' => $data['variant_name'] ?? null,
            'issued_on' => $data['issued_on'],
            'description' => $data['description'],
            'cbm' => $isCbm ? $data['cbm'] : null,
            'rate' => $isCbm ? $data['rate'] : null,
            'amount' => $isCbm ? 0 : $data['amount'],
            'fob' => $data['fob'] ?? 0,
            'freight' => $data['freight'] ?? 0,
        ];
    }

    /**
     * Costos con monto 0 se descartan; sin nombre se llaman "Costo adicional". Los traslados de diferencia se conservan.
     *
     * @param  array<int, array<string, mixed>>  $costs
     */
    private function syncCosts(ChinaOperation $operation, array $costs): void
    {
        ChinaOperationCost::query()->where('operation_id', $operation->id)->whereNull('source_operation_id')->delete();
        foreach ($costs as $cost) {
            if ((float) ($cost['amount'] ?? 0) <= 0) {
                continue;
            }
            ChinaOperationCost::query()->create([
                'operation_id' => $operation->id,
                'name' => trim((string) ($cost['name'] ?? '')) ?: 'Costo adicional',
                'amount' => $cost['amount'],
            ]);
        }
    }

    private function storePacking(ChinaOperation $operation, ?UploadedFile $file): void
    {
        if (!$file) {
            return;
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = $this->storageFinalizeCdnPath(
            $this->storageStoreUpload($file, 'china-billing/packing', 'packing-'.$operation->id.'-'.now()->format('YmdHis').'.'.$extension)
        );
        $operation->update(['packing_list_path' => $path, 'packing_list_name' => $file->getClientOriginalName()]);
    }

    private function nextCode(): string
    {
        $last = ChinaOperation::query()->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? (int) substr($last, 3) : 0;

        return 'OP-'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }
}
