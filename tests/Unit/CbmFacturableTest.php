<?php

namespace Tests\Unit;

use App\Services\CalculadoraImportacion\CalculadoraTarifaService;
use App\Services\CalculadoraImportacion\CbmFacturable;
use PHPUnit\Framework\TestCase;

class CbmFacturableTest extends TestCase
{
    public function test_el_ejemplo_del_ticket_usa_el_volumen_total_y_no_mezcla_por_proveedor(): void
    {
        $proveedores = [
            ['cbm' => 0.20, 'peso' => 44, 'maxcbm' => 0.20],
            ['cbm' => 0.20, 'peso' => 32, 'maxcbm' => 0.20],
            ['cbm' => 0.32, 'peso' => 62, 'maxcbm' => 0.32],
            ['cbm' => 0.29, 'peso' => 553, 'maxcbm' => 0.553],
        ];

        $totales = CbmFacturable::desdeProveedores($proveedores);

        $this->assertEqualsWithDelta(1.01, $totales['sum_cbm'], 0.0001);
        $this->assertEqualsWithDelta(0.691, $totales['peso_cbm'], 0.0001);
        $this->assertFalse($totales['domina_peso']);
        $this->assertEqualsWithDelta(1.01, $totales['facturable'], 0.0001);
        $this->assertEqualsWithDelta(0.29, CbmFacturable::aporte(0.29, 553, false), 0.0001);

        $service = new CalculadoraTarifaService();
        $this->assertEqualsWithDelta(1.01, $service->totalCbmFromProveedoresPayload($proveedores), 0.0001);
    }

    public function test_si_el_peso_total_es_mayor_el_facturable_es_la_suma_de_kilos(): void
    {
        $proveedores = [
            ['cbm' => 0.20, 'peso' => 1800],
            ['cbm' => 2.10, 'peso' => 1500],
            ['cbm' => 1.10, 'peso' => 900],
            ['cbm' => 0.70, 'peso' => 1200],
            ['cbm' => 1.20, 'peso' => 2500],
            ['cbm' => 3.30, 'peso' => 2000],
        ];

        $totales = CbmFacturable::desdeProveedores($proveedores);

        $this->assertEqualsWithDelta(8.60, $totales['sum_cbm'], 0.0001);
        $this->assertEqualsWithDelta(9.90, $totales['facturable'], 0.0001);
        $this->assertTrue($totales['domina_peso']);
        $this->assertEqualsWithDelta(1.50, CbmFacturable::aporte(2.10, 1500, true), 0.0001);
    }
}
