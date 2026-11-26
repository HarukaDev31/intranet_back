<?php

namespace Tests\Unit;

use App\Models\Usuario;
use PHPUnit\Framework\TestCase;

class CotizadorProspectosAlcanceTest extends TestCase
{
    public function test_jazmin_ve_prospectos_de_todos_los_vendedores(): void
    {
        $this->assertTrue(Usuario::cotizadorVeProspectosDeTodos(29151));
        $this->assertTrue(Usuario::cotizadorVeProspectosDeTodos('29151'));
    }

    public function test_jefe_y_milagros_siguen_viendo_todos(): void
    {
        $this->assertTrue(Usuario::cotizadorVeProspectosDeTodos(Usuario::ID_JEFE_VENTAS));
        $this->assertTrue(Usuario::cotizadorVeProspectosDeTodos(28911));
    }

    public function test_otro_cotizador_solo_ve_los_suyos(): void
    {
        $this->assertFalse(Usuario::cotizadorVeProspectosDeTodos(29409));
        $this->assertFalse(Usuario::cotizadorVeProspectosDeTodos(29170));
        $this->assertFalse(Usuario::cotizadorVeProspectosDeTodos(1));
        $this->assertFalse(Usuario::cotizadorVeProspectosDeTodos(null));
    }
}
