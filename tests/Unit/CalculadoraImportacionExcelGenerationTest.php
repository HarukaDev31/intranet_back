<?php

namespace Tests\Unit;

use App\Services\CalculadoraImportacion\CalculadoraImportacionCacheService;
use App\Services\CalculadoraImportacionService;
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
                'cbm'      => 8.95,
                'peso'     => 0,
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

        // Excel generado
        $this->assertNotEmpty($calculadora->url_cotizacion, 'No se guardó la URL del Excel');

        $relativePath = ltrim(str_replace('/storage/', '', parse_url($calculadora->url_cotizacion, PHP_URL_PATH)), '/');
        $filePath = storage_path('app/public/' . $relativePath);
        $this->assertFileExists($filePath, 'El archivo Excel no existe en: ' . $filePath);
        $this->assertGreaterThan(0, filesize($filePath), 'El archivo Excel está vacío');

        if (self::INVALIDAR_CACHE) {
            app(CalculadoraImportacionCacheService::class)->invalidateAfterWrite($calculadora);
        }

        $fullUrl = rtrim(config('app.url'), '/') . $calculadora->url_cotizacion;

        fwrite(STDOUT, PHP_EOL . '✔  Calculadora ID:   ' . $calculadora->id . PHP_EOL);
        fwrite(STDOUT, '   Excel en:         ' . $filePath . PHP_EOL);
        fwrite(STDOUT, '   URL completa:     ' . $fullUrl . PHP_EOL);
        fwrite(STDOUT, '   Total FOB:        ' . $calculadora->total_fob . PHP_EOL);
        fwrite(STDOUT, '   Total impuestos:  ' . $calculadora->total_impuestos . PHP_EOL);
        fwrite(STDOUT, '   Logística:        ' . $calculadora->logistica . PHP_EOL);
    }
}
