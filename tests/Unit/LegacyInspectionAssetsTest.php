<?php

namespace Tests\Unit;

use App\Support\Storage\LegacyInspectionAssets;
use Tests\TestCase;

class LegacyInspectionAssetsTest extends TestCase
{
    private const LEGACY_URL = 'https://intranet.probusiness.pe/assets/images/1765516769_%E5%BE%AE%E4%BF%A1%E5%9B%BE%E7%89%87_20251212131331_2077_2.jpg';

    public function test_extrae_nombre_decodificado_de_url_legacy()
    {
        $this->assertSame(
            '1765516769_微信图片_20251212131331_2077_2.jpg',
            LegacyInspectionAssets::legacyRelativeName(self::LEGACY_URL)
        );
        $this->assertSame(
            'agentecompra/1738798955_1738029284_JOSE SMITH.xlsx',
            LegacyInspectionAssets::legacyRelativeName('https://intranet.probusiness.pe/assets/images/agentecompra/1738798955_1738029284_JOSE%20SMITH.xlsx')
        );
        $this->assertNull(LegacyInspectionAssets::legacyRelativeName('inspection/1774496169_69c4a9a92ca4f.jpg'));
    }

    public function test_detecta_rutas_reubicadas()
    {
        $this->assertTrue(LegacyInspectionAssets::isRelocatedPath('assets/imgs/1765_a.jpg'));
        $this->assertTrue(LegacyInspectionAssets::isRelocatedPath('/assets/imgs/1765_a.jpg'));
        $this->assertFalse(LegacyInspectionAssets::isRelocatedPath(self::LEGACY_URL));
        $this->assertFalse(LegacyInspectionAssets::isRelocatedPath('inspection/x.jpg'));
        $this->assertFalse(LegacyInspectionAssets::isRelocatedPath(null));
    }

    public function test_cdn_url_codifica_una_sola_vez()
    {
        $esperado = 'https://cdn.probusiness.pe/assets/imgs/1765516769_%E5%BE%AE%E4%BF%A1%E5%9B%BE%E7%89%87_20251212131331_2077_2.jpg';

        // Nombre decodificado (lo que deja el comando con --update-db)
        $this->assertSame($esperado, LegacyInspectionAssets::cdnUrl(
            'assets/imgs/1765516769_微信图片_20251212131331_2077_2.jpg',
            'https://cdn.probusiness.pe/'
        ));

        // Nombre aún codificado (lo que deja un UPDATE SQL con SUBSTRING_INDEX)
        $this->assertSame($esperado, LegacyInspectionAssets::cdnUrl(
            'assets/imgs/1765516769_%E5%BE%AE%E4%BF%A1%E5%9B%BE%E7%89%87_20251212131331_2077_2.jpg',
            'https://cdn.probusiness.pe'
        ));

        $this->assertSame(
            'https://cdn.probusiness.pe/assets/imgs/agentecompra/1738798955_1738029284_JOSE%20SMITH.xlsx',
            LegacyInspectionAssets::cdnUrl('assets/imgs/agentecompra/1738798955_1738029284_JOSE SMITH.xlsx', 'https://cdn.probusiness.pe')
        );

        $this->assertNull(LegacyInspectionAssets::cdnUrl('inspection/x.jpg', 'https://cdn.probusiness.pe'));
    }
}
