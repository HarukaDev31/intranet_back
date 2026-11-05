<?php

namespace Tests\Feature;

use App\Exceptions\PagoProveedorException;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorCotizacionService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PagoProveedorCotizacionGuardarTest extends TestCase
{
    private const CORTE = 499;

    /** @var array<string, mixed>|null */
    private ?array $recibidos = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $autorizacion = $this->createMock(PagoProveedorAutorizacion::class);
        $autorizacion->method('exigirVentas')->willReturn(['id' => 1, 'nombre' => 'Ventas', 'area' => 'ventas']);
        $this->app->instance(PagoProveedorAutorizacion::class, $autorizacion);
    }

    public function test_put_por_multipart_entrega_la_proforma_con_los_datos(): void
    {
        $servicio = $this->createMock(PagoProveedorCotizacionService::class);
        $servicio->expects($this->once())->method('actualizar')
            ->with(7, $this->callback(fn (array $datos) => $this->capturar($datos)), $this->anything())
            ->willThrowException(new PagoProveedorException('corte', self::CORTE));
        $this->app->instance(PagoProveedorCotizacionService::class, $servicio);

        $this->post('/api/pago-proveedor/cotizaciones/7', ['_method' => 'PUT'] + $this->cuerpo(), ['Accept' => 'application/json'])
            ->assertStatus(self::CORTE);

        $this->assertInstanceOf(UploadedFile::class, $this->recibidos['proveedores'][0]['proforma'] ?? null);
        $this->assertSame('proforma.pdf', $this->recibidos['proveedores'][0]['proforma']->getClientOriginalName());
    }

    public function test_post_crea_con_datos_y_proforma_en_una_sola_peticion(): void
    {
        $servicio = $this->createMock(PagoProveedorCotizacionService::class);
        $servicio->expects($this->once())->method('crearConDatos')
            ->with('CO09261405', $this->callback(fn (array $datos) => $this->capturar($datos)), $this->anything())
            ->willThrowException(new PagoProveedorException('corte', self::CORTE));
        $this->app->instance(PagoProveedorCotizacionService::class, $servicio);

        $this->post('/api/pago-proveedor/cotizaciones', ['cod_cotizacion_cc' => 'CO09261405'] + $this->cuerpo(), ['Accept' => 'application/json'])
            ->assertStatus(self::CORTE);

        $this->assertInstanceOf(UploadedFile::class, $this->recibidos['proveedores'][0]['proforma'] ?? null);
    }

    public function test_rechaza_una_proforma_con_formato_no_permitido(): void
    {
        $servicio = $this->createMock(PagoProveedorCotizacionService::class);
        $servicio->expects($this->never())->method('actualizar');
        $this->app->instance(PagoProveedorCotizacionService::class, $servicio);

        $cuerpo = $this->cuerpo();
        $cuerpo['proveedores'][0]['proforma'] = UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload');

        $this->post('/api/pago-proveedor/cotizaciones/7', ['_method' => 'PUT'] + $cuerpo, ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proveedores.0.proforma']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function capturar(array $datos): bool
    {
        $this->recibidos = $datos;

        return true;
    }

    /**
     * Mismo formato que arma CotizacionForm.vue: booleanos como 1/0 y sin campos nulos.
     *
     * @return array<string, mixed>
     */
    private function cuerpo(): array
    {
        return [
            'tipo_cambio_confirmado' => '1',
            'proveedores' => [[
                'moneda' => 'RMB',
                'tipo_pago' => 'transferencia',
                'valor_carga_usd' => '100',
                'modalidad_abono' => 'total',
                'modalidad_pago' => 'total',
                'porcentaje_1' => '100',
                'porcentaje_2' => '0',
                'comision_bancaria' => '1',
                'comision_administrativa' => '0',
                'proforma' => UploadedFile::fake()->create('proforma.pdf', 10, 'application/pdf'),
            ]],
        ];
    }
}
