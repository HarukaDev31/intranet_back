<?php

namespace Tests\Unit;

use App\Services\ManualUsuario\ManualUsuarioCapturaShare;
use PHPUnit\Framework\TestCase;

class ManualUsuarioCapturaShareTest extends TestCase
{
    public function test_expected_key_includes_step_not_just_flow(): void
    {
        $grid = ManualUsuarioCapturaShare::expectedKeyFromContext(
            'cargaconsolidada/completados',
            'Documentación',
            'Cómo se ve y cómo interactúas',
            1
        );
        $factura = ManualUsuarioCapturaShare::expectedKeyFromContext(
            'cargaconsolidada/completados',
            'Documentación',
            'Factura General: qué hace de verdad',
            3
        );

        $this->assertSame(
            'cargaconsolidada-completados__documentacion__paso-01-como-se-ve-y-como-interactuas',
            $grid
        );
        $this->assertSame(
            'cargaconsolidada-completados__documentacion__paso-03-factura-general-que-hace-de-verdad',
            $factura
        );
        $this->assertNotSame($grid, $factura);
    }

    public function test_news_same_step_shares_across_roles(): void
    {
        $left = ManualUsuarioCapturaShare::expectedKeyFromContext(
            'news',
            'Leer avisos',
            'tarjetas y detalle',
            1
        );
        $right = ManualUsuarioCapturaShare::expectedKeyFromContext(
            'news',
            'Leer avisos',
            'tarjetas y detalle',
            1
        );

        $this->assertTrue(ManualUsuarioCapturaShare::shouldShare($left, $right));
        $this->assertSame('news__leer-avisos__paso-01-tarjetas-y-detalle', $left);
    }

    public function test_rekeys_flow_level_key_to_include_step(): void
    {
        $this->assertTrue(ManualUsuarioCapturaShare::shouldRekey(
            'carga-documentacion',
            'cargaconsolidada-completados__documentacion__paso-01-como-se-ve-y-como-interactuas'
        ));
        $this->assertFalse(ManualUsuarioCapturaShare::shouldRekey(
            'cargaconsolidada-completados__documentacion__paso-01-como-se-ve-y-como-interactuas',
            'cargaconsolidada-completados__documentacion__paso-01-como-se-ve-y-como-interactuas'
        ));
    }

    public function test_nombre_inherited_from_page_is_refreshed(): void
    {
        $this->assertTrue(ManualUsuarioCapturaShare::nombreNeedsRefresh(
            'Carga consolidada — Completados — Descargar plantillas',
            'Documentación — Cómo se ve y cómo interactúas',
            'Cómo se ve y cómo interactúas',
            'Carga consolidada — Completados'
        ));
        $this->assertFalse(ManualUsuarioCapturaShare::nombreNeedsRefresh(
            'Documentación — Cómo se ve y cómo interactúas',
            'Documentación — Cómo se ve y cómo interactúas',
            'Cómo se ve y cómo interactúas',
            'Carga consolidada — Completados'
        ));
    }

    public function test_plan_unlinks_media_shared_across_distinct_steps(): void
    {
        $plan = ManualUsuarioCapturaShare::planUngroup([
            $this->row(1, 'paso-01', 'paso-01', 10, 'paso-01', 'Cómo se ve y cómo interactúas'),
            $this->row(2, 'paso-04', 'paso-04', 10, 'paso-01', 'Descargar plantillas'),
            $this->row(3, 'paso-06', 'paso-06', 10, 'paso-01', 'Nuevo documento'),
        ]);

        $this->assertSame([2, 3], $plan['unlink_media']);
        $this->assertSame(2, $plan['flows'][0]['unlinked']);
        $this->assertSame('Documentación', $plan['flows'][0]['flow']);
    }

    public function test_plan_keeps_media_when_same_canonical_step(): void
    {
        $plan = ManualUsuarioCapturaShare::planUngroup([
            $this->row(10, 'news-paso-01', 'news-paso-01', 7, 'news-paso-01', 'tarjetas y detalle', 'Noticias', 'news'),
            $this->row(11, 'news-paso-01', 'news-paso-01', 7, 'news-paso-01', 'tarjetas y detalle', 'Noticias', 'news'),
        ]);

        $this->assertSame([], $plan['unlink_media']);
        $this->assertSame([], $plan['flows']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        $id,
        $identity,
        $expected,
        $mediaId,
        $mediaAlt,
        $step,
        $flow = 'Documentación',
        $modulo = 'cargaconsolidada/completados'
    ) {
        return [
            'block_id' => $id,
            'identity' => $identity,
            'expected' => $expected,
            'media_id' => $mediaId,
            'media_alt' => $mediaAlt,
            'nombre' => 'Carga consolidada — Completados — Descargar plantillas',
            'derived' => $flow . ' — ' . $step,
            'step_title' => $step,
            'page_titulo' => 'Carga consolidada — Completados',
            'flow' => $flow,
            'modulo' => $modulo,
        ];
    }
}
