<?php

namespace Tests\Unit;

use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use Tests\TestCase;

class RotuladoPdfFilenameTest extends TestCase
{
    public function test_el_pdf_de_rotulado_usa_el_codigo_de_proveedor_y_no_el_temporal()
    {
        $this->assertSame(
            'Rotulado_PE-CAJE23-1.pdf',
            CoordinacionWhatsappPayload::nombrePdfRotulado('PE-CAJE23-1')
        );
        $this->assertSame(
            'Rotulado_CAJE19-1.pdf',
            CoordinacionWhatsappPayload::nombrePdfRotulado(' CAJE19-1 ')
        );

        $payload = CoordinacionWhatsappPayload::rotuladoPdfProducto(
            '51999999999',
            'SET PROTECTOR DE PANTALLA',
            'PE-CAJE23-1',
            '/var/www/storage/app/temp_document_proveedorPE-CAJE23-1.pdf',
            "Producto: SET\nCódigo de proveedor: PE-CAJE23-1",
            0
        );

        $this->assertSame('pb_rotulado_pdf_producto_v1', $payload['template']);
        $this->assertSame('Rotulado_PE-CAJE23-1.pdf', $payload['header']['filename']);
        $this->assertStringNotContainsString('temp_document', $payload['header']['filename']);
        $this->assertSame(
            '/var/www/storage/app/temp_document_proveedorPE-CAJE23-1.pdf',
            $payload['header']['path']
        );
    }

    public function test_un_nombre_explicito_no_se_reemplaza_por_el_de_rotulado()
    {
        $payload = CoordinacionWhatsappPayload::rotuladoPdfProducto(
            '51999999999',
            'Movilidad Personal',
            'PE-CAJE23-1',
            '/var/www/storage/app/templates/rotulado/movilidad_personal.pdf',
            'Etiqueta especial',
            1,
            'movilidad_personal.pdf'
        );

        $this->assertSame('movilidad_personal.pdf', $payload['header']['filename']);
    }
}
