<?php

namespace Tests\Unit;

use App\Models\UsuarioMetodoPago;
use PHPUnit\Framework\TestCase;

class UsuarioMetodoPagoTest extends TestCase
{
    public function test_numero_local_quita_prefijo_peru_y_simbolos(): void
    {
        $this->assertSame('987654321', UsuarioMetodoPago::numeroLocal('+51 987 654 321'));
        $this->assertSame('987654321', UsuarioMetodoPago::numeroLocal('51987654321'));
        $this->assertSame('987654321', UsuarioMetodoPago::numeroLocal('987654321'));
    }

    public function test_numero_local_vacio_devuelve_null(): void
    {
        $this->assertNull(UsuarioMetodoPago::numeroLocal(null));
        $this->assertNull(UsuarioMetodoPago::numeroLocal(''));
    }
}
