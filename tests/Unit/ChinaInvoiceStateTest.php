<?php

namespace Tests\Unit;

use App\Services\ChinaBilling\ChinaBankAccountService;
use App\Services\ChinaBilling\ChinaInvoiceState;
use PHPUnit\Framework\TestCase;

class ChinaInvoiceStateTest extends TestCase
{
    private function op(array $overrides = []): array
    {
        return array_replace_recursive([
            'issued_on' => '2026-11-22',
            'organization' => ['name' => 'Genuino'],
            'service_name' => 'Consolidado',
            'variant_name' => null,
            'cbm' => 21.85,
            'rate' => 50,
            'service_amount' => 1092.5,
            'freight' => 6000,
            'fob' => 1000,
            'extras_amount' => 0,
            'total' => 8092.5,
        ], $overrides);
    }

    public function test_sin_cambios_no_marca_la_factura(): void
    {
        $this->assertSame([], ChinaInvoiceState::changes($this->op(), $this->op(['total' => 8092.501])));
    }

    public function test_lista_los_campos_contables_que_cambiaron(): void
    {
        $changes = ChinaInvoiceState::changes($this->op(), $this->op(['freight' => 6182, 'total' => 8274.5, 'issued_on' => '2026-11-25']));

        $this->assertSame(['issued_on', 'freight', 'total'], array_column($changes, 'field'));
        $this->assertSame(['label' => 'Flete marítimo', 'from' => '$6,000.00', 'to' => '$6,182.00'], array_diff_key($changes[1], ['field' => 1]));
        $this->assertSame('22/11/26', $changes[0]['from']);
    }

    public function test_detecta_cambio_de_cliente(): void
    {
        $changes = ChinaInvoiceState::changes($this->op(), $this->op(['organization' => ['name' => 'Genuino SRL']]));

        $this->assertSame([['field' => 'organization.name', 'label' => 'Cliente', 'from' => 'Genuino', 'to' => 'Genuino SRL']], $changes);
    }

    public function test_misma_cuenta_bancaria_no_marca_cambio(): void
    {
        $bank = ['account_number' => 'NRA1560200201059', 'payer_bank' => 'ZHEJIANG'];
        $active = ['fingerprint' => ChinaBankAccountService::fingerprint($bank + ['company' => '']), 'account' => 'NRA1560200201059'];

        $this->assertNull(ChinaInvoiceState::bankChange($bank, $active));
    }

    public function test_cuenta_nueva_muestra_los_ultimos_digitos(): void
    {
        $active = ['fingerprint' => ChinaBankAccountService::fingerprint(['account_number' => 'NRA999988887777']), 'account' => 'NRA999988887777'];

        $change = ChinaInvoiceState::bankChange(['account_number' => 'NRA1560200201059'], $active);
        $this->assertSame(['field' => 'bank', 'label' => 'Cuenta bancaria', 'from' => '…1059', 'to' => '…7777'], $change);
    }

    public function test_otro_dato_bancario_sin_cambiar_la_cuenta(): void
    {
        $active = ['fingerprint' => ChinaBankAccountService::fingerprint(['account_number' => 'A1', 'payer_swift' => 'NEW']), 'account' => 'A1'];

        $this->assertSame('Datos bancarios', ChinaInvoiceState::bankChange(['account_number' => 'A1', 'payer_swift' => 'OLD'], $active)['label']);
    }
}
