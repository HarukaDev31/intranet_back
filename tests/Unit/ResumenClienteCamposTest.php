<?php

namespace Tests\Unit;

use App\Support\CargaConsolidada\ResumenClienteCampos;
use Tests\TestCase;

class ResumenClienteCamposTest extends TestCase
{
    public function test_no_usa_telefono_como_documento()
    {
        $s = ResumenClienteCampos::sanitizar([
            'documento' => '51987654321',
            'whatsapp' => null,
            'correo' => 'null',
        ]);

        $this->assertNull($s['documento']);
        $this->assertSame('51987654321', $s['whatsapp']);
        $this->assertNull($s['correo']);
    }

    public function test_documento_igual_al_whatsapp_no_es_id()
    {
        $s = ResumenClienteCampos::sanitizar([
            'documento' => '987654321',
            'whatsapp' => '987654321',
            'correo' => 'ana@correo.com',
        ]);

        $this->assertNull($s['documento']);
        $this->assertSame('987654321', $s['whatsapp']);
        $this->assertSame('ana@correo.com', $s['correo']);
    }

    public function test_conserva_dni_y_ruc()
    {
        $dni = ResumenClienteCampos::sanitizar(['documento' => '12345678']);
        $this->assertSame('12345678', $dni['documento']);
        $this->assertSame('ID', $dni['tipo_documento']);

        $ruc = ResumenClienteCampos::sanitizar(['documento' => '20123456789']);
        $this->assertSame('20123456789', $ruc['documento']);
        $this->assertSame('RUC', $ruc['tipo_documento']);
    }
}
