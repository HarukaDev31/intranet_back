<?php

namespace Tests\Unit;

use App\Support\SoporteTi\SoporteTiHtmlEvidenceSanitizer;
use App\Support\Storage\StoragePathSanitizer;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SoporteTiHtmlChatAdjuntoTest extends TestCase
{
    public function test_html_del_chat_conserva_contenido_y_nombre_pero_se_guarda_inerte(): void
    {
        $html = '<html><body><h1>Indicadores TI</h1><script>render()</script></body></html>';
        $file = UploadedFile::fake()->createWithContent('Indicadores TI.html', $html);

        $this->assertTrue(SoporteTiHtmlEvidenceSanitizer::esHtml($file));

        $r = SoporteTiHtmlEvidenceSanitizer::prepararHtmlInerte($file);

        $this->assertSame($html, $r['contenido']);
        $this->assertSame('Indicadores TI.html', $r['nombre']);
        $this->assertStringEndsWith('.html.txt', StoragePathSanitizer::fileName($r['nombre_almacenamiento']));

        $opciones = SoporteTiHtmlEvidenceSanitizer::opcionesAlmacenamientoInerte();
        $this->assertStringStartsWith('text/plain', $opciones['ContentType']);
        $this->assertSame('attachment', $opciones['ContentDisposition']);
    }

    public function test_htm_conserva_su_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('reporte.htm', '<p>x</p>');

        $r = SoporteTiHtmlEvidenceSanitizer::prepararHtmlInerte($file);

        $this->assertSame('reporte.htm', $r['nombre']);
        $this->assertSame('reporte.htm.txt', $r['nombre_almacenamiento']);
    }
}
