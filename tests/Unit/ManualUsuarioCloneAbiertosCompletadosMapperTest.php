<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCloneAbiertosCompletadosMapper;
use PHPUnit\Framework\TestCase;

class ManualUsuarioCloneAbiertosCompletadosMapperTest extends TestCase
{
    public function test_maps_same_role_flow_and_step_number_even_if_titles_differ(): void
    {
        $source = $this->row(
            'jefe-importacion',
            'Documentación',
            1,
            'Cómo se ve y cómo interactúas',
            'cargaconsolidada-abiertos__documentacion__paso-01-como-se-ve-y-como-interactuas',
            10
        );
        $target = $this->row(
            'jefe-importacion',
            'Documentación',
            1,
            'Qué puedes hacer',
            'cargaconsolidada-completados__documentacion__paso-01-que-puedes-hacer'
        );

        $matched = ManualUsuarioCloneAbiertosCompletadosMapper::match([$source], $target);

        $this->assertNotNull($matched);
        $this->assertSame(ManualUsuarioCloneAbiertosCompletadosMapper::MATCH_ROLE_FLOW_STEP, $matched['match']);
        $this->assertSame($source['capture_key'], $matched['capture_key']);
        $this->assertSame(10, $matched['media_id']);
    }

    public function test_does_not_map_step_number_across_roles(): void
    {
        $source = $this->row(
            'cotizador',
            'Cotización',
            1,
            'Cómo se ve y cómo interactúas',
            'cargaconsolidada-abiertos__cotizacion__paso-01-como-se-ve-y-como-interactuas',
            11
        );
        $target = $this->row(
            'jefe-marketing',
            'Cotización',
            1,
            'Consultar',
            'cargaconsolidada-completados__cotizacion__paso-01-consultar'
        );

        $this->assertNull(ManualUsuarioCloneAbiertosCompletadosMapper::match([$source], $target));
    }

    public function test_maps_identical_titles_across_roles(): void
    {
        $source = $this->row(
            'coordinacion',
            'Documentación',
            4,
            'Descargar plantillas',
            'cargaconsolidada-abiertos__documentacion__paso-04-descargar-plantillas',
            12
        );
        $target = $this->row(
            'administracion',
            'Documentación',
            1,
            'Descargar plantillas',
            'cargaconsolidada-completados__documentacion__paso-01-descargar-plantillas'
        );

        $matched = ManualUsuarioCloneAbiertosCompletadosMapper::match([$source], $target);

        $this->assertNotNull($matched);
        $this->assertSame(ManualUsuarioCloneAbiertosCompletadosMapper::MATCH_FLOW_TITLE, $matched['match']);
    }

    public function test_maps_when_only_modulo_slug_changes_in_capture_key(): void
    {
        $source = $this->row(
            'cotizador',
            'Cotización',
            2,
            'Subir o quitar la cotización',
            'cargaconsolidada-abiertos__cotizacion__paso-02-subir-o-quitar-la-cotizacion',
            13
        );
        $target = $this->row(
            'finanzas',
            'Cotización',
            9,
            'Otra acción',
            'cargaconsolidada-completados__cotizacion__paso-02-subir-o-quitar-la-cotizacion'
        );

        $matched = ManualUsuarioCloneAbiertosCompletadosMapper::match([$source], $target);

        $this->assertNotNull($matched);
        $this->assertSame(ManualUsuarioCloneAbiertosCompletadosMapper::MATCH_SWAPPED_KEY, $matched['match']);
    }

    public function test_plan_leaves_unmatched_completados_steps_skipped(): void
    {
        $source = [$this->row(
            'cotizador',
            'Cotización',
            1,
            'Cómo se ve y cómo interactúas',
            'cargaconsolidada-abiertos__cotizacion__paso-01-como-se-ve-y-como-interactuas',
            14
        )];
        $target = [
            $this->row(
                'cotizador',
                'Cotización',
                1,
                'Cómo se ve y cómo interactúas',
                'cargaconsolidada-completados__cotizacion__paso-01-como-se-ve-y-como-interactuas'
            ),
            $this->row(
                'finanzas',
                'Plantillas finales',
                1,
                'Cuándo aparece la carga',
                'cargaconsolidada-completados__plantillas-finales__paso-01-cuando-aparece-la-carga'
            ),
        ];

        $plan = ManualUsuarioCloneAbiertosCompletadosMapper::plan($source, $target);

        $this->assertCount(1, $plan['links']);
        $this->assertCount(1, $plan['skipped']);
        $this->assertSame('no_equivalent', $plan['skipped'][0]['reason']);
        $this->assertSame(
            'cargaconsolidada-completados__plantillas-finales__paso-01-cuando-aparece-la-carga',
            $plan['skipped'][0]['target_key']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $role,
        string $flow,
        int $number,
        string $title,
        string $key,
        ?int $mediaId = null
    ): array {
        $modulo = str_contains($key, 'completados')
            ? 'cargaconsolidada/completados'
            : 'cargaconsolidada/abiertos';

        return [
            'block_id' => abs(crc32($role . $key)),
            'role' => $role,
            'modulo' => $modulo,
            'flow' => $flow,
            'step_number' => $number,
            'step_title' => $title,
            'capture_key' => $key,
            'capture_output' => $key . '.png',
            'media_id' => $mediaId,
            'media_path' => $mediaId ? 'manual/capturas/' . $key . '.png' : null,
        ];
    }
}
