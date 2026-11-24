<?php

namespace Tests\Unit;

use App\Models\Usuario;
use App\Support\Menu\PedidosCursoMenuFilter;
use PHPUnit\Framework\TestCase;

class PedidosCursoMenuFilterTest extends TestCase
{
    public function test_jefe_de_ventas_ve_curso(): void
    {
        $this->assertTrue(PedidosCursoMenuFilter::usuarioPuedeVerCurso(Usuario::ID_JEFE_VENTAS));
    }

    public function test_cotizador_autorizado_ve_curso(): void
    {
        $this->assertTrue(PedidosCursoMenuFilter::usuarioPuedeVerCurso(29409));
        $this->assertTrue(PedidosCursoMenuFilter::usuarioPuedeVerCurso(29170));
    }

    public function test_otro_cotizador_no_ve_curso(): void
    {
        $this->assertFalse(PedidosCursoMenuFilter::usuarioPuedeVerCurso(12345));
    }

    public function test_usuario_autorizado_recibe_menus_sin_filtrar(): void
    {
        $menus = [(object) ['ID_Menu' => 182, 'No_Menu' => 'Curso', 'Hijos' => []]];

        $this->assertSame($menus, PedidosCursoMenuFilter::apply($menus, 29409, 0));
    }
}
