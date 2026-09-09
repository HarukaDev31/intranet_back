<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCapturasAttacher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ManualUsuarioCapturasAttacherTest extends TestCase
{
    public function test_secondary_runner_viewport_is_not_considered_orphan(): void
    {
        $method = new ReflectionMethod(ManualUsuarioCapturasAttacher::class, 'isViewportVariant');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(
            new ManualUsuarioCapturasAttacher(),
            'comercial/alumnos/crear--2560x1440.png',
            ['comercial/alumnos/crear.png']
        ));
        $this->assertFalse($method->invoke(
            new ManualUsuarioCapturasAttacher(),
            'comercial/alumnos/otra.png',
            ['comercial/alumnos/crear.png']
        ));
    }

    public function test_resolves_shared_png_by_identity_ignoring_role_folder(): void
    {
        $files = [
            'administracion/news/news__leer-avisos__paso-01-tarjetas-y-detalle.png' => '/tmp/a.png',
            'comercial/news/news__leer-avisos__paso-01-tarjetas-y-detalle.png' => '/tmp/b.png',
        ];
        $resolved = (new ManualUsuarioCapturasAttacher())->resolvePng(
            $files,
            'news__leer-avisos__paso-01-tarjetas-y-detalle',
            'news__leer-avisos__paso-01-tarjetas-y-detalle',
            'comercial/news/news__leer-avisos__paso-01-tarjetas-y-detalle.png'
        );

        $this->assertSame(
            'comercial/news/news__leer-avisos__paso-01-tarjetas-y-detalle.png',
            $resolved
        );

        $root = ['news__leer-avisos__paso-01-tarjetas-y-detalle.png' => '/tmp/root.png'] + $files;
        $this->assertSame(
            'news__leer-avisos__paso-01-tarjetas-y-detalle.png',
            (new ManualUsuarioCapturasAttacher())->resolvePng(
                $root,
                'news__leer-avisos__paso-01-tarjetas-y-detalle',
                'otro-key',
                'administracion/news/otro-key.png'
            )
        );
    }
}
