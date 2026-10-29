<?php

namespace Tests\Unit;

use App\Exceptions\PagoProveedorException;
use App\Services\PagoProveedor\PagoProveedorAbonoEvaluador;
use App\Services\PagoProveedor\PagoProveedorCodigoSolicitud;
use App\Services\PagoProveedor\PagoProveedorDatosReglas;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PagoProveedorEtapa1Test extends TestCase
{
    public function test_wechat_solo_acepta_digitos(): void
    {
        $this->assertSame('8613800138000', PagoProveedorDatosReglas::wechat('86 138 0013 8000'));
        $this->expectException(PagoProveedorException::class);
        PagoProveedorDatosReglas::wechat('wechat-li');
    }

    public function test_swift_rechaza_siete_caracteres_y_acepta_ocho_u_once(): void
    {
        $this->assertSame('DEUTDEFF', PagoProveedorDatosReglas::codigoBanco('SWIFT', 'deutdeff'));
        $this->assertSame('DEUTDEFF500', PagoProveedorDatosReglas::codigoBanco('SWIFT', 'DEUTDEFF500'));

        $this->expectException(PagoProveedorException::class);
        PagoProveedorDatosReglas::codigoBanco('SWIFT', 'DEUTDEF');
    }

    public function test_abono_parcial_no_marca_cubierto_si_usa_todo_el_importe(): void
    {
        $resultado = (new PagoProveedorAbonoEvaluador())->evaluar(400, [
            10 => 400,
        ], [
            10 => 1000,
        ]);

        $this->assertSame(400.0, $resultado['aplicado']);
        $this->assertSame(0.0, $resultado['excedente']);
        $this->assertSame([], $resultado['cubiertos']);
    }

    public function test_lo_que_sobra_despues_de_cubrir_el_saldo_es_excedente(): void
    {
        $resultado = (new PagoProveedorAbonoEvaluador())->evaluar(1200, [
            10 => 1000,
        ], [
            10 => 1000,
        ]);

        $this->assertSame(200.0, $resultado['excedente']);
        $this->assertSame([10], $resultado['cubiertos']);
    }

    public function test_abono_que_completa_al_proveedor(): void
    {
        $resultado = (new PagoProveedorAbonoEvaluador())->evaluar(1000, [
            10 => 1000,
            11 => 0,
        ], [
            10 => 1000,
            11 => 500,
        ]);

        $this->assertSame([10], $resultado['cubiertos']);
        $this->assertSame(0.0, $resultado['excedente']);
    }

    public function test_no_permite_aplicar_mas_que_el_saldo_ni_un_abono_vacio(): void
    {
        $evaluador = new PagoProveedorAbonoEvaluador();

        try {
            $evaluador->evaluar(50, [10 => 80], [10 => 40]);
            $this->fail('Debió rechazar el monto mayor al saldo.');
        } catch (PagoProveedorException $e) {
            $this->assertStringContainsString('saldo', $e->getMessage());
        }

        $this->expectException(PagoProveedorException::class);
        $evaluador->evaluar(50, [10 => 0], [10 => 40]);
    }

    public function test_excedente_menor_a_un_dolar_con_cuota_2_se_aplica(): void
    {
        $evaluador = new PagoProveedorAbonoEvaluador();
        $this->assertSame('aplicado_cuota', $evaluador->decisionExcedente(0.8, true));
        $this->assertSame('no_devuelve', $evaluador->decisionExcedente(0.8, false));
        $this->assertSame('por_consultar', $evaluador->decisionExcedente(25, true));
        $this->assertNull($evaluador->decisionExcedente(0, true));
    }

    public function test_codigo_de_solicitud_interno(): void
    {
        $codigo = (new PagoProveedorCodigoSolicitud())->armar(
            'RMB',
            15,
            Carbon::parse('2026-10-29 12:00:00', 'America/Lima')
        );

        $this->assertSame('CN15OCT26', $codigo);
        $this->assertSame('PE', substr((new PagoProveedorCodigoSolicitud())->armar('USD', 9, Carbon::parse('2026-10-29', 'America/Lima')), 0, 2));
    }
}
