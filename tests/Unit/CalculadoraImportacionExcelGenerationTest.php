<?php

namespace Tests\Unit;

use App\Services\CalculadoraImportacion\CalculadoraImportacionCacheService;
use App\Services\CalculadoraImportacionService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CalculadoraImportacionExcelGenerationTest extends TestCase
{
    /**
     * Cuando está en true, invalida la cache después de guardar
     * para que el registro aparezca de inmediato en la tabla de dev.
     * Cambiar a false si no se quiere ese efecto secundario al correr el test.
     */
    private const INVALIDAR_CACHE = true;

    private array $payload = [
        'clienteInfo' => [
            'nombre'          => 'MATHIAS DELGADO WETZELL',
            'dni'             => '20611942100',
            'whatsapp'        => '19254571573',
            'correo'          => 'mdelgadow1@gmail.com',
            'qtyProveedores'  => 1,
            'tipoCliente'     => 'ANTIGUO',
            'tipoDocumento'   => 'DNI',
            'empresa'         => '',
            'ruc'             => '',
        ],
        'proveedores' => [
            [
                'id'       => '1',
                'cbm'      => 0.80,
                'peso'     => 1000,
                'qtyCaja'  => 0,
                'productos' => [
                    [
                        'nombre'         => 'SCOOTER ELECTRICO MODELO K3 MASTER',
                        'precio'         => 420,
                        'valoracion'     => 0,
                        'cantidad'       => 10,
                        'antidumpingCU'  => 12,
                        'adValoremP'     => 0,
                        'iscP'           => 0,
                    ],
                    [
                        'nombre'         => 'SCOOTER ELECTRICO MODELO K2 MASTER',
                        'precio'         => 335,
                        'valoracion'     => 0,
                        'cantidad'       => 15,
                        'antidumpingCU'  => 0,
                        'adValoremP'     => 0,
                        'iscP'           => 1,
                    ],
                    [
                        'nombre'         => 'SCOOTER ELECTRICO MODELO K2 MAX',
                        'precio'         => 275,
                        'valoracion'     => 0,
                        'cantidad'       => 25,
                        'antidumpingCU'  => 0,
                        'adValoremP'     => 5,
                        'iscP'           => 0,
                    ],
                ],
            ],
        ],
        'tarifaTotalExtraProveedor'       => 0,
        'tarifaTotalExtraItem'            => 0,
        'tarifaDescuento'                 => 0,
        'id_usuario'                      => 28911,
        'id_carga_consolidada_contenedor' => 160,
        'tarifa' => [
            'id'             => 18,
            'limit_inf'      => '4.10',
            'limit_sup'      => '999999.99',
            'type'           => 'STANDARD',
            'tarifa'         => '280.00',
            'label'          => 'ANTIGUO',
            'id_tipo_cliente' => 4,
            'value'          => 'ANTIGUO',
        ],
        'tipo_cambio' => 3.7,
        'es_imo'      => false,
        'usa_yuan'    => false,
        'created_by'  => 28791,
    ];

    public function test_guardar_calculo_registra_en_bd_y_genera_excel(): void
    {
        /** @var CalculadoraImportacionService $service */
        $service = app(CalculadoraImportacionService::class);

        $calculadora = $service->guardarCalculo($this->payload);

        // Registro en BD
        $this->assertNotNull($calculadora->id, 'No se creó el registro en BD');
        $this->assertDatabaseHas('calculadora_importacion', ['id' => $calculadora->id]);
        $this->assertSame('PESO', $calculadora->tipo_cotizacion);

        // Excel generado
        $this->assertNotEmpty($calculadora->url_cotizacion, 'No se guardó la URL del Excel');

        $relativePath = ltrim(str_replace('/storage/', '', parse_url($calculadora->url_cotizacion, PHP_URL_PATH)), '/');
        $filePath = storage_path('app/public/' . $relativePath);
        $this->assertFileExists($filePath, 'El archivo Excel no existe en: ' . $filePath);
        $this->assertGreaterThan(0, filesize($filePath), 'El archivo Excel está vacío');

        // PDF generado + ruta de descarga válida
        $this->assertNotEmpty($calculadora->url_cotizacion_pdf, 'No se guardó la URL del PDF');
        $relativePdfPath = ltrim(str_replace('/storage/', '', parse_url($calculadora->url_cotizacion_pdf, PHP_URL_PATH)), '/');
        $pdfPath = storage_path('app/public/' . $relativePdfPath);
        $this->assertFileExists($pdfPath, 'El archivo PDF no existe en: ' . $pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath), 'El archivo PDF está vacío');

        if (self::INVALIDAR_CACHE) {
            app(CalculadoraImportacionCacheService::class)->invalidateAfterWrite($calculadora);
        }

        $fullUrl = rtrim(config('app.url'), '/') . $calculadora->url_cotizacion;

        fwrite(STDOUT, PHP_EOL . '✔  Calculadora ID:   ' . $calculadora->id . PHP_EOL);
        fwrite(STDOUT, '   Excel en:         ' . $filePath . PHP_EOL);
        fwrite(STDOUT, '   PDF en:           ' . $pdfPath . PHP_EOL);
        fwrite(STDOUT, '   URL completa:     ' . $fullUrl . PHP_EOL);
        fwrite(STDOUT, '   URL PDF:          ' . (rtrim(config('app.url'), '/') . $calculadora->url_cotizacion_pdf) . PHP_EOL);
        fwrite(STDOUT, '   Total FOB:        ' . $calculadora->total_fob . PHP_EOL);
        fwrite(STDOUT, '   Total impuestos:  ' . $calculadora->total_impuestos . PHP_EOL);
        fwrite(STDOUT, '   Logística:        ' . $calculadora->logistica . PHP_EOL);
    }

    public function test_genera_excel_y_pdf_con_varios_proveedores_mixto_peso_y_cbm(): void
    {
        /** @var CalculadoraImportacionService $service */
        $service = app(CalculadoraImportacionService::class);

        $payload = $this->payload;
        $payload['clienteInfo']['qtyProveedores'] = 6;
        $payload['proveedores'] = [
            [
                'id' => '1',
                'cbm' => 0.20,
                'peso' => 1800, // 1.80 -> domina PESO
                'qtyCaja' => 3,
                'productos' => [
                    [
                        'nombre' => 'ITEM P1',
                        'precio' => 50,
                        'valoracion' => 0,
                        'cantidad' => 2,
                        'antidumpingCU' => 0,
                        'adValoremP' => 0,
                        'iscP' => 0,
                    ],
                ],
            ],
            [
                'id' => '2',
                'cbm' => 2.10,
                'peso' => 1500, // 1.50 -> domina CBM
                'qtyCaja' => 2,
                'productos' => [
                    [
                        'nombre' => 'ITEM P2',
                        'precio' => 80,
                        'valoracion' => 0,
                        'cantidad' => 1,
                        'antidumpingCU' => 0,
                        'adValoremP' => 0,
                        'iscP' => 0,
                    ],
                ],
            ],
            [
                'id' => '3',
                'cbm' => 1.10,
                'peso' => 900, // 0.90 -> domina CBM
                'qtyCaja' => 1,
                'productos' => [
                    [
                        'nombre' => 'ITEM P3',
                        'precio' => 30,
                        'valoracion' => 0,
                        'cantidad' => 5,
                        'antidumpingCU' => 0,
                        'adValoremP' => 0,
                        'iscP' => 0,
                    ],
                ],
            ],
            [
                'id' => '4',
                'cbm' => 0.70,
                'peso' => 1200, // 1.20
                'qtyCaja' => 4,
                'productos' => [
                    [
                        'nombre' => 'ITEM P4',
                        'precio' => 45,
                        'valoracion' => 0,
                        'cantidad' => 3,
                        'antidumpingCU' => 0,
                        'adValoremP' => 0,
                        'iscP' => 0,
                    ],
                ],
            ],
            [
                'id' => '5',
                'cbm' => 1.20,
                'peso' => 2500, // 2.50 -> domina PESO
                'qtyCaja' => 5,
                'productos' => [
                    [
                        'nombre' => 'ITEM P5',
                        'precio' => 120,
                        'valoracion' => 0,
                        'cantidad' => 2,
                        'antidumpingCU' => 0,
                        'adValoremP' => 0,
                        'iscP' => 0,
                    ],
                ],
            ],
            [
                'id' => '6',
                'cbm' => 3.30,
                'peso' => 2000, // 2.00 -> domina CBM
                'qtyCaja' => 6,
                'productos' => [
                    [
                        'nombre' => 'ITEM P6',
                        'precio' => 75,
                        'valoracion' => 0,
                        'cantidad' => 4,
                        'antidumpingCU' => 0,
                        'adValoremP' => 0,
                        'iscP' => 0,
                    ],
                ],
            ],
        ];

        $calculadora = $service->guardarCalculo($payload);
        $calculadora->load('proveedores');

        $this->assertNotNull($calculadora->id);
        $this->assertSame('PESO', $calculadora->tipo_cotizacion);
        $this->assertCount(6, $calculadora->proveedores);

        // maxcbm persistido por proveedor (sin depender del orden)
        $maxcbmValues = $calculadora->proveedores
            ->map(fn ($p) => round((float) $p->maxcbm, 2))
            ->sort()
            ->values()
            ->all();
        $this->assertSame([1.10, 1.20, 1.80, 2.10, 2.50, 3.30], $maxcbmValues);

        // Debe existir al menos un proveedor donde domine PESO y otro donde domine CBM
        $dominaPeso = $calculadora->proveedores->contains(fn ($p) => (float) $p->maxcbm > (float) $p->cbm);
        $dominaCbm = $calculadora->proveedores->contains(fn ($p) => (float) $p->cbm >= ((float) $p->peso / 1000) && (float) $p->maxcbm === (float) $p->cbm);
        $this->assertTrue($dominaPeso, 'Debe existir al menos un proveedor donde domine PESO');
        $this->assertTrue($dominaCbm, 'Debe existir al menos un proveedor donde domine CBM');

        $relativeExcelPath = ltrim(str_replace('/storage/', '', parse_url($calculadora->url_cotizacion, PHP_URL_PATH)), '/');
        $excelPath = storage_path('app/public/' . $relativeExcelPath);
        $this->assertFileExists($excelPath);
        $this->assertGreaterThan(0, filesize($excelPath));

        $spreadsheet = IOFactory::load($excelPath);
        $sheetResumen = $spreadsheet->getSheet(0);
        $sheetCalculos = $spreadsheet->getSheet(1);
        $volumenResumen = (float) $sheetResumen->getCell('I11')->getCalculatedValue();
        $this->assertEqualsWithDelta(12.00, $volumenResumen, 0.01, 'El volumen total en hoja 1 debe usar suma de maxcbm');

        $col = 'C';
        $colTotales = null;
        for ($i = 0; $i < 120; $i++) {
            $value = strtoupper(trim((string) $sheetCalculos->getCell($col . '3')->getValue()));
            if ($value === 'TOTALES') {
                $colTotales = $col;
                break;
            }
            $col++;
        }
        $this->assertNotNull($colTotales, 'No se encontró la columna TOTALES en hoja 2');
        $cbmTotalHoja2 = (float) $sheetCalculos->getCell($colTotales . '8')->getCalculatedValue();
        $this->assertEqualsWithDelta(8.60, $cbmTotalHoja2, 0.01, 'La fila 8 debe conservar la suma de CBM original');
        $maxcbmTotalHoja2 = (float) $sheetCalculos->getCell($colTotales . '9')->getCalculatedValue();
        $this->assertEqualsWithDelta(12.00, $maxcbmTotalHoja2, 0.01, 'La fila maxcbm en hoja 2 debe sumar correctamente');

        $relativePdfPath = ltrim(str_replace('/storage/', '', parse_url($calculadora->url_cotizacion_pdf, PHP_URL_PATH)), '/');
        $pdfPath = storage_path('app/public/' . $relativePdfPath);
        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));

        $fullExcelUrl = rtrim(config('app.url'), '/') . $calculadora->url_cotizacion;
        $fullPdfUrl = rtrim(config('app.url'), '/') . $calculadora->url_cotizacion_pdf;

        fwrite(STDOUT, PHP_EOL . '✔  [MIXTO] Calculadora ID: ' . $calculadora->id . PHP_EOL);
        fwrite(STDOUT, '   [MIXTO] Excel en:      ' . $excelPath . PHP_EOL);
        fwrite(STDOUT, '   [MIXTO] PDF en:        ' . $pdfPath . PHP_EOL);
        fwrite(STDOUT, '   [MIXTO] URL Excel:     ' . $fullExcelUrl . PHP_EOL);
        fwrite(STDOUT, '   [MIXTO] URL PDF:       ' . $fullPdfUrl . PHP_EOL);
    }
}
