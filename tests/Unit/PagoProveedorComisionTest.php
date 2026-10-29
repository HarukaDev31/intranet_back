<?php

namespace Tests\Unit;

use App\Services\PagoProveedor\PagoProveedorComisionService;
use App\Services\PagoProveedor\PagoProveedorMensajeService;
use App\Services\PagoProveedor\PagoProveedorVigenciaService;
use App\Services\PagoProveedor\TarifaPagoProveedor;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PagoProveedorComisionTest extends TestCase
{
    private PagoProveedorComisionService $comisiones;

    private TarifaPagoProveedor $tarifa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comisiones = new PagoProveedorComisionService();
        $this->tarifa = new TarifaPagoProveedor();
    }

    public function test_tramos_usd(): void
    {
        $this->assertSame(100.0, $this->bancariaDe('USD', 2999));
        $this->assertSame(120.0, $this->bancariaDe('USD', 3000));
        $this->assertSame(120.0, $this->bancariaDe('USD', 9000));
        $this->assertSame(150.0, $this->bancariaDe('USD', 9001));
    }

    public function test_tramos_rmb(): void
    {
        $this->assertSame(50.0, $this->bancariaDe('RMB', 2999));
        $this->assertSame(90.0, $this->bancariaDe('RMB', 3000));
        $this->assertSame(90.0, $this->bancariaDe('RMB', 9000));
        $this->assertSame(120.0, $this->bancariaDe('RMB', 9001));
    }

    public function test_bancaria_rmb_la_lleva_el_primero(): void
    {
        $calculo = $this->comisiones->calcular([
            $this->linea('RMB', 2000, 1),
            $this->linea('RMB', 2000, 2),
        ], $this->tarifa);

        $this->assertSame(90.0, $calculo['proveedores'][0]['bancaria']);
        $this->assertSame(0.0, $calculo['proveedores'][1]['bancaria']);
        $this->assertSame('Incluida en el Proveedor 1', $calculo['proveedores'][1]['etiqueta_bancaria']);
        $this->assertNull($calculo['proveedores'][0]['etiqueta_bancaria']);
    }

    public function test_al_quitar_el_primero_la_bancaria_pasa_al_siguiente(): void
    {
        $calculo = $this->comisiones->calcular([
            $this->linea('RMB', 2000, 2),
            $this->linea('RMB', 2000, 3),
        ], $this->tarifa);

        $this->assertSame(90.0, $calculo['proveedores'][0]['bancaria']);
        $this->assertSame('Incluida en el Proveedor 2', $calculo['proveedores'][1]['etiqueta_bancaria']);
    }

    public function test_usd_no_entra_al_agrupamiento(): void
    {
        $calculo = $this->comisiones->calcular([
            $this->linea('RMB', 2000, 1),
            $this->linea('USD', 2000, 2),
        ], $this->tarifa);

        $this->assertSame(50.0, $calculo['proveedores'][0]['bancaria']);
        $this->assertSame(100.0, $calculo['proveedores'][1]['bancaria']);
        $this->assertNull($calculo['proveedores'][1]['etiqueta_bancaria']);
    }

    public function test_administrativa_doble_en_dos_pagos_rmb(): void
    {
        $linea = $this->linea('RMB', 5000, 1);
        $linea['modalidad_abono'] = 'total';
        $linea['modalidad_pago'] = 'fraccionado';
        $linea['porcentaje_1'] = 60;
        $linea['porcentaje_2'] = 40;
        $linea['comision_administrativa'] = true;

        $calculo = $this->comisiones->calcular([$linea], $this->tarifa);
        $proveedor = $calculo['proveedores'][0];

        $this->assertSame(90.0, $proveedor['bancaria']);
        $this->assertSame(60.0, $proveedor['administrativa']);
        $this->assertSame(5150.0, $proveedor['total']);
        $this->assertCount(2, $proveedor['pagos_proveedor']);
        $this->assertSame(3000.0, $proveedor['pagos_proveedor'][0]['carga']);
    }

    public function test_cuotas_cobran_comision_en_cada_parte(): void
    {
        $linea = $this->linea('USD', 4000, 1);
        $linea['modalidad_abono'] = 'fraccionado';
        $linea['modalidad_pago'] = 'total';
        $linea['porcentaje_1'] = 50;
        $linea['porcentaje_2'] = 50;

        $calculo = $this->comisiones->calcular([$linea], $this->tarifa);
        $proveedor = $calculo['proveedores'][0];

        $this->assertSame('fraccionado', $proveedor['modalidad_pago']);
        $this->assertSame(100.0, $proveedor['cuotas'][0]['bancaria']);
        $this->assertSame(100.0, $proveedor['cuotas'][1]['bancaria']);
        $this->assertSame(2100.0, $proveedor['total_ahora']);
        $this->assertSame(4200.0, $calculo['total']);
    }

    public function test_abono_fraccionado_y_pago_total_cobra_la_bancaria_una_vez(): void
    {
        $linea = $this->linea('RMB', 4000, 1);
        $linea['modalidad_abono'] = 'fraccionado';
        $linea['modalidad_pago'] = 'total';
        $linea['porcentaje_1'] = 50;
        $linea['porcentaje_2'] = 50;

        $calculo = $this->comisiones->calcular([$linea], $this->tarifa);
        $proveedor = $calculo['proveedores'][0];

        $this->assertSame('total', $proveedor['modalidad_pago']);
        $this->assertSame(90.0, $proveedor['bancaria']);
        $this->assertSame(90.0, $proveedor['cuotas'][0]['bancaria']);
        $this->assertSame(0.0, $proveedor['cuotas'][1]['bancaria']);
        $this->assertSame(2090.0, $proveedor['total_ahora']);
    }

    public function test_vigencia_de_viernes_salta_el_fin_de_semana(): void
    {
        $servicio = new PagoProveedorVigenciaService();
        $inicio = Carbon::parse('2026-10-30 10:00:00', PagoProveedorVigenciaService::ZONA);
        $fin = $servicio->sumarHorasHabiles($inicio, 48);

        $this->assertSame('2026-11-03 10:00:00', $fin->format('Y-m-d H:i:s'));
        $this->assertFalse($servicio->estaVencida($fin, Carbon::parse('2026-11-03 09:59:00', PagoProveedorVigenciaService::ZONA)));
        $this->assertTrue($servicio->estaVencida($fin, $fin));
    }

    public function test_mensaje_de_varios_proveedores_no_lleva_tipo_de_cambio_ni_codigo_de_solicitud(): void
    {
        $calculo = $this->comisiones->calcular([
            $this->linea('RMB', 2000, 1),
            $this->linea('USD', 2000, 2),
        ], $this->tarifa);

        $texto = (new PagoProveedorMensajeService())->resumen('María', $calculo, [
            ['code_supplier' => 'MATO19-1', 'nombre_proveedor' => '', 'producto' => 'Zapatillas'],
            ['code_supplier' => 'MATO19-2', 'nombre_proveedor' => 'Li', 'producto' => 'Bolsos'],
        ]);

        $this->assertStringContainsString('Hola, María', $texto);
        $this->assertStringContainsString('💰 Total a pagar:', $texto);
        $this->assertStringContainsString('PDF', $texto);
        $this->assertStringNotContainsString('T.C', $texto);
        $this->assertStringNotContainsString('CN', $texto);
        $this->assertStringNotContainsString('PE', $texto);
    }

    public function test_mensaje_con_cuotas_usa_total_a_pagar_ahora(): void
    {
        $linea = $this->linea('USD', 4000, 1);
        $linea['modalidad_abono'] = 'fraccionado';
        $calculo = $this->comisiones->calcular([$linea], $this->tarifa);

        $texto = (new PagoProveedorMensajeService())->resumen('María', $calculo, [
            ['code_supplier' => 'MATO19-1', 'nombre_proveedor' => '', 'producto' => 'Zapatillas'],
        ]);

        $this->assertStringContainsString('Total a pagar ahora', $texto);
        $this->assertStringContainsString('➡ Comisión bancaria:', $texto);
        $this->assertStringContainsString('Proveedor 1: MATO19-1', $texto);
    }

    private function bancariaDe(string $moneda, float $valor): float
    {
        $calculo = $this->comisiones->calcular([
            $this->linea($moneda, $valor, 1),
        ], $this->tarifa);

        return $calculo['proveedores'][0]['bancaria'];
    }

    /**
     * @return array<string, mixed>
     */
    private function linea(string $moneda, float $valor, int $orden): array
    {
        return [
            'orden' => $orden,
            'moneda' => $moneda,
            'valor_carga_usd' => $valor,
            'modalidad_abono' => 'total',
            'modalidad_pago' => 'total',
            'porcentaje_1' => 100,
            'porcentaje_2' => 0,
            'comision_bancaria' => true,
            'comision_administrativa' => false,
        ];
    }
}
