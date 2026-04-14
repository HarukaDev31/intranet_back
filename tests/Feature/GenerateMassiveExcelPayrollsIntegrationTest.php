<?php

namespace Tests\Feature;

use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Integración: generateMassiveExcelPayrolls con getFinalCotizacionExcelv2 real,
 * escritura del xlsx en public/storage, UPDATE en contenedor_consolidado_cotizacion
 * y generación de boleta PDF (generateBoleta vía reflexión).
 *
 * Requiere public/assets/templates/Boleta_Template.xlsx en el entorno local (no suele estar en git).
 *
 * Sincronía con el controlador y plantillas:
 * - Sí: este test ejecuta el código real de getFinalCotizacionExcelv2 y generateBoleta; si cambias
 *   Boleta_Template.xlsx, PLANTILLA_COTIZACION_FINAL.html o la lógica en CotizacionFinalController,
 *   el test usará esa versión sin tocar el test (mismas rutas que en producción).
 * - No automático: getMassiveExcelData sigue mockeado; si el Excel masivo o su parseo exigen nuevos
 *   campos en el array de clientes/productos, actualiza sampleExcelPayloadFromLogs().
 *
 * Casos base: (1) un ítem sin antidumping, (2) cinco ítems con antidumping en extremos, (3) cuatro ítems sin AD.
 * Delivery/montacargas: (4) 8 ítems + importes BD sin AD (K42/K43), (5) 6 ítems + AD (K43/K44), (6) 7 ítems HTML/PDF, (7) 10 ítems suma montacarga, (8) 6 ítems solo monta, (9) 5 ítems sin líneas BD (filas ocultas).
 * Hoja 1 (layout vigente):
 * - Sin AD: B20..B23 = AD/ISC/IGV/IPM, B24 SUB TOTAL, B25 vacío, B26 PERCEPCION, B27 TOTAL.
 * - Con AD: B24 = ANTIDUMPING, B25 SUB TOTAL, B26 vacío, B27 PERCEPCION, B28 TOTAL.
 * Cada uno genera Excel + boleta PDF en public/storage/temp_integracion_boleta/ con nombre distinto.
 *
 * Cada test escribe en stderr: “Caso: …”, URL codificada del Excel y URL codificada de la boleta (APP_URL).
 * KEEP_INTEGRATION_TEST_ARTIFACTS=1 evita borrar esos archivos al terminar para abrirlos en el navegador.
 *
 * Para conservar archivos y abrir URLs en el navegador tras el test:
 *   KEEP_INTEGRATION_TEST_ARTIFACTS=1 ./vendor/bin/phpunit tests/Feature/GenerateMassiveExcelPayrollsIntegrationTest.php
 * Ajusta APP_URL (o phpunit.xml env) al mismo host/puerto que uses (ej. http://localhost:8001).
 *
 * @group integration
 */
class GenerateMassiveExcelPayrollsIntegrationTest extends TestCase
{
    /** @var string */
    private $originalDbDefault;

    /** @var bool */
    private $sqliteActive = false;

    /** @var array<int, string> */
    private $createdAssetFiles = [];

    /** @var array<int, string> Archivos extra a borrar en tearDown (ej. PDF de resumen) */
    private $tempOutputFiles = [];

    /** @var int */
    private $idContenedor = 152;

    /** @var int */
    private $idCotizacion = 9134;

    protected function setUp(): void
    {
        parent::setUp();

        $template = public_path('assets/templates/Boleta_Template.xlsx');
        if (!is_file($template)) {
            $this->markTestSkipped(
                'Falta la plantilla Boleta_Template.xlsx en public/assets/templates/. ' .
                'Cópiala desde tu entorno donde generes cotizaciones finales para ejecutar este test.'
            );
        }

        $this->ensureBoletaStaticAssets();
        $this->switchToSqliteMemory();
        $this->createSchemaAndSeed();
    }

    protected function tearDown(): void
    {
        if (!$this->keepIntegrationTestArtifacts()) {
            foreach ($this->tempOutputFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $this->cleanupGeneratedCotizacionArtifacts();
            $this->removePublicBoletaIntegrationDirIfEmpty();
        }
        $this->tempOutputFiles = [];
        if ($this->sqliteActive) {
            $this->restoreDatabaseConnection();
        }
        $this->removeCreatedAssetFiles();
        parent::tearDown();
    }

    /**
     * Si es true, no se borran Excel/PDF/ZIP temporales para poder abrir las URLs en el navegador.
     */
    private function keepIntegrationTestArtifacts(): bool
    {
        $v = getenv('KEEP_INTEGRATION_TEST_ARTIFACTS');

        return $v === '1' || $v === 'true';
    }

    public function test_caso_1_un_item_sin_antidumping_excel_bd_boleta(): void
    {
        $caso = 'Caso 1 — Un ítem, sin antidumping (payload tipo log); B26 sin rotular ANTIDUMPING.';

        $response = $this->invocarGenerateMassiveExcelPayrolls($this->sampleExcelPayloadFromLogs(), 'plantilla_masiva.xlsx');
        $this->assertZipResponseValido($response);
        $zipPath = $response->getFile()->getRealPath() ?: $response->getFile()->getPathname();

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row, 'Debe existir la cotización sembrada.');
        $this->assertNotEmpty($row->cotizacion_final_url, 'Debe persistir cotizacion_final_url tras el flujo.');
        $this->assertEquals('COTIZADO', $row->estado_cotizacion_final);

        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel, 'El Excel generado debe existir en public/storage.');

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 1, false, null);

        [$boletaPdfPath, $publicBoletaPath] = $this->generarBoletaYPublicarEnStorage($fullExcel, 'caso1_sin_ad');

        $rowFinal = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertFlujoCompletoPersistenciaYArtefactos($rowFinal, $zipPath, $fullExcel, $boletaPdfPath, $publicBoletaPath);
        $this->imprimirCasoYUrlsCodificadas($caso, $rowFinal, $publicBoletaPath);
    }

    public function test_caso_2_cinco_items_antidumping_extremos_excel_bd_boleta(): void
    {
        $caso = 'Caso 2 — Cinco ítems, antidumping en ítem 1 y 5 (B24/K24 con ANTIDUMPING).';

        $payload = $this->samplePayloadMultiplesProductosConAntidumpingEnIndices(5, [1, 5]);
        $response = $this->invocarGenerateMassiveExcelPayrolls($payload, 'plantilla_caso2_ad.xlsx');
        $this->assertZipResponseValido($response);
        $zipPath = $response->getFile()->getRealPath() ?: $response->getFile()->getPathname();

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->cotizacion_final_url);
        $this->assertEquals('COTIZADO', $row->estado_cotizacion_final);

        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel);

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 5, true);
        $this->assertCamposNumericosCotizacionFinalRazonables($row);

        [$boletaPdfPath, $publicBoletaPath] = $this->generarBoletaYPublicarEnStorage($fullExcel, 'caso2_mult_ad');

        $this->assertArtefactosZipExcelBoleta($zipPath, $fullExcel, $boletaPdfPath, $publicBoletaPath);
        $this->assertCotizacionSembradaCoherente($row);
        $this->imprimirCasoYUrlsCodificadas($caso, $row, $publicBoletaPath);
    }

    public function test_caso_3_cuatro_items_sin_antidumping_excel_bd_boleta(): void
    {
        $caso = 'Caso 3 — Cuatro ítems, sin antidumping (B26 sin ANTIDUMPING).';

        $payload = $this->samplePayloadMultiplesProductosConAntidumpingEnIndices(4, []);
        $response = $this->invocarGenerateMassiveExcelPayrolls($payload, 'plantilla_caso3.xlsx');
        $this->assertZipResponseValido($response);
        $zipPath = $response->getFile()->getRealPath() ?: $response->getFile()->getPathname();

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->cotizacion_final_url);
        $this->assertEquals('COTIZADO', $row->estado_cotizacion_final);

        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel);

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 4, false);
        $this->assertCamposNumericosCotizacionFinalRazonables($row);

        [$boletaPdfPath, $publicBoletaPath] = $this->generarBoletaYPublicarEnStorage($fullExcel, 'caso3_mult_sin_ad');

        $this->assertArtefactosZipExcelBoleta($zipPath, $fullExcel, $boletaPdfPath, $publicBoletaPath);
        $this->assertCotizacionSembradaCoherente($row);
        $this->imprimirCasoYUrlsCodificadas($caso, $row, $publicBoletaPath);
    }

    /**
     * Con líneas MONTACARGA + DELIVERY en BD: K42/K43 (sin AD) deben reflejar importes y filas visibles.
     */
    public function test_delivery_servicio_con_importes_sin_antidumping_filas_k(): void
    {
        $caso = 'Delivery servicio — sin AD: montacargas + delivery en K42/K43.';

        $this->seedDeliveryServicioLines(100.25, 200.5);

        $response = $this->invocarGenerateMassiveExcelPayrolls($this->sampleExcelPayloadServicioMultiplesItems(8), 'plantilla_delivery_sin_ad.xlsx');
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row);
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel);

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 8, false, 'ITEM SERVICIO LOG');
        $this->assertBoletaMontacargaDelivery(
            $spreadsheet,
            false,
            100.25,
            200.5,
            'Sin antidumping: montacargas en fila 42, delivery en 43.'
        );
        $this->assertBoletaHtmlPdfIncluyeMontacargaDelivery($fullExcel, 100.25, 200.5);

        $this->imprimirCasoYUrlsCodificadas($caso, $row, '');
    }

    /**
     * Con líneas en BD y antidumping: K43/K44 deben reflejar importes (fila extra de recargos).
     */
    public function test_delivery_servicio_con_importes_con_antidumping_filas_k(): void
    {
        $caso = 'Delivery servicio — con AD: montacargas + delivery en K43/K44.';

        $this->seedDeliveryServicioLines(88.88, 177.77);

        $payload = $this->samplePayloadMultiplesProductosConAntidumpingEnIndices(6, [1, 6]);
        $response = $this->invocarGenerateMassiveExcelPayrolls($payload, 'plantilla_delivery_con_ad.xlsx');
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row);
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel);

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 6, true);
        $this->assertBoletaMontacargaDelivery(
            $spreadsheet,
            true,
            88.88,
            177.77,
            'Con antidumping: montacargas en fila 43, delivery en 44.'
        );
        $this->assertBoletaHtmlPdfIncluyeMontacargaDelivery($fullExcel, 88.88, 177.77);

        $this->imprimirCasoYUrlsCodificadas($caso, $row, '');
    }

    /**
     * HTML de boleta (mismo que alimenta al PDF) incluye filas RECARGO con montos cuando hay importes en Excel.
     */
    public function test_delivery_servicio_html_boleta_coincide_con_excel_sin_ad(): void
    {
        $this->seedDeliveryServicioLines(301.12, 402.34);

        $response = $this->invocarGenerateMassiveExcelPayrolls($this->sampleExcelPayloadServicioMultiplesItems(7), 'plantilla_delivery_html_sin_ad.xlsx');
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertExcelMultiplesItemsYAntidumping(IOFactory::load($fullExcel), 7, false, 'ITEM SERVICIO LOG');
        $this->assertBoletaHtmlPdfIncluyeMontacargaDelivery($fullExcel, 301.12, 402.34);

        [$pdfPath] = $this->generarBoletaYPublicarEnStorage($fullExcel, 'delivery_html_sin_ad');
        $this->tempOutputFiles[] = $pdfPath;
        $this->assertPdfBoletaGeneradoDesdeHtml($pdfPath);
    }

    /**
     * Caso integral: antidumping + ad valorem + isc% + servicios.
     * Verifica que ISC% del payload alimente el cálculo de ISC valor en Excel.
     */
    public function test_flujo_integral_tributos_y_servicios_incluye_isc_percent(): void
    {
        $caso = 'Caso integral ISC% — antidumping + ad valorem + isc + servicios + boleta.';
        $this->seedDeliveryServicioLines(55.50, 66.60);

        $payload = [[
            'cliente' => [
                'nombre' => 'PETER TAYPE TACAS',
                'tipo' => 'NATURAL',
                'dni' => '12345678',
                'telefono' => '51900000000',
                'productos' => [
                    [
                        'nombre' => 'ITEM FULL 1',
                        'cantidad' => '2',
                        'precio_unitario' => '18.90',
                        'antidumping' => 2.4,
                        'valoracion' => 14.5,
                        'ad_valorem' => 0.06,
                        'isc_percent' => 0.11,
                        'percepcion' => 0.035,
                        'peso' => '12',
                        'cbm' => '0.07',
                    ],
                    [
                        'nombre' => 'ITEM FULL 2',
                        'cantidad' => '3',
                        'precio_unitario' => '9.30',
                        'antidumping' => 0,
                        'valoracion' => 10.2,
                        'ad_valorem' => 0.06,
                        'isc_percent' => 0.11,
                        'percepcion' => 0.035,
                        'peso' => '8',
                        'cbm' => '0.05',
                    ],
                ],
            ],
        ]];

        $response = $this->invocarGenerateMassiveExcelPayrolls($payload, 'plantilla_integral_isc_ad_servicios.xlsx');
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row);
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel);

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 2, true, 'ITEM FULL');
        $this->assertBoletaMontacargaDelivery($spreadsheet, true, 55.50, 66.60, 'Con AD: servicios en filas 43/44');
        $this->assertBoletaHtmlPdfIncluyeMontacargaDelivery($fullExcel, 55.50, 66.60);

        $main = $spreadsheet->getSheet(0);
        $this->assertGreaterThan(0, (float) $main->getCell('K20')->getCalculatedValue(), 'AD VALOREM (valor) debe ser > 0');
        $this->assertGreaterThan(0, (float) $main->getCell('K21')->getCalculatedValue(), 'ISC (valor) debe ser > 0');
        $this->assertGreaterThan(0, (float) $main->getCell('K24')->getCalculatedValue(), 'ANTIDUMPING (valor) debe ser > 0');

        $tributos = $this->findTributosSheet($spreadsheet);
        $this->assertStringContainsStringIgnoringCase('ISC', (string) $tributos->getCell('B30')->getValue());
        $this->assertEqualsWithDelta(0.11, (float) $tributos->getCell('C29')->getCalculatedValue(), 0.0001, 'ISC% debe provenir del archivo/payload');
        $this->assertGreaterThan(0, (float) $tributos->getCell('C30')->getCalculatedValue(), 'ISC valor debe calcularse > 0');

        [$boletaPdfPath, $publicBoletaPath] = $this->generarBoletaYPublicarEnStorage($fullExcel, 'integral_isc_ad_servicios');
        $this->assertFileExists($boletaPdfPath);
        $this->assertFileExists($publicBoletaPath);
        $this->imprimirCasoYUrlsCodificadas($caso, $row, $publicBoletaPath);
    }

    /**
     * Varias líneas MONTACARGA en BD se suman en K (sin AD).
     */
    public function test_delivery_servicio_suma_multiples_lineas_montacarga_en_excel(): void
    {
        foreach ([40.0, 35.5, 24.5] as $imp) {
            DB::table('contenedor_consolidado_cotizacion_delivery_servicio')->insert([
                'id_cotizacion' => $this->idCotizacion,
                'tipo_servicio' => 'MONTACARGA',
                'importe' => $imp,
            ]);
        }
        DB::table('contenedor_consolidado_cotizacion_delivery_servicio')->insert([
            'id_cotizacion' => $this->idCotizacion,
            'tipo_servicio' => 'DELIVERY',
            'importe' => 15.0,
        ]);

        $response = $this->invocarGenerateMassiveExcelPayrolls(
            $this->samplePayloadMultiplesProductosConAntidumpingEnIndices(10, []),
            'plantilla_delivery_suma.xlsx'
        );
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 10, false);
        $this->assertBoletaMontacargaDelivery($spreadsheet, false, 100.0, 15.0, 'Suma 40+35.5+24.5=100');
        $this->assertBoletaHtmlPdfIncluyeMontacargaDelivery($fullExcel, 100.0, 15.0);
    }

    /**
     * Solo MONTACARGA en BD: fila delivery oculta; HTML/PDF no deben mostrar segundo monto delivery.
     */
    public function test_delivery_servicio_solo_montacarga_sin_fila_delivery_visible(): void
    {
        DB::table('contenedor_consolidado_cotizacion_delivery_servicio')->insert([
            'id_cotizacion' => $this->idCotizacion,
            'tipo_servicio' => 'MONTACARGA',
            'importe' => 77.77,
        ]);

        $response = $this->invocarGenerateMassiveExcelPayrolls($this->sampleExcelPayloadServicioMultiplesItems(6), 'plantilla_solo_monta.xlsx');
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 6, false, 'ITEM SERVICIO LOG');
        $main = $spreadsheet->getSheet(0);
        $this->assertSame(false, $main->getRowDimension(43)->getVisible());

        $html = $this->getBoletaHtmlDesdeExcel($fullExcel);
        $this->assertStringContainsString('77.77', $html);
        $this->assertStringNotContainsString('177.77', $html);

        [$pdfPath] = $this->generarBoletaYPublicarEnStorage($fullExcel, 'solo_monta_sin_delivery');
        $this->tempOutputFiles[] = $pdfPath;
        $this->assertPdfBoletaGeneradoDesdeHtml($pdfPath);
    }

    /**
     * Sin filas en contenedor_consolidado_cotizacion_delivery_servicio: importes 0 → filas ocultas (concepto no visible).
     */
    public function test_delivery_servicio_sin_lineas_filas_ocultas_en_boleta(): void
    {
        $caso = 'Delivery servicio — sin líneas en BD: filas montacargas/delivery ocultas.';

        $response = $this->invocarGenerateMassiveExcelPayrolls($this->sampleExcelPayloadServicioMultiplesItems(5), 'plantilla_delivery_vacio.xlsx');
        $this->assertZipResponseValido($response);

        $row = DB::table('contenedor_consolidado_cotizacion')->where('id', $this->idCotizacion)->first();
        $this->assertNotNull($row);
        $fullExcel = public_path('storage/' . $row->cotizacion_final_url);
        $this->assertFileExists($fullExcel);

        $spreadsheet = IOFactory::load($fullExcel);
        $this->assertExcelMultiplesItemsYAntidumping($spreadsheet, 5, false, 'ITEM SERVICIO LOG');
        $main = $spreadsheet->getSheet(0);
        // Sin AD: filas 42 y 43
        $this->assertSame(false, $main->getRowDimension(42)->getVisible(), 'Sin montacargas la fila 42 debe ocultarse');
        $this->assertSame(false, $main->getRowDimension(43)->getVisible(), 'Sin delivery la fila 43 debe ocultarse');

        $html = $this->getBoletaHtmlDesdeExcel($fullExcel);
        $this->assertStringNotContainsString('{{row_montacargas}}', $html, 'Placeholders deben sustituirse en HTML');
        $this->assertStringNotContainsString('{{row_delivery}}', $html);
        $this->assertStringNotContainsString('100.25', $html, 'Sin líneas BD no debe aparecer un monto típico de otros tests en filas recargo');

        $this->imprimirCasoYUrlsCodificadas($caso, $row, '');
    }

    private function seedDeliveryServicioLines(float $importeMontacargas, float $importeDelivery): void
    {
        DB::table('contenedor_consolidado_cotizacion_delivery_servicio')->insert([
            [
                'id_cotizacion' => $this->idCotizacion,
                'tipo_servicio' => 'MONTACARGA',
                'importe' => $importeMontacargas,
            ],
            [
                'id_cotizacion' => $this->idCotizacion,
                'tipo_servicio' => 'DELIVERY',
                'importe' => $importeDelivery,
            ],
        ]);
    }

    /**
     * @param  float  $esperadoMonta  Importe esperado en K (fila según layout).
     * @param  float  $esperadoDeliv
     */
    private function assertBoletaMontacargaDelivery(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        bool $conAntidumping,
        float $esperadoMonta,
        float $esperadoDeliv,
        string $mensajeContexto
    ): void {
        $main = $spreadsheet->getSheet(0);
        $rowM = $conAntidumping ? 43 : 42;
        $rowD = $conAntidumping ? 44 : 43;

        $kM = $main->getCell('K' . $rowM)->getCalculatedValue();
        $kD = $main->getCell('K' . $rowD)->getCalculatedValue();

        $this->assertEqualsWithDelta(
            $esperadoMonta,
            is_numeric($kM) ? (float) $kM : 0.0,
            0.08,
            'Montacargas K' . $rowM . ' — ' . $mensajeContexto
        );
        $this->assertEqualsWithDelta(
            $esperadoDeliv,
            is_numeric($kD) ? (float) $kD : 0.0,
            0.08,
            'Delivery K' . $rowD . ' — ' . $mensajeContexto
        );

        $this->assertNotSame(false, $main->getRowDimension($rowM)->getVisible(), 'Fila montacargas visible con importe > 0');
        $this->assertNotSame(false, $main->getRowDimension($rowD)->getVisible(), 'Fila delivery visible con importe > 0');
    }

    private function findTributosSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        for ($i = 0; $i < $spreadsheet->getSheetCount(); $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $label = strtoupper(trim((string) $sheet->getCell('B30')->getValue()));
            if (str_contains($label, 'ISC')) {
                return $sheet;
            }
        }

        return $spreadsheet->getSheet(1);
    }

    private function getBoletaHtmlDesdeExcel(string $fullExcelPath): string
    {
        $spreadsheet = IOFactory::load($fullExcelPath);
        $method = new ReflectionMethod(CotizacionFinalController::class, 'buildCotizacionFinalBoletaFilledHtml');
        $method->setAccessible(true);

        return $method->invoke(new CotizacionFinalController(), $spreadsheet);
    }

    /**
     * Comprueba que el HTML de boleta (DomPDF) incluye montos formateados en el bloque resumen (mismas celdas K que Excel).
     */
    private function assertBoletaHtmlPdfIncluyeMontacargaDelivery(
        string $fullExcelPath,
        float $esperadoMonta,
        float $esperadoDeliv
    ): void {
        $html = $this->getBoletaHtmlDesdeExcel($fullExcelPath);
        $fmtM = number_format($esperadoMonta, 2, '.', ',');
        $fmtD = number_format($esperadoDeliv, 2, '.', ',');
        $this->assertStringContainsString($fmtM, $html, 'HTML boleta debe incluir monto montacargas');
        $this->assertStringContainsString($fmtD, $html, 'HTML boleta debe incluir monto delivery');
        $this->assertStringContainsString('RECARGO', $html, 'Etiqueta de fila recargo (desde celda B de la plantilla)');
        $this->assertStringNotContainsString('{{row_montacargas}}', $html);
        $this->assertStringNotContainsString('{{row_delivery}}', $html);
    }

    /**
     * DomPDF suele comprimir streams: no buscar texto en bruto. El HTML ya valida montos/RECARGO.
     */
    private function assertPdfBoletaGeneradoDesdeHtml(string $pdfPath): void
    {
        $this->assertFileExists($pdfPath);
        $raw = (string) file_get_contents($pdfPath);
        $this->assertGreaterThan(4000, strlen($raw), 'PDF debe tener cuerpo no trivial');
        $this->assertStringStartsWith('%PDF-', $raw, 'Cabecera PDF válida (DomPDF)');
    }

    /**
     * Hoja 0:
     * - Con antidumping: B24/K24 contiene ANTIDUMPING y monto > 0.
     * - Sin antidumping: B24 es SUB TOTAL y B26 PERCEPCION (no ANTIDUMPING).
     * Hoja cálculos (índice 1): nombres en fila 5, columnas C+.
     *
     * @param  string|null  $tituloDebeContener  Si no es null, cada título de ítem debe contener este fragmento (ej. "ITEM TEST").
     */
    private function assertExcelMultiplesItemsYAntidumping(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        int $cantidadProductos,
        bool $esperaAntidumping,
        ?string $tituloDebeContener = 'ITEM TEST'
    ): void {
        $this->assertGreaterThanOrEqual(2, $spreadsheet->getSheetCount(), 'Debe existir hoja principal y hoja de cálculos.');

        $main = $spreadsheet->getSheet(0);
        $b24 = (string) $main->getCell('B24')->getValue();
        $b26 = (string) $main->getCell('B26')->getValue();
        if ($esperaAntidumping) {
            $this->assertStringContainsStringIgnoringCase('ANTIDUMPING', $b24, 'Con antidumping, B24 debe rotular ANTIDUMPING.');
            $k24 = $main->getCell('K24')->getCalculatedValue();
            $this->assertIsNumeric($k24, 'Con antidumping, K24 debe ser numérico (monto).');
            $this->assertGreaterThan(0, (float) $k24, 'Con antidumping, K24 debe ser > 0.');
        } else {
            $this->assertStringContainsStringIgnoringCase('SUB', $b24, 'Sin antidumping, B24 debe ser SUB TOTAL.');
            $this->assertStringNotContainsStringIgnoringCase('ANTIDUMPING', $b26, 'Sin antidumping, B26 no debe rotular ANTIDUMPING.');
        }

        $calc = $spreadsheet->getSheet(1);
        $rowNombres = 5;
        for ($i = 0; $i < $cantidadProductos; $i++) {
            $col = Coordinate::stringFromColumnIndex(3 + $i);
            $titulo = (string) $calc->getCell($col . $rowNombres)->getValue();
            $this->assertNotSame(
                '',
                trim($titulo),
                "Hoja cálculos: se esperaba nombre de producto en {$col}{$rowNombres} (ítem " . ($i + 1) . ' de ' . $cantidadProductos . ').'
            );
            if ($tituloDebeContener !== null && $tituloDebeContener !== '') {
                $this->assertStringContainsString(
                    $tituloDebeContener,
                    $titulo,
                    "El título del ítem " . ($i + 1) . ' debe contener el fragmento esperado del payload.'
                );
            }
        }

        $colTotal = Coordinate::stringFromColumnIndex(3 + $cantidadProductos);
        $this->assertStringContainsStringIgnoringCase(
            'total',
            (string) $calc->getCell($colTotal . $rowNombres)->getValue(),
            "La columna inmediatamente posterior al último ítem ({$colTotal}{$rowNombres}) debe titularse Total."
        );
    }

    /**
     * Aserciones del flujo completo que antes solo figuraban en el resumen por stderr.
     */
    private function assertFlujoCompletoPersistenciaYArtefactos(
        object $row,
        string $zipPath,
        string $fullExcel,
        string $boletaPdfPathApp,
        string $publicBoletaPath
    ): void {
        $this->assertCotizacionSembradaCoherente($row);
        $this->assertCamposNumericosCotizacionFinalRazonables($row);
        $this->assertArtefactosZipExcelBoleta($zipPath, $fullExcel, $boletaPdfPathApp, $publicBoletaPath);
    }

    /**
     * Genera PDF con generateBoleta, guarda en storage/app/temp y copia a public/storage/temp_integracion_boleta/.
     *
     * @return array{0: string, 1: string} ruta PDF en app/temp, ruta PDF pública
     */
    private function generarBoletaYPublicarEnStorage(string $fullExcel, string $sufijoNombreSeguro): array
    {
        $sufijoNombreSeguro = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $sufijoNombreSeguro) ?: 'boleta';

        $spreadsheet = IOFactory::load($fullExcel);
        $method = new ReflectionMethod(CotizacionFinalController::class, 'generateBoleta');
        $method->setAccessible(true);
        $boleta = $method->invoke(new CotizacionFinalController(), $spreadsheet);

        $this->assertSame(200, $boleta->getStatusCode());
        $this->assertStringContainsString('application/pdf', (string) $boleta->headers->get('Content-Type'));
        $this->assertGreaterThan(500, strlen($boleta->getContent()), 'El PDF debe tener cuerpo no trivial.');

        $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $boletaPdfPath = $tempDir . DIRECTORY_SEPARATOR . 'boleta_integracion_' . $this->idCotizacion . '_' . $sufijoNombreSeguro . '.pdf';
        file_put_contents($boletaPdfPath, $boleta->getContent());
        $this->tempOutputFiles[] = $boletaPdfPath;

        $publicBoletaDir = public_path('storage/temp_integracion_boleta');
        if (!is_dir($publicBoletaDir)) {
            mkdir($publicBoletaDir, 0777, true);
        }
        $publicBoletaName = 'boleta_integracion_' . $this->idCotizacion . '_' . $sufijoNombreSeguro . '.pdf';
        $publicBoletaPath = $publicBoletaDir . DIRECTORY_SEPARATOR . $publicBoletaName;
        copy($boletaPdfPath, $publicBoletaPath);
        $this->tempOutputFiles[] = $publicBoletaPath;

        return [$boletaPdfPath, $publicBoletaPath];
    }

    /**
     * Stderr: descripción del caso, URL codificada Excel, URL codificada boleta (mismo host que APP_URL).
     */
    private function imprimirCasoYUrlsCodificadas(string $descripcionCaso, object $row, string $publicBoletaPath): void
    {
        fwrite(STDERR, 'Caso: ' . $descripcionCaso . PHP_EOL);

        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            fwrite(STDERR, '(config app.url vacío: no hay URLs absolutas)' . PHP_EOL);

            return;
        }

        $cotRel = trim(str_replace('\\', '/', (string) ($row->cotizacion_final_url ?? '')), '/');
        if ($cotRel !== '') {
            fwrite(STDERR, $base . $this->rutaStorageCodificadaParaUrl($cotRel) . PHP_EOL);
        }

        if (is_file($publicBoletaPath)) {
            fwrite(STDERR, $base . '/storage/temp_integracion_boleta/' . rawurlencode(basename($publicBoletaPath)) . PHP_EOL);
        }

        if (!$this->keepIntegrationTestArtifacts()) {
            fwrite(STDERR, '(Artefactos se borran al terminar; usa KEEP_INTEGRATION_TEST_ARTIFACTS=1 para conservarlos.)' . PHP_EOL);
        }
    }

    private function assertArtefactosZipExcelBoleta(
        string $zipPath,
        string $fullExcel,
        string $boletaPdfPathApp,
        string $publicBoletaPath
    ): void {
        $this->assertFileExists($zipPath, 'ZIP masivo debe existir en storage/app/temp.');
        $this->assertGreaterThan(64, filesize($zipPath) ?: 0, 'ZIP no debe estar vacío.');
        $this->assertFileExists($fullExcel);
        $this->assertFileExists($boletaPdfPathApp);
        $this->assertFileExists($publicBoletaPath);
    }

    private function assertCotizacionSembradaCoherente(object $row): void
    {
        $this->assertSame((string) $this->idCotizacion, (string) ($row->id ?? ''));
        $this->assertSame((string) $this->idContenedor, (string) ($row->id_contenedor ?? ''));
        $this->assertSame('PETER TAYPE TACAS', (string) ($row->nombre ?? ''));
        $this->assertNotEmpty($row->correo ?? '');
        $this->assertSame('2', (string) ($row->id_tipo_cliente ?? ''));
    }

    private function assertCamposNumericosCotizacionFinalRazonables(object $row): void
    {
        $this->assertIsNumeric($row->volumen_final ?? null);
        $this->assertGreaterThan(0, (float) ($row->volumen_final ?? 0));
        $this->assertIsNumeric($row->tarifa_final ?? null);
        $this->assertIsNumeric($row->monto_final ?? null);
        $this->assertIsNumeric($row->impuestos_final ?? null);
        $this->assertIsNumeric($row->logistica_final ?? null);
        $this->assertIsNumeric($row->peso_final ?? null);
    }

    private function assertZipResponseValido(BinaryFileResponse $response): void
    {
        $zipPath = $response->getFile()->getRealPath() ?: $response->getFile()->getPathname();
        $this->assertFileExists($zipPath);
        $this->assertGreaterThan(64, filesize($zipPath) ?: 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    private function invocarGenerateMassiveExcelPayrolls(array $payload, string $nombreFakeXlsx): BinaryFileResponse
    {
        /** @var CotizacionFinalController|\PHPUnit\Framework\MockObject_MockObject $controller */
        $controller = $this->getMockBuilder(CotizacionFinalController::class)
            ->onlyMethods(['getMassiveExcelData'])
            ->getMock();
        $controller->method('getMassiveExcelData')->willReturn($payload);

        $upload = UploadedFile::fake()->create(
            $nombreFakeXlsx,
            64,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $request = Request::create(
            '/api/carga-consolidada/contenedor/cotizacion-final/general/upload-plantilla-final',
            'POST',
            ['idContenedor' => $this->idContenedor],
            [],
            ['file' => $upload]
        );

        $response = $controller->generateMassiveExcelPayrolls($request);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        return $response;
    }

    /**
     * @param  int[]  $indicesAntidumping1Based  Posiciones 1..n con antidumping; vacío = ninguno
     * @return array<int, array<string, mixed>>
     */
    private function samplePayloadMultiplesProductosConAntidumpingEnIndices(int $cantidadProductos, array $indicesAntidumping1Based): array
    {
        $this->assertGreaterThanOrEqual(1, $cantidadProductos);

        $productos = [];
        for ($i = 1; $i <= $cantidadProductos; $i++) {
            $antidumping = in_array($i, $indicesAntidumping1Based, true)
                ? 2.5 + ($i * 0.1)
                : 0.0;
            $productos[] = [
                'nombre' => 'ITEM TEST ' . $i . ' MULTI',
                'cantidad' => (string) (1 + $i),
                'precio_unitario' => (string) (10 + $i * 2.25),
                'antidumping' => $antidumping,
                'valoracion' => 12.5 + $i,
                'ad_valorem' => 0.06,
                'isc_percent' => 0,
                'percepcion' => 0.035,
                'peso' => (string) (5 * $i),
                'cbm' => (string) round(0.04 + $i * 0.02, 4),
            ];
        }

        return [
            [
                'cliente' => [
                    'nombre' => 'PETER TAYPE TACAS',
                    'tipo' => 'NATURAL',
                    'dni' => '12345678',
                    'telefono' => '51900000000',
                    'productos' => $productos,
                ],
            ],
        ];
    }

    /**
     * Codifica cada segmento de la ruta bajo storage (espacios, tildes, etc.) para pegar la URL en el navegador.
     */
    private function rutaStorageCodificadaParaUrl(string $rutaRelativaPublicStorage): string
    {
        $rutaRelativaPublicStorage = trim(str_replace('\\', '/', $rutaRelativaPublicStorage), '/');
        if ($rutaRelativaPublicStorage === '') {
            return '/storage';
        }
        $segmentos = explode('/', $rutaRelativaPublicStorage);

        return '/storage/' . implode('/', array_map('rawurlencode', $segmentos));
    }

    private function removePublicBoletaIntegrationDirIfEmpty(): void
    {
        $dir = public_path('storage/temp_integracion_boleta');
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($dir);
    }

    private function switchToSqliteMemory(): void
    {
        $this->originalDbDefault = (string) config('database.default');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        $this->app['db']->reconnect();
        $this->sqliteActive = true;
    }

    private function restoreDatabaseConnection(): void
    {
        Config::set('database.default', $this->originalDbDefault);
        DB::purge('sqlite');
        if ($this->originalDbDefault) {
            DB::purge($this->originalDbDefault);
            $this->app['db']->reconnect();
        }
    }

    private function createSchemaAndSeed(): void
    {
        Schema::create('contenedor_consolidado_tipo_cliente', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('id_contenedor');
            $table->unsignedBigInteger('id_tipo_cliente');
            $table->string('tarifa')->nullable();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('vol_selected')->nullable();
            $table->decimal('volumen', 15, 2)->nullable();
            $table->decimal('volumen_china', 15, 2)->nullable();
            $table->decimal('volumen_doc', 15, 2)->nullable();
            $table->string('estado_cotizador')->nullable();
            $table->string('estado_cliente')->nullable();
            $table->unsignedBigInteger('id_cliente_importacion')->nullable();
            $table->string('estado_cotizacion_final')->nullable();
            $table->string('cotizacion_final_url')->nullable();
            $table->decimal('volumen_final', 15, 2)->nullable();
            $table->decimal('monto_final', 15, 2)->nullable();
            $table->decimal('tarifa_final', 15, 2)->nullable();
            $table->decimal('impuestos_final', 15, 2)->nullable();
            $table->decimal('logistica_final', 15, 2)->nullable();
            $table->decimal('fob_final', 15, 2)->nullable();
            $table->decimal('peso_final', 15, 2)->nullable();
        });

        Schema::create('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cotizacion');
        });

        Schema::create('contenedor_consolidado_cotizacion_delivery_servicio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_cotizacion');
            $table->string('tipo_servicio', 32);
            $table->decimal('importe', 12, 2)->default(0);
        });

        DB::table('contenedor_consolidado_tipo_cliente')->insert([
            'id' => 2,
            'name' => 'ANTIGUO',
        ]);

        DB::table('contenedor_consolidado_cotizacion')->insert([
            'id' => $this->idCotizacion,
            'id_contenedor' => $this->idContenedor,
            'id_tipo_cliente' => 2,
            'tarifa' => '280.00',
            'nombre' => 'PETER TAYPE TACAS',
            'correo' => 'peter.pj.2306@gmail.com',
            'vol_selected' => 'volumen_china',
            'volumen' => 4.75,
            'volumen_china' => 4.75,
            'volumen_doc' => 4.74,
            'estado_cotizador' => 'CONFIRMADO',
            'estado_cliente' => 'CONFIRMADO',
            'id_cliente_importacion' => null,
            'estado_cotizacion_final' => 'PENDIENTE',
            'cotizacion_final_url' => null,
            'volumen_final' => null,
            'monto_final' => null,
            'tarifa_final' => null,
            'impuestos_final' => null,
            'logistica_final' => null,
            'fob_final' => null,
            'peso_final' => null,
        ]);

        DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
            'id' => 1,
            'id_cotizacion' => $this->idCotizacion,
        ]);
    }

    /**
     * Misma forma que en logs tras parsear plantilla masiva + matching BD.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sampleExcelPayloadFromLogs(): array
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
     * Misma forma que sampleExcelPayloadFromLogs pero con varios ítems (tests delivery/montacargas).
     *
     * @return array<int, array<string, mixed>>
     */
    private function sampleExcelPayloadServicioMultiplesItems(int $cantidadProductos): array
    {
        $this->assertGreaterThanOrEqual(1, $cantidadProductos);
        $productos = [];
        for ($i = 1; $i <= $cantidadProductos; $i++) {
            $productos[] = [
                'nombre' => 'ITEM SERVICIO LOG ' . $i,
                'cantidad' => (string) (1 + $i),
                'precio_unitario' => (string) round(12.5 + $i * 1.75, 2),
                'antidumping' => 0,
                'valoracion' => 0,
                'ad_valorem' => 0,
                'isc_percent' => 0,
                'percepcion' => 0.035,
                'peso' => (string) (8 + $i * 2),
                'cbm' => (string) round(0.04 + $i * 0.015, 4),
            ];
        }

        return [
            [
                'cliente' => [
                    'nombre' => 'PETER TAYPE TACAS',
                    'tipo' => 'NATURAL',
                    'dni' => '12345678',
                    'telefono' => '51900000000',
                    'productos' => $productos,
                ],
            ],
        ];
    }

    private function ensureBoletaStaticAssets(): void
    {
        $dir = public_path('assets/images');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $logo = $dir . DIRECTORY_SEPARATOR . 'probusiness.png';
        if (!is_file($logo)) {
            file_put_contents($logo, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ));
            $this->createdAssetFiles[] = $logo;
        }

        $pagos = $dir . DIRECTORY_SEPARATOR . 'pagos-full.jpg';
        if (!is_file($pagos)) {
            // JPEG mínimo válido (1x1 px)
            file_put_contents($pagos, base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8='
            ));
            $this->createdAssetFiles[] = $pagos;
        }
    }

    private function removeCreatedAssetFiles(): void
    {
        foreach ($this->createdAssetFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->createdAssetFiles = [];
    }

    private function cleanupGeneratedCotizacionArtifacts(): void
    {
        $base = public_path('storage/cotizacion_final/' . $this->idContenedor);
        if (!is_dir($base)) {
            return;
        }
        foreach (glob($base . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($base);
    }
}
