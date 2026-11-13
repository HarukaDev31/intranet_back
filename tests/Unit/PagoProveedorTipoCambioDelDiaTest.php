<?php

namespace Tests\Unit;

use App\Models\PagoProveedor\PagoProveedorCotizacion;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * #1656: el tipo de cambio solo vale el día en que se cotizó o se confirmó (hora de Lima).
 */
class PagoProveedorTipoCambioDelDiaTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cotizacion(string $creada, bool $confirmado, ?string $confirmadoEn): PagoProveedorCotizacion
    {
        $cotizacion = new PagoProveedorCotizacion();
        $cotizacion->exchange_rate = 7.1;
        $cotizacion->exchange_rate_confirmed = $confirmado;
        $cotizacion->exchange_rate_confirmed_at = $confirmadoEn ? Carbon::parse($confirmadoEn, 'UTC') : null;
        $cotizacion->created_at = Carbon::parse($creada, 'UTC');

        return $cotizacion;
    }

    public function test_cotizacion_de_hoy_no_pide_confirmar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 20:00', 'UTC'));
        $this->assertTrue($this->cotizacion('2026-11-13 15:00', true, '2026-11-13 15:00')->exchangeRateConfirmedToday());
    }

    public function test_confirmacion_de_otro_dia_no_vale_hoy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 20:00', 'UTC'));
        $this->assertFalse($this->cotizacion('2026-11-11 15:00', true, '2026-11-11 15:00')->exchangeRateConfirmedToday());
        $this->assertFalse($this->cotizacion('2026-11-11 15:00', true, null)->exchangeRateConfirmedToday());
    }

    public function test_confirmado_hoy_en_cotizacion_antigua(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 20:00', 'UTC'));
        $this->assertTrue($this->cotizacion('2026-11-11 15:00', true, '2026-11-13 19:00')->exchangeRateConfirmedToday());
    }

    public function test_usa_la_fecha_de_lima(): void
    {
        // 13/11 02:00 UTC es aún 12/11 en Lima: una cotización de esa hora ya es "de ayer" el 13/11 en Lima.
        Carbon::setTestNow(Carbon::parse('2026-11-13 20:00', 'UTC'));
        $this->assertFalse($this->cotizacion('2026-11-13 02:00', true, '2026-11-13 02:00')->exchangeRateConfirmedToday());
    }
}
