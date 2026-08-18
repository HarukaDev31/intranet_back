<?php

namespace Tests\Unit;

use App\Services\CargaConsolidada\CotizacionFinal\PlantillaFinalBatchService;
use App\Services\CargaConsolidada\CotizacionFinal\TarifaTipoClienteCalculator;
use Tests\TestCase;

class PlantillaFinalBatchMatchTest extends TestCase
{
    private function service()
    {
        return new class extends PlantillaFinalBatchService {
            public function __construct()
            {
            }

            public function match($a, $b)
            {
                return $this->matchClientName($a, $b);
            }

            public function volumen($item)
            {
                return $this->resolveVolumenAsignado($item);
            }

            public function volumenExcel(array $cliente)
            {
                return $this->volumenFromExcelCliente($cliente);
            }

            public function tarifa($item, $volumen, $tipoCliente)
            {
                return $this->resolveTarifa($item, $volumen, $tipoCliente);
            }
        };
    }

    public function test_match_ignora_guion_puntos_acentos_y_espacios(): void
    {
        $svc = $this->service();

        $this->assertTrue($svc->match('MARTHA SEMINARIO - YUAN', 'MARTHA SEMINARIO YUAN'));
        $this->assertTrue($svc->match('QCIMPORT E.I.R.L.', 'QCIMPORT EIRL'));
        $this->assertTrue($svc->match('JOSEMARIA WESTON PONCE DE LEON', 'JOSE MARIA WESTON PONCE DE LEON'));
        $this->assertTrue($svc->match('VLADIMIR GUERRERO CHUCHÓN', 'VLADIMIR GUERRERO CHUCHON'));
        $this->assertFalse($svc->match('EDUARDO DAZA', 'WILSON RIVEROS ESLAVA'));
    }

    public function test_volumen_ignora_campo_seleccionado_en_cero_y_usa_otro(): void
    {
        $svc = $this->service();
        $item = (object) [
            'vol_selected' => 'volumen',
            'volumen' => 0,
            'volumen_china' => 1.87,
            'volumen_doc' => 1.80,
        ];

        $this->assertEqualsWithDelta(1.87, $svc->volumen($item), 0.0001);
    }

    public function test_volumen_excel_usa_cbm_de_plantilla(): void
    {
        $svc = $this->service();

        $this->assertEqualsWithDelta(0.82, $svc->volumenExcel([
            'volumen_excel' => 0.82,
            'productos' => [['cbm' => '0.10']],
        ]), 0.0001);

        $this->assertEqualsWithDelta(1.29, $svc->volumenExcel([
            'productos' => [['cbm' => '1.29'], ['cbm' => '']],
        ]), 0.0001);
    }

    public function test_tarifa_se_calcula_si_bd_esta_en_cero(): void
    {
        $svc = $this->service();
        $itemSinTarifa = (object) ['tarifa' => 0];

        $this->assertEquals(375.0, $svc->tarifa($itemSinTarifa, 1.00, 'NUEVO'));
        $this->assertEquals(350.0, $svc->tarifa($itemSinTarifa, 1.00, 'ANTIGUO'));
        $this->assertEquals(250.0, $svc->tarifa($itemSinTarifa, 0.82, 'SOCIO'));
        $this->assertEquals(280.0, $svc->tarifa((object) ['tarifa' => '280.00'], 0, 'NUEVO'));
    }

    public function test_calculator_incluye_limites_exactos(): void
    {
        $this->assertEquals(375.0, TarifaTipoClienteCalculator::calculate('NUEVO', 0.59));
        $this->assertEquals(375.0, TarifaTipoClienteCalculator::calculate('NUEVO', 1.00));
        $this->assertEquals(350.0, TarifaTipoClienteCalculator::calculate('NUEVO', 2.00));
        $this->assertEquals(350.0, TarifaTipoClienteCalculator::calculate('ANTIGUO', 1.00));
    }
}
