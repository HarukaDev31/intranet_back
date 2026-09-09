<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCaptureKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ManualUsuarioCaptureKeyTest extends TestCase
{
    public function test_derives_deterministic_safe_key(): void
    {
        $key = ManualUsuarioCaptureKey::make(
            'curso/alumnos',
            'Jefe Marketing',
            'Generar y enviar constancia',
            'Vista previa',
            2
        );

        $this->assertSame(
            'curso-alumnos__generar-y-enviar-constancia__paso-02-vista-previa',
            $key
        );
        $this->assertSame($key . '.png', ManualUsuarioCaptureKey::output($key));
        $this->assertSame(
            'jefe-marketing/curso-alumnos/' . $key . '.png',
            ManualUsuarioCaptureKey::runnerOutput('Jefe Marketing', 'curso/alumnos', $key)
        );
        $this->assertSame($key, ManualUsuarioCaptureKey::identity($key, null));
        $this->assertSame(
            'news__leer-avisos__paso-01-tarjetas-y-detalle',
            ManualUsuarioCaptureKey::identity(
                'comercial-news-paso-01',
                'news__leer-avisos__paso-01-tarjetas-y-detalle'
            )
        );
    }

    public function test_explicit_key_is_preserved(): void
    {
        $this->assertSame(
            'curso-alumnos-constancia-v1',
            ManualUsuarioCaptureKey::make('módulo', 'rol', 'flujo', 'paso', 1, 'curso-alumnos-constancia-v1')
        );
    }

    public function test_rejects_unsafe_explicit_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ManualUsuarioCaptureKey::make('m', 'r', 'f', 's', 1, '../captura');
    }
}
