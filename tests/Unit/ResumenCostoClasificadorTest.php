<?php

namespace Tests\Unit;

use App\Support\CargaConsolidada\ResumenCostoClasificador;
use Tests\TestCase;

class ResumenCostoClasificadorTest extends TestCase
{
    public function test_clasifica_isd_antes_que_impuesto()
    {
        $this->assertSame(ResumenCostoClasificador::ISD, ResumenCostoClasificador::tipo('ISD'));
        $this->assertSame(ResumenCostoClasificador::ISD, ResumenCostoClasificador::tipo('Impuesto a la salida de divisas'));
        $this->assertSame(ResumenCostoClasificador::ISD, ResumenCostoClasificador::tipo('Salida divisas'));
    }

    public function test_clasifica_fob_y_mercancia()
    {
        $this->assertSame(ResumenCostoClasificador::FOB, ResumenCostoClasificador::tipo('Valor de Mercadería'));
        $this->assertSame(ResumenCostoClasificador::FOB, ResumenCostoClasificador::tipo('Valor mercancía'));
        $this->assertSame(ResumenCostoClasificador::FOB, ResumenCostoClasificador::tipo('FOB'));
    }

    public function test_clasifica_impuesto_y_logistica()
    {
        $this->assertSame(ResumenCostoClasificador::IMPUESTO, ResumenCostoClasificador::tipo('Tributos e Impuestos Aduaneros'));
        $this->assertSame(ResumenCostoClasificador::LOGISTICA, ResumenCostoClasificador::tipo('Servicio de importación'));
        $this->assertSame(ResumenCostoClasificador::LOGISTICA, ResumenCostoClasificador::tipo('Servicios de Importacion'));
        $this->assertSame(ResumenCostoClasificador::OTRO, ResumenCostoClasificador::tipo('Flete'));
        $this->assertSame(ResumenCostoClasificador::OTRO, ResumenCostoClasificador::tipo('Transferencia'));
        $this->assertSame(ResumenCostoClasificador::OTRO, ResumenCostoClasificador::tipo('Logística Internacional'));
        $this->assertSame(ResumenCostoClasificador::OTRO, ResumenCostoClasificador::tipo('Seguro'));
    }
}
