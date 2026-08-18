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
    }

    public function test_attach_command_exposes_safe_transition_options(): void
    {
        $definition = Artisan::all()['manual:attach-capturas']->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('strict'));
        $this->assertTrue($definition->hasOption('legacy'));
    }
}
