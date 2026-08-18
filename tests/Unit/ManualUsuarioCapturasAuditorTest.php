<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCapturasAuditor;
use PHPUnit\Framework\TestCase;

class ManualUsuarioCapturasAuditorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'manual-audit-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_reports_coverage_orphans_dimensions_and_media_id(): void
    {
        $this->png('presente.png');
        $this->png('huerfana.png');
        $manifest = ['captures' => [
            $this->capture('presente', 'presente.png', 10),
            $this->capture('faltante', 'faltante.png', null),
        ]];

        $report = (new ManualUsuarioCapturasAuditor())->audit($manifest, $this->directory);
        $codes = array_column($report['issues'], 'code');

        $this->assertContains('missing_png', $codes);
        $this->assertContains('orphan_png', $codes);
        $this->assertContains('small_dimensions', $codes);
        $this->assertContains('missing_media_id', $codes);
        $this->assertFalse($report['ok']);
    }

    public function test_repeated_hash_requires_explicit_alias(): void
    {
        $this->png('uno.png');
        copy($this->directory . DIRECTORY_SEPARATOR . 'uno.png', $this->directory . DIRECTORY_SEPARATOR . 'dos.png');
        $manifest = ['captures' => [
            $this->capture('uno', 'uno.png', 1),
            $this->capture('dos', 'dos.png', 2),
        ]];

        $report = (new ManualUsuarioCapturasAuditor())->audit(
            $manifest,
            $this->directory,
            ['minimum_width' => 1, 'minimum_height' => 1]
        );

        $this->assertContains('duplicate_hash_without_alias', array_column($report['issues'], 'code'));
    }

    public function test_explicit_alias_justifies_repeated_hash(): void
    {
        $this->png('uno.png');
        copy($this->directory . DIRECTORY_SEPARATOR . 'uno.png', $this->directory . DIRECTORY_SEPARATOR . 'dos.png');
        $alias = $this->capture('dos', 'dos.png', 2);
        $alias['alias_of'] = 'uno';
        $manifest = ['captures' => [
            $this->capture('uno', 'uno.png', 1),
            $alias,
        ]];

        $report = (new ManualUsuarioCapturasAuditor())->audit(
            $manifest,
            $this->directory,
            ['minimum_width' => 1, 'minimum_height' => 1]
        );

        $this->assertNotContains('duplicate_hash_without_alias', array_column($report['issues'], 'code'));
        $this->assertTrue($report['ok']);
    }

    public function test_runner_viewport_variant_is_not_orphan_or_duplicate(): void
    {
        $this->png('captura.png');
        copy(
            $this->directory . DIRECTORY_SEPARATOR . 'captura.png',
            $this->directory . DIRECTORY_SEPARATOR . 'captura--2560x1440.png'
        );
        $capture = $this->capture('captura', 'captura.png', 1);
        $capture['roles'] = ['comercial'];
        $capture['screen'] = 'alumnos';

        $report = (new ManualUsuarioCapturasAuditor())->audit(
            ['captures' => [$capture]],
            $this->directory,
            ['minimum_width' => 1, 'minimum_height' => 1]
        );
        $codes = array_column($report['issues'], 'code');

        $this->assertNotContains('orphan_png', $codes);
        $this->assertNotContains('duplicate_hash_without_alias', $codes);
        $this->assertTrue($report['ok']);
    }

    private function capture(string $key, string $output, ?int $mediaId): array
    {
        return [
            'capture_key' => $key,
            'output' => $output,
            'media_id' => $mediaId,
            'block_id' => $mediaId,
        ];
    }

    private function png(string $name): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z4k0AAAAASUVORK5CYII=')
        );
    }
}
