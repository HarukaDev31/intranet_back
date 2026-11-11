<?php

namespace Tests\Unit;

use App\Models\ChinaBilling\ChinaOperation;
use App\Models\ChinaBilling\ChinaOperationCost;
use App\Models\ChinaBilling\ChinaPayment;
use App\Models\ChinaBilling\ChinaPaymentAllocation;
use App\Services\ChinaBilling\ChinaBillingCalculator as Calc;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ChinaBillingCalculatorTest extends TestCase
{
    private function op(int $id, array $attrs = [], array $costs = []): ChinaOperation
    {
        $op = new ChinaOperation(array_merge(['billing_type' => 'cbm', 'cbm' => 10, 'rate' => 100, 'fob' => 200, 'freight' => 300], $attrs));
        $op->id = $id;
        $op->setRelation('costs', new Collection(array_map(fn ($a) => new ChinaOperationCost(['amount' => $a]), $costs)));
        $op->setRelation('allocations', new Collection());
        $op->setRelation('creditApplications', new Collection());

        return $op;
    }

    private function pay(int $id, float $sent, ?float $received = null, ?int $shortfallOp = null, bool $cashed = false): ChinaPayment
    {
        $p = new ChinaPayment(['amount_sent' => $sent, 'amount_received' => $received, 'shortfall_operation_id' => $shortfallOp]);
        $p->id = $id;
        $p->confirmed_at = $received !== null ? now() : null;
        $p->cashed_at = $cashed ? now() : null;
        $p->setRelation('allocations', new Collection());
        $p->setRelation('creditApplications', new Collection());

        return $p;
    }

    private function allocate(ChinaOperation $op, ChinaPayment $p, float $amount): void
    {
        $a = new ChinaPaymentAllocation(['payment_id' => $p->id, 'operation_id' => $op->id, 'amount' => $amount]);
        $a->setRelation('payment', $p);
        $op->allocations->push($a);
        $p->allocations->push($a);
    }

    public function test_total_is_service_plus_fob_freight_and_extras(): void
    {
        $m = Calc::operationMetrics($this->op(1, [], [50, 25]));
        $this->assertSame(1000.0, $m['service_amount']);
        $this->assertSame(1575.0, $m['total']);
        $this->assertSame(Calc::STATUS_PENDING, $m['status']);
        $this->assertSame(1575.0, $m['pending']);
    }

    public function test_fixed_service_uses_amount(): void
    {
        $m = Calc::operationMetrics($this->op(1, ['billing_type' => 'fixed', 'amount' => 80, 'fob' => 0, 'freight' => 0]));
        $this->assertSame(80.0, $m['total']);
    }

    public function test_unconfirmed_payment_is_to_confirm(): void
    {
        $op = $this->op(1);
        $this->allocate($op, $this->pay(1, 1500), 1500);
        $m = Calc::operationMetrics($op);
        $this->assertSame(Calc::STATUS_TO_CONFIRM, $m['status']);
        $this->assertSame(1500.0, $m['in_confirmation']);
        $this->assertSame(0.0, $m['confirmed']);
    }

    public function test_partial_payment_stays_pending_and_partner_sees_partial(): void
    {
        $op = $this->op(1);
        $this->allocate($op, $this->pay(1, 500, 500), 500);
        $m = Calc::operationMetrics($op);
        $this->assertSame(Calc::STATUS_PENDING, $m['status']);
        $this->assertSame('partial', $m['partner_status']);
        $this->assertSame(1000.0, $m['pending']);
    }

    public function test_shortfall_is_charged_to_the_chosen_operation(): void
    {
        $op = $this->op(1);
        $other = $this->op(2);
        $payment = $this->pay(1, 1500, 1400, 1);
        $this->allocate($op, $payment, 1000);
        $this->allocate($other, $payment, 500);

        $this->assertSame(100.0, Calc::paymentShortfall($payment));
        $m = Calc::operationMetrics($op);
        $this->assertSame(900.0, $m['received']);
        $this->assertSame(100.0, $m['shortfall_included']);
        $this->assertSame(500.0, Calc::operationMetrics($other)['received']);
    }

    public function test_excess_received_becomes_credit(): void
    {
        $op = $this->op(1);
        $payment = $this->pay(1, 2000, 2000);
        $this->allocate($op, $payment, 1500);
        $this->assertSame(500.0, Calc::paymentCredit($payment));
        $this->assertSame(500.0, Calc::creditBalance(new Collection([$payment])));
    }

    public function test_status_is_the_most_behind_payment(): void
    {
        $op = $this->op(1);
        $this->allocate($op, $this->pay(1, 700, 700, null, true), 700);
        $this->allocate($op, $this->pay(2, 800, 800), 800);
        $this->assertSame(Calc::STATUS_CONFIRMED, Calc::operationMetrics($op)['status']);
    }

    public function test_received_rmb_only_counts_payments_already_in_cash(): void
    {
        $op = $this->op(1);
        $cashed = $this->pay(1, 700, 700, null, true);
        $cashed->exchange_rate = 7.2;
        $this->allocate($op, $cashed, 700);
        $this->allocate($op, $this->pay(2, 300, 300), 300);
        $this->assertSame(5040.0, Calc::operationMetrics($op)['received_rmb']);
    }

    public function test_transferred_operation_has_no_pending(): void
    {
        $op = $this->op(1, ['transferred_to_operation_id' => 2]);
        $m = Calc::operationMetrics($op);
        $this->assertSame(0.0, $m['pending']);
        $this->assertSame('paid', $m['partner_status']);
    }
}
