<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCapturaNombre;
use PHPUnit\Framework\TestCase;

class ManualUsuarioCapturaNombreTest extends TestCase
{
    public function test_prefers_stored_nombre(): void
    {
        $this->assertSame(
            'Noticias — tarjetas y detalle',
            ManualUsuarioCapturaNombre::resolve(
                'Noticias — tarjetas y detalle',
                ['capture_key' => 'news__leer-avisos__paso-01-tarjetas-y-detalle'],
                'Foto 1 — tarjetas',
                'Noticias'
            )
        );
    }

    public function test_derives_from_flow_and_step_not_raw_key(): void
    {
        $nombre = ManualUsuarioCapturaNombre::fromSnapshot(
            [
                'capture_key' => 'news__leer-avisos__paso-01-tarjetas-y-detalle',
                'capture_flow' => 'Noticias',
                'capture_step' => ['number' => 1, 'title' => 'tarjetas y detalle'],
            ],
            'Foto 1 — tarjetas y detalle',
            'Noticias'
        );

        $this->assertSame('Noticias — tarjetas y detalle', $nombre);
        $this->assertStringNotContainsString('news__', $nombre);
    }

    public function test_does_not_use_page_title_when_flow_and_step_exist(): void
    {
        $nombre = ManualUsuarioCapturaNombre::fromSnapshot(
            [
                'capture_flow' => 'Documentación',
                'capture_step' => ['number' => 1, 'title' => 'Cómo se ve y cómo interactúas'],
            ],
            'Foto 1 — Cómo se ve y cómo interactúas',
            'Carga consolidada — Completados'
        );

        $this->assertSame('Documentación — Cómo se ve y cómo interactúas', $nombre);
        $this->assertStringNotContainsString('Carga consolidada', $nombre);
        $this->assertStringNotContainsString('Descargar plantillas', $nombre);
    }

    public function test_strips_foto_prefix_from_block_title(): void
    {
        $this->assertSame(
            'Pedidos de Curso — Confirmar',
            ManualUsuarioCapturaNombre::fromSnapshot(
                [],
                'Foto 2 — Confirmar',
                'Pedidos de Curso'
            )
        );
    }
}
