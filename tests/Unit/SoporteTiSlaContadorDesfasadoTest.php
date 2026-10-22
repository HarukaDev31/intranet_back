<?php

namespace Tests\Unit;

use App\Services\SoporteTi\SoporteTiService;
use PHPUnit\Framework\TestCase;

class SoporteTiSlaContadorDesfasadoTest extends TestCase
{
    public function test_contador_en_cero_con_historial_consumido_se_resincroniza()
    {
        $this->assertTrue(SoporteTiService::slaContadorDesfasado(0, 3600 * 10));
    }

    public function test_contador_al_dia_no_se_resincroniza()
    {
        $this->assertFalse(SoporteTiService::slaContadorDesfasado(3600 * 10, 3600 * 10 + 30));
        $this->assertFalse(SoporteTiService::slaContadorDesfasado(0, 0));
    }

    public function test_contador_mayor_que_historial_no_se_toca()
    {
        $this->assertFalse(SoporteTiService::slaContadorDesfasado(3600 * 12, 3600 * 10));
    }
}
