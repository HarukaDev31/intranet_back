<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ManualUsuarioCaptureMigrationsTest extends TestCase
{
    public function test_historical_capture_migrations_do_not_call_storage_attacher(): void
    {
        foreach ([
            '2026_08_17_160000_attach_manual_usuario_capturas.php',
            '2026_08_17_210000_reattach_manual_usuario_capturas_enfocadas.php',
        ] as $migration) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/' . $migration);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('ManualUsuarioCapturasAttacher', $source);
            $this->assertStringNotContainsString('->attach(', $source);
        }
    }
}
