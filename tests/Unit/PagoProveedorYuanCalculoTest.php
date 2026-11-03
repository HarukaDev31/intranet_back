<?php

namespace Tests\Unit;

use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Services\PagoProveedor\PagoProveedorYuanCalculo;
use PHPUnit\Framework\TestCase;

class PagoProveedorYuanCalculoTest extends TestCase
{
    public function test_faltan_yuanes_cuando_la_diferencia_supera_la_tolerancia(): void
    {
        $calculo = PagoProveedorYuanCalculo::calcular(1000, 7.05, 7100, 1);

        $this->assertSame(7050.0, $calculo['obtained']);
        $this->assertSame(-50.0, $calculo['difference_yuan']);
        $this->assertSame(-7.09, $calculo['difference_usd']);
        $this->assertSame(PagoProveedorEjecucion::RESULT_COLLECT, $calculo['result']);
    }

    public function test_sobran_yuanes_cuando_el_tipo_de_cambio_real_es_mejor(): void
    {
        $calculo = PagoProveedorYuanCalculo::calcular(1000, 7.20, 7100, 1);

        $this->assertSame(100.0, $calculo['difference_yuan']);
        $this->assertSame(PagoProveedorEjecucion::RESULT_REFUND, $calculo['result']);
    }

    public function test_la_tolerancia_de_un_dolar_la_asume_pro_business(): void
    {
        $calculo = PagoProveedorYuanCalculo::calcular(1000, 7.10, 7107, 1);

        $this->assertSame(-7.0, $calculo['difference_yuan']);
        $this->assertSame(-0.99, $calculo['difference_usd']);
        $this->assertSame(PagoProveedorEjecucion::RESULT_WITHIN, $calculo['result']);
    }
}
