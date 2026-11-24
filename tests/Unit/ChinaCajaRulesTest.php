<?php

namespace Tests\Unit;

use App\Services\ChinaCaja\ChinaCashRules;
use App\Services\ChinaCaja\ChinaContainerRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ChinaCajaRulesTest extends TestCase
{
    public function test_semanas_de_noviembre_unen_las_puntas_cortas_y_vencen_lunes_o_fin_de_mes(): void
    {
        $weeks = ChinaCashRules::weeks('2026-11');

        $this->assertSame(['2026-11-01', '2026-11-08', '2026-11-09'], [$weeks[0]['starts_on'], $weeks[0]['ends_on'], $weeks[0]['due_on']]);
        $this->assertSame(['2026-11-23', '2026-11-30', '2026-11-30'], [$weeks[3]['starts_on'], $weeks[3]['ends_on'], $weeks[3]['due_on']]);
        $this->assertCount(4, $weeks);
    }

    public function test_todo_mes_queda_cubierto_sin_huecos(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $month = sprintf('2027-%02d', $i);
            $next = CarbonImmutable::parse($month.'-01');
            foreach (ChinaCashRules::weeks($month) as $week) {
                $this->assertSame($next->toDateString(), $week['starts_on']);
                $next = CarbonImmutable::parse($week['ends_on'])->addDay();
            }
            $this->assertSame(1, $next->day);
        }
    }

    public function test_etapas_bloqueadas_hasta_60_cbm_llenado_y_cierre(): void
    {
        $locks = fn (array $p) => array_column($p['stages'], 'locked');
        $today = CarbonImmutable::parse('2026-11-16');

        $this->assertSame([true, true, true], $locks(ChinaContainerRules::progress([], 59.9, false, false, $today)));
        $this->assertSame([false, true, true], $locks(ChinaContainerRules::progress([], 60, false, false, $today)));
        $this->assertSame([false, false, false], $locks(ChinaContainerRules::progress(['filling_on' => '2026-11-18'], 60, true, false, $today)));
        $this->assertTrue($locks(ChinaContainerRules::progress(['filling_on' => '2026-11-19'], 60, true, false, $today))[1]);
    }

    public function test_avance_es_items_completados_sobre_quince(): void
    {
        $data = array_fill_keys(array_keys(ChinaContainerRules::RESERVATION), '2026-11-18');
        $data['freight_usd'] = 4500;
        $result = ChinaContainerRules::progress($data, 60, true, true, CarbonImmutable::parse('2026-11-18'));

        $this->assertSame(67, $result['percent']);
        $this->assertSame(2, $result['current']);
        $this->assertSame([], $result['missing_reservation']);
    }

    public function test_bl_tres_semanas_tras_zarpe_y_arribo_siete_dias_antes(): void
    {
        $row = ['sailing_on' => '2026-10-26', 'arrival_on' => '2026-11-23', 'has_bl' => false, 'telex' => false, 'collected' => false, 'freight_paid' => false];
        $status = ChinaContainerRules::status($row, CarbonImmutable::parse('2026-11-16'));
        $this->assertSame(['this_week', true, true], [$status['group'], $status['bl_due'], $status['arrival_due']]);

        $before = ChinaContainerRules::status($row, CarbonImmutable::parse('2026-11-15'));
        $this->assertSame(['in_transit', false, false], [$before['group'], $before['bl_due'], $before['arrival_due']]);
        $this->assertSame('arrived', ChinaContainerRules::status($row, CarbonImmutable::parse('2026-11-24'))['group']);
        $this->assertSame('to_sail', ChinaContainerRules::status($row, CarbonImmutable::parse('2026-10-25'))['group']);
    }

    public function test_completado_exige_bl_telex_cobro_y_flete(): void
    {
        $row = ['sailing_on' => '2026-10-01', 'arrival_on' => '2026-11-01', 'has_bl' => true, 'telex' => true, 'collected' => true, 'freight_paid' => true];
        $today = CarbonImmutable::parse('2026-11-16');
        $this->assertSame('completed', ChinaContainerRules::status($row, $today)['group']);
        foreach (['has_bl', 'telex', 'collected', 'freight_paid'] as $key) {
            $this->assertFalse(ChinaContainerRules::status([$key => false] + $row, $today)['completed']);
        }
    }

    public function test_totales_separan_reales_y_proyectados(): void
    {
        $totals = ChinaCashRules::totals([
            ['date' => '2026-11-02', 'type' => 'in', 'amount' => 100, 'real' => true],
            ['date' => '2026-11-20', 'type' => 'in', 'amount' => 50, 'real' => false],
            ['date' => '2026-11-03', 'type' => 'out', 'amount' => 30, 'real' => true],
            ['date' => '2026-11-25', 'type' => 'out', 'amount' => 10, 'real' => false],
        ]);

        $this->assertSame([70.0, 110.0], [$totals['profit'], $totals['profit_projected']]);
    }
}
