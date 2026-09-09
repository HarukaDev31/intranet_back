<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ManualUsuarioCapturasCommandsTest extends TestCase
{
    public function test_capture_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('manual:attach-capturas', $commands);
        $this->assertArrayHasKey('manual:export-capturas-manifest', $commands);
        $this->assertArrayHasKey('manual:normalize-capturas-keys', $commands);
        $this->assertArrayHasKey('manual:clone-abiertos-completados', $commands);
    }

    public function test_attach_command_exposes_safe_transition_options(): void
    {
        $definition = Artisan::all()['manual:attach-capturas']->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('strict'));
        $this->assertTrue($definition->hasOption('legacy'));
    }

    public function test_clone_abiertos_completados_command_exposes_prod_safe_options(): void
    {
        $definition = Artisan::all()['manual:clone-abiertos-completados']->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('cdn'));
        $this->assertTrue($definition->hasOption('force'));
        $this->assertSame(
            'https://cdn.probusiness.pe',
            $definition->getOption('cdn')->getDefault()
        );
    }
}
