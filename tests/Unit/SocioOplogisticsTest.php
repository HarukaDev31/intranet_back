<?php

namespace Tests\Unit;

use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use App\Support\Organizacion\SocioOplogistics;
use Tests\TestCase;

class SocioOplogisticsTest extends TestCase
{
    protected function tearDown(): void
    {
        SocioOplogistics::fijarCache([]);
        parent::tearDown();
    }

    public function test_las_orgs_existentes_no_son_oplogistics()
    {
        foreach ([0, 1, 2, 3, null] as $id) {
            $this->assertFalse(SocioOplogistics::es($id));
        }
        $this->assertTrue(SocioOplogistics::esNombre(' Oplogistics '));
    }

    public function test_su_codigo_lleva_el_numero_4_sin_depender_del_id()
    {
        SocioOplogistics::fijarCache([9 => true]);

        $this->assertSame('ECU4-JUPE1-1', CodeSupplierHelper::generateWithPaisPrefix('Ecuador', 9, 'Juan Perez', '01', 1, 'ec', 'ECU'));
        $this->assertSame(1, CodeSupplierHelper::socioEmpresaNumero(2));
    }

    public function test_su_cotizacion_no_duplica_montos_y_no_lleva_isd()
    {
        $costos = array_map(fn ($c) => ['concepto' => $c[0], 'valor' => $c[1]], [
            ['Valor de carga (FOB)', 99], ['Flete internacional + seguro', 285], ['Valor CIF', 285], ['Total tributos', 44.39],
            ['Primer pago (logística)', 327.75], ['Segundo pago (impuestos)', 44.39], ['ISD', 5],
            ['Valor de carga (pago al proveedor)', 100], ['Servicio de importación (origen, flete, destino)', 327.75], ['Impuestos', 44.39],
        ]);

        $data = SocioOplogistics::ajustarExtraccion(['proveedores' => [
            ['productos' => 'a', 'isd' => 5, 'costos' => array_slice($costos, 0, 4)],
            ['productos' => 'b', 'costos' => array_slice($costos, 4)],
        ]]);

        $this->assertSame([
            ['concepto' => 'Valor de carga (FOB)', 'valor' => 100],
            ['concepto' => 'Servicio de importación (origen, flete, destino)', 'valor' => 327.75],
            ['concepto' => 'Impuestos', 'valor' => 44.39],
        ], $data['proveedores'][0]['costos']);
        $this->assertSame([], $data['proveedores'][1]['costos']);
        $this->assertNull($data['proveedores'][0]['isd']);
    }

    public function test_sin_resumen_usa_el_primer_concepto_de_cada_tipo()
    {
        $this->assertSame(
            [['concepto' => 'Total tributos', 'valor' => 44.39]],
            SocioOplogistics::costosDelResumen([['concepto' => 'Total tributos', 'valor' => 44.39], ['concepto' => 'Segundo pago (impuestos)', 'valor' => 44.39]])
        );
    }
}
