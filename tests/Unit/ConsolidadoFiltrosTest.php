<?php

namespace Tests\Unit;

use App\Support\CargaConsolidada\ConsolidadoFiltros;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ConsolidadoFiltrosTest extends TestCase
{
    public function test_acepta_listas_comas_y_valor_unico(): void
    {
        $request = Request::create('/x', 'GET', [
            'anio' => ['2026', '2027', '2026'],
            'id_pais' => '3,4',
            'empresa' => 'GENUINO',
            'estado_china' => 'todos',
        ]);

        $this->assertSame([
            'anio' => ['2026', '2027'],
            'id_pais' => ['3', '4'],
            'empresa' => ['GENUINO'],
            'estado_china' => [],
        ], ConsolidadoFiltros::seleccion($request));
    }

    public function test_traduce_los_estados_en_ingles_del_almacen(): void
    {
        $request = Request::create('/x', 'GET', ['estado_china' => ['WAITING', 'receiving', 'PENDIENTE']]);

        $this->assertSame(['PENDIENTE', 'RECIBIENDO'], ConsolidadoFiltros::seleccion($request)['estado_china']);
    }

    public function test_alias_year_y_company(): void
    {
        $request = Request::create('/x', 'GET', ['year' => '2025', 'company' => 'X']);

        $seleccion = ConsolidadoFiltros::seleccion($request);
        $this->assertSame(['2025'], $seleccion['anio']);
        $this->assertSame(['X'], $seleccion['empresa']);
    }
}
