<?php

namespace Tests\Unit;

use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Prueba rápida de generateMassiveExcelPayrolls con datos tipo producción (logs 2026-04-10),
 * mockeando getMassiveExcelData, getFinalCotizacionExcelv2 y DB::table (sin plantilla ni PDF).
 *
 * Para Excel real + UPDATE en BD + boleta PDF véase
 * {@see \Tests\Feature\GenerateMassiveExcelPayrollsIntegrationTest}.
 */
class GenerateMassiveExcelPayrollsTest extends TestCase
{
    /** @var string Ruta relativa bajo public/storage donde el controlador busca el xlsx para el ZIP */
    private string $excelRelativePath = 'templates/cotizaciones_test/CotizacionPETER_TAYPE_TACAS_mock.xlsx';

    protected function tearDown(): void
    {
        $full = public_path('storage/' . $this->excelRelativePath);
        if (is_file($full)) {
            @unlink($full);
        }
        $dir = dirname($full);
        if (is_dir($dir) && count(glob($dir . '/*')) === 0) {
            @rmdir($dir);
        }
        parent::tearDown();
    }

    /**
     * Estructura devuelta por getMassiveExcelData, alineada a lo que sale en logs tras parsear la plantilla masiva.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sampleExcelDataFromLogs(): array
    {
        return [
            [
                'cliente' => [
                    'nombre' => 'PETER TAYPE TACAS',
                    'tipo' => 'NATURAL',
                    'dni' => '12345678',
                    'telefono' => '51900000000',
                    'productos' => [
                        [
                            'nombre' => 'ITEM EJEMPLO LOG',
                            'cantidad' => '2',
                            'precio_unitario' => '15.50',
                            'antidumping' => 0,
                            'valoracion' => 0,
                            'ad_valorem' => 0,
                            'percepcion' => 0.035,
                            'peso' => '10',
                            'cbm' => '0.05',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Fila que devuelve el JOIN de contenedor_consolidado_cotizacion (como en log "Coincidencia encontrada").
     */
    private function sampleCotizacionRowFromLogs(): object
    {
        return (object) [
            'id' => 9134,
            'tarifa' => '280.00',
            'nombre' => 'PETER TAYPE TACAS',
            'id_tipo_cliente' => 2,
            'tipoCliente' => 'ANTIGUO',
            'correo' => 'peter.pj.2306@gmail.com',
            'vol_selected' => 'volumen_china',
            'volumen' => '4.75',
            'volumen_china' => '4.75',
            'volumen_doc' => '4.74',
        ];
    }

    public function test_generate_massive_excel_payrolls_descarga_zip_con_mocks(): void
    {
        $idContenedor = 152;
        $cotRow = $this->sampleCotizacionRowFromLogs();

        $joinBuilder = \Mockery::mock();
        $joinBuilder->shouldReceive('join')->andReturnSelf();
        $joinBuilder->shouldReceive('select')->andReturnSelf();
        $joinBuilder->shouldReceive('where')->andReturnSelf();
        $joinBuilder->shouldReceive('whereNotNull')->andReturnSelf();
        $joinBuilder->shouldReceive('whereNull')->andReturnSelf();
        $joinBuilder->shouldReceive('whereExists')->andReturnSelf();
        $joinBuilder->shouldReceive('get')->once()->andReturn(collect([$cotRow]));

        $cotizacionFirstBuilder = \Mockery::mock();
        $cotizacionFirstBuilder->shouldReceive('where')->andReturnSelf();
        $cotizacionFirstBuilder->shouldReceive('first')->once()->andReturn(null);

        $cotizacionUpdateBuilder = \Mockery::mock();
        $cotizacionUpdateBuilder->shouldReceive('where')->andReturnSelf();
        $cotizacionUpdateBuilder->shouldReceive('update')->once()->andReturn(1);

        DB::shouldReceive('table')
            ->once()
            ->with(\Mockery::pattern('/contenedor_consolidado_cotizacion as cc$/'))
            ->andReturn($joinBuilder);

        DB::shouldReceive('table')
            ->twice()
            ->with('contenedor_consolidado_cotizacion')
            ->andReturn($cotizacionFirstBuilder, $cotizacionUpdateBuilder);

        $fullExcelPath = public_path('storage/' . $this->excelRelativePath);
        if (!is_dir(dirname($fullExcelPath))) {
            mkdir(dirname($fullExcelPath), 0777, true);
        }
        file_put_contents($fullExcelPath, 'fake xlsx content for zip');

        $finalExcelPayload = [
            'excel_file_name' => 'CotizacionPETER_TAYPE_TACAS_mock.xlsx',
            'excel_file_path' => $this->excelRelativePath,
            'id' => 9134,
            'cotizacion_final_url' => 'https://example.test/cotizacion.xlsx',
            'volumen_final' => 4.75,
            'monto_final' => 1000.00,
            'tarifa_final' => 280.00,
            'impuestos_final' => 100.00,
            'logistica_final' => 50.00,
            'fob_final' => 800.00,
            'peso_final' => 120.00,
        ];

        /** @var CotizacionFinalController|\PHPUnit\Framework\MockObject_MockObject $controller */
        $controller = $this->getMockBuilder(CotizacionFinalController::class)
            ->onlyMethods(['getMassiveExcelData', 'getFinalCotizacionExcelv2'])
            ->getMock();

        $controller->method('getMassiveExcelData')
            ->willReturnCallback(function ($file) {
                $this->assertNotNull($file);

                return $this->sampleExcelDataFromLogs();
            });

        $controller->method('getFinalCotizacionExcelv2')
            ->willReturnCallback(function ($objPHPExcel, $data, $idContainer) use ($idContenedor, $finalExcelPayload) {
                $this->assertSame($idContenedor, $idContainer);
                $this->assertSame('PETER TAYPE TACAS', $data['cliente']['nombre']);
                $this->assertSame(280.00, (float) $data['cliente']['tarifa']);
                $this->assertSame(4.75, (float) $data['cliente']['volumen']);
                $this->assertSame(9134, $data['id']);

                return $finalExcelPayload;
            });

        $upload = UploadedFile::fake()->create(
            'plantilla_masiva.xlsx',
            32,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $request = Request::create(
            '/api/carga-consolidada/contenedor/cotizacion-final/general/upload-plantilla-final',
            'POST',
            ['idContenedor' => $idContenedor],
            [],
            ['file' => $upload]
        );

        $response = $controller->generateMassiveExcelPayrolls($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        // El archivo temporal incluye timestamp; el nombre de descarga sugerido es Boletas_{id}.zip
        $this->assertStringStartsWith('Boletas_' . $idContenedor . '_', $response->getFile()->getFilename());
        $this->assertStringContainsString(
            'Boletas_' . $idContenedor . '.zip',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertGreaterThan(0, $response->getFile()->getSize());
    }
}
