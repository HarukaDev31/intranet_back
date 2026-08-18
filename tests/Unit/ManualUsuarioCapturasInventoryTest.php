<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCapturasInventory;
use PHPUnit\Framework\TestCase;

class ManualUsuarioCapturasInventoryTest extends TestCase
{
    public function test_exports_complete_capture_metadata(): void
    {
        $block = (object) [
            'id' => 30,
            'pagina_id' => 20,
            'orden' => 2,
            'titulo' => 'Foto 2 — Confirmar',
            'payload' => ['snapshot' => [
                'capture_key' => 'pedidos__confirmar__paso-02-confirmar',
                'capture_role' => 'comercial',
                'capture_screen' => 'pedidos/detalle',
                'capture_screen_url' => '/pedidos/20',
                'capture_modulo' => 'pedidos',
                'capture_flow' => 'Confirmar pedido',
                'capture_step' => ['number' => 2, 'title' => 'Confirmar'],
                'capture_hint' => 'Recorta el botón.',
                'capture_output' => 'comercial/pedidos-detalle/pedidos__confirmar__paso-02-confirmar.png',
                'capture_config' => [
                    'type' => 'modal',
                    'target' => ['role' => 'dialog'],
                    'expectedText' => 'Confirmar pedido',
                ],
                'media_id' => 9,
            ]],
            'pagina' => (object) ['role_slug' => 'comercial', 'modulo_key' => 'pedidos'],
            'parent' => (object) ['titulo' => 'Confirmar pedido'],
        ];

        $manifest = (new ManualUsuarioCapturasInventory())->fromBlocks([$block], collect());
        $capture = $manifest['captures'][0];

        $this->assertSame(1, $manifest['schema_version']);
        $this->assertSame(['comercial'], $capture['roles']);
        $this->assertSame('pedidos/detalle', $capture['screen']);
        $this->assertSame('/pedidos/20', $capture['screen_url']);
        $this->assertSame('pedidos', $capture['modulo']);
        $this->assertSame('Confirmar pedido', $capture['flow']);
        $this->assertSame(['number' => 2, 'title' => 'Confirmar'], $capture['step']);
        $this->assertSame('Recorta el botón.', $capture['hint']);
        $this->assertSame(
            'comercial/pedidos-detalle/pedidos__confirmar__paso-02-confirmar.png',
            $capture['output']
        );
        $this->assertSame('modal', $capture['type']);
        $this->assertSame(['role' => 'dialog'], $capture['target']);
        $this->assertSame('Confirmar pedido', $capture['expected_text']);
        $this->assertSame(9, $capture['media_id']);

        $runner = (new ManualUsuarioCapturasInventory())->toRunnerManifest($manifest);
        $shot = $runner['roles'][0]['screens'][0]['shots'][0];
        $this->assertSame(1, $runner['version']);
        $this->assertSame('comercial', $runner['roles'][0]['slug']);
        $this->assertSame('pedidos-detalle', $runner['roles'][0]['screens'][0]['id']);
        $this->assertSame('/pedidos/20', $runner['roles'][0]['screens'][0]['url']);
        $this->assertSame('modal', $shot['type']);
        $this->assertSame(['role' => 'dialog'], $shot['target']);
        $this->assertSame('Confirmar pedido', $shot['expectedText']);
        $this->assertSame(9, $shot['manual']['mediaId']);
        $this->assertSame('pedidos__confirmar__paso-02-confirmar.png', $shot['manual']['output']);
        $this->assertSame([], (new ManualUsuarioCapturasInventory())->validateRunnerManifest($runner));
    }

    public function test_runner_manifest_aliases_duplicate_capture_keys(): void
    {
        $canonical = $this->mediaBlock(30, 'comercial', 'news__leer-avisos__paso-01-tarjetas-y-detalle');
        $alias = $this->mediaBlock(31, 'administracion', 'news__leer-avisos__paso-01-tarjetas-y-detalle');

        $inventory = new ManualUsuarioCapturasInventory();
        $runner = $inventory->toRunnerManifest($inventory->fromBlocks([$canonical, $alias], collect()));
        $shots = [];
        foreach ($runner['roles'] as $role) {
            foreach ($role['screens'] as $screen) {
                foreach ($screen['shots'] as $shot) {
                    $shots[] = $shot;
                }
            }
        }

        $enabled = array_values(array_filter($shots, fn (array $shot) => ($shot['enabled'] ?? true) !== false));
        $disabled = array_values(array_filter($shots, fn (array $shot) => ($shot['enabled'] ?? true) === false));
        $this->assertCount(1, $enabled);
        $this->assertCount(1, $disabled);
        $this->assertSame('news__leer-avisos__paso-01-tarjetas-y-detalle.png', $enabled[0]['manual']['output']);
        $this->assertSame('news__leer-avisos__paso-01-tarjetas-y-detalle', $disabled[0]['manual']['aliasOf']);
        $this->assertSame([], $inventory->validateRunnerManifest($runner));
    }

    private function mediaBlock(int $id, string $role, string $key): object
    {
        return (object) [
            'id' => $id,
            'pagina_id' => $id,
            'orden' => 1,
            'titulo' => 'Foto 1',
            'payload' => ['snapshot' => [
                'capture_key' => $key,
                'capture_role' => $role,
                'capture_screen' => 'news',
                'capture_screen_url' => '/news',
                'capture_modulo' => 'news',
                'media_id' => null,
            ]],
            'pagina' => (object) ['role_slug' => $role, 'modulo_key' => 'news'],
            'parent' => (object) ['titulo' => 'Leer avisos'],
        ];
    }
}
