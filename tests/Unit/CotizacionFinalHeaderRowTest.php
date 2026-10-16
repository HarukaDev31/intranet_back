<?php

namespace Tests\Unit;

use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use ReflectionMethod;
use Tests\TestCase;

class CotizacionFinalHeaderRowTest extends TestCase
{
    private function isHeaderRow($name)
    {
        $controller = (new \ReflectionClass(CotizacionFinalController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(CotizacionFinalController::class, 'isHeaderRow');
        $method->setAccessible(true);

        return $method->invoke($controller, $name);
    }

    public function test_razon_social_con_productos_no_se_trata_como_encabezado(): void
    {
        $this->assertFalse($this->isHeaderRow('TIENDA DE PRODUCTOS IMPORTADOS S.A.C.'));
        $this->assertFalse($this->isHeaderRow('PRODUCTOS DEL NORTE S.A.C.'));
    }

    public function test_fila_de_encabezado_sigue_descartandose(): void
    {
        $this->assertTrue($this->isHeaderRow('PRODUCTO'));
        $this->assertTrue($this->isHeaderRow('CLIENTE'));
        $this->assertTrue($this->isHeaderRow('PRECIO UNITARIO'));
    }
}
