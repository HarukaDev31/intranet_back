<?php

namespace Tests\Unit;

use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use Tests\TestCase;

class CodeSupplierPaisPrefixTest extends TestCase
{
    public function test_prefijo_pais_tres_letras()
    {
        $this->assertSame('ECU', CodeSupplierHelper::paisPrefix('Ecuador'));
        $this->assertSame('ARG', CodeSupplierHelper::paisPrefix('Argentina'));
        $this->assertSame('PER', CodeSupplierHelper::paisPrefix('Perú'));
        $this->assertSame('ECU', CodeSupplierHelper::paisPrefix('', 'EC'));
    }

    public function test_genera_pais_empresa_y_resto()
    {
        $this->assertSame(1, CodeSupplierHelper::socioEmpresaNumero(2));
        $this->assertSame(2, CodeSupplierHelper::socioEmpresaNumero(3));
        $this->assertSame(
            'ECU1-JUPE5-1',
            CodeSupplierHelper::generateWithPaisPrefix('Ecuador', 2, 'Juan Perez', 5, 1)
        );
        $this->assertSame(
            'ARG2-JUPE5-1',
            CodeSupplierHelper::generateWithPaisPrefix('Argentina', 3, 'Juan Perez', 5, 1)
        );
    }
}
