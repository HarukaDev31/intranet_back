<?php

namespace App\Services\ChinaBilling;

use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaPayment;
use Illuminate\Support\Collection;

/**
 * Única fuente de cálculo de Operaciones/Cobros de China (el frontend solo pinta).
 *
 * Espera cargadas las relaciones: operación → costs, allocations.payment, creditApplications;
 * cobro → allocations (y creditApplications para el saldo a favor).
 */
class ChinaBillingCalculator
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_TO_CONFIRM = 'to_confirm';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_CASH = 'in_cash';

    /** Orden de "más atrasado" a "más avanzado". */
    private const STATUS_ORDER = [
        self::STATUS_TO_CONFIRM => 1,
        self::STATUS_CONFIRMED => 2,
        self::STATUS_IN_CASH => 3,
    ];

    public static function round(float $value): float
    {
        return round($value, 2);
    }

    public static function paymentStatus(ChinaPayment $payment): string
    {
        if ($payment->cashed_at) {
            return self::STATUS_IN_CASH;
        }

        return $payment->confirmed_at ? self::STATUS_CONFIRMED : self::STATUS_TO_CONFIRM;
    }

    /** Total asignado del cobro a operaciones. */
    public static function allocatedTotal(ChinaPayment $payment): float
    {
        return self::round((float) $payment->allocations->sum('amount'));
    }

    /** Faltante: lo asignado que no llegó (solo cobro confirmado). */
    public static function paymentShortfall(ChinaPayment $payment): float
    {
        if (!$payment->confirmed_at) {
            return 0.0;
        }

        return self::round(max(0, self::allocatedTotal($payment) - (float) $payment->amount_received));
    }

    /** Excedente que pasa a saldo a favor (cobro confirmado). */
    public static function paymentCredit(ChinaPayment $payment): float
    {
        if (!$payment->confirmed_at) {
            return 0.0;
        }

        return self::round(max(0, (float) $payment->amount_received - self::allocatedTotal($payment)));
    }

    public static function operationServiceAmount(ChinaOperation $op): float
    {
        $service = $op->billing_type === ChinaOperation::BILLING_CBM
            ? (float) $op->cbm * (float) $op->rate
            : (float) $op->amount;

        return self::round($service);
    }

    /**
     * @return array<string, mixed>
     */
    public static function operationMetrics(ChinaOperation $op): array
    {
        $service = self::operationServiceAmount($op);
        $extras = self::round((float) $op->costs->sum('amount'));
        $total = self::round($service + (float) $op->fob + (float) $op->freight + $extras);

        $credit = self::round((float) $op->creditApplications->sum('amount'));
        $declared = $credit;
        $confirmed = $credit;
        $inConfirmation = 0.0;
        $received = $credit;
        $allConfirmed = true;
        $worst = null;
        $shortfallIncluded = 0.0;
        $receivedRmb = 0.0;

        foreach ($op->allocations as $allocation) {
            $payment = $allocation->payment;
            $amount = (float) $allocation->amount;
            $declared += $amount;
            $status = self::paymentStatus($payment);

            if ($status === self::STATUS_TO_CONFIRM) {
                $inConfirmation += $amount;
                $allConfirmed = false;
            } else {
                $real = $amount;
                if ((int) $payment->shortfall_operation_id === (int) $op->id) {
                    $shortfall = self::paymentShortfall($payment);
                    $real = max(0, $amount - $shortfall);
                    $shortfallIncluded += $shortfall;
                }
                $confirmed += $real;
                $received += $real;
                if ($status === self::STATUS_IN_CASH) {
                    $receivedRmb += $real * (float) $payment->exchange_rate;
                }
            }

            if ($worst === null || self::STATUS_ORDER[$status] < self::STATUS_ORDER[$worst]) {
                $worst = $status;
            }
        }

        $transferred = $op->transferred_to_operation_id !== null;
        $declared = self::round($declared);
        $confirmed = self::round($confirmed);
        $inConfirmation = self::round($inConfirmation);
        $received = self::round($received);

        $pending = $transferred ? 0.0 : self::round(max(0, $total - $declared));
        $covered = $declared >= $total;

        if (!$transferred && !$covered) {
            $status = self::STATUS_PENDING;
        } else {
            $status = $worst ?? self::STATUS_CONFIRMED;
        }

        $difference = null;
        if (!$transferred && $covered && $allConfirmed) {
            $difference = self::round($total - $received);
        }

        return [
            'service_amount' => $service,
            'extras_amount' => $extras,
            'total' => $total,
            'declared' => $declared,
            'confirmed' => $confirmed,
            'in_confirmation' => $inConfirmation,
            'received' => $received,
            'received_rmb' => self::round($receivedRmb),
            'credit_applied' => $credit,
            'shortfall_included' => self::round($shortfallIncluded),
            'pending' => $pending,
            'partner_balance' => $transferred ? 0.0 : self::round(max(0, $total - $confirmed - $inConfirmation)),
            'difference' => $difference,
            'transferred' => $transferred,
            'status' => $status,
            'partner_status' => self::partnerStatus($status, $transferred, $confirmed, $inConfirmation),
        ];
    }

    /** Estado visible para el socio: pending, partial, to_confirm, paid. */
    public static function partnerStatus(string $status, bool $transferred, float $confirmed, float $inConfirmation): string
    {
        if ($transferred) {
            return 'paid';
        }
        if ($status === self::STATUS_PENDING) {
            return ($confirmed + $inConfirmation) > 0 ? 'partial' : 'pending';
        }

        return $status === self::STATUS_TO_CONFIRM ? 'to_confirm' : 'paid';
    }

    /**
     * Saldo a favor disponible del cliente: excedentes de cobros confirmados − aplicado.
     *
     * @param  Collection<int, ChinaPayment>  $payments  cobros de UNA organización
     */
    public static function creditBalance(Collection $payments): float
    {
        $generated = $payments->sum(fn (ChinaPayment $p) => self::paymentCredit($p));
        $applied = $payments->sum(fn (ChinaPayment $p) => (float) $p->creditApplications->sum('amount'));

        return self::round(max(0, $generated - $applied));
    }

    /**
     * Cabecera/KPIs. Cálculo único para China y para el socio.
     *
     * @param  Collection<int, ChinaOperation>  $operations
     * @param  Collection<int, ChinaPayment>  $payments
     * @return array<string, float|int>
     */
    public static function summary(Collection $operations, Collection $payments): array
    {
        $rows = $operations->map(fn (ChinaOperation $op) => self::operationMetrics($op));
        $active = $rows->reject(fn ($m) => $m['transferred']);
        $cashed = $payments->filter(fn (ChinaPayment $p) => $p->cashed_at);

        return [
            'operations_count' => $operations->count(),
            'services_amount' => self::round($rows->sum('service_amount')),
            'receivable' => self::round($active->sum(fn ($m) => max(0, $m['total'] - $m['received']))),
            'cashed_usd' => self::round($cashed->sum('amount_received')),
            'cashed_rmb' => self::round($cashed->sum('yuan_amount')),
            'total_billed' => self::round($rows->sum('total')),
            'paid' => self::round($rows->sum('confirmed')),
            'in_confirmation' => self::round($rows->sum('in_confirmation')),
            'balance_due' => self::round($active->sum('partner_balance')),
            'credit_balance' => self::creditBalance($payments),
        ];
    }
}
