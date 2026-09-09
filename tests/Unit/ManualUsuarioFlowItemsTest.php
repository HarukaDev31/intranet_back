<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioFlowItems;
use PHPUnit\Framework\TestCase;

class ManualUsuarioFlowItemsTest extends TestCase
{
    public function test_keeps_existing_three_argument_calls_compatible(): void
    {
        $item = $this->factory()->make('Título', 'Cuerpo', 'Hint');

        $this->assertSame([
            'title' => 'Título',
            'body' => 'Cuerpo',
            'captura' => 'Hint',
        ], $item);
    }

    public function test_accepts_explicit_capture_metadata(): void
    {
        $item = $this->factory()->make('Título', 'Cuerpo', 'Hint', [
            'capture_key' => 'manual-paso',
            'capture_alias_of' => 'manual-canonica',
            'capture_output' => 'manual-paso.png',
            'type' => 'modal',
            'target' => ['role' => 'dialog'],
            'actions' => [['type' => 'click', 'target' => ['text' => 'Abrir']]],
            'expectedText' => 'Formulario',
            'unknown' => 'ignored',
        ]);

        $this->assertSame('manual-paso', $item['capture_key']);
        $this->assertSame('manual-canonica', $item['capture_alias_of']);
        $this->assertSame('manual-paso.png', $item['capture_output']);
        $this->assertSame('modal', $item['type']);
        $this->assertSame(['role' => 'dialog'], $item['target']);
        $this->assertSame('Formulario', $item['expectedText']);
        $this->assertArrayNotHasKey('unknown', $item);
    }

    private function factory(): object
    {
        return new class {
            use ManualUsuarioFlowItems;

            public function make($title, $body, $hint = '', $capture = [])
            {
                return $this->itemFlujo($title, $body, $hint, $capture);
            }
        };
    }
}
