<?php

namespace Tests\Unit;

use App\Events\SoporteTi\SoporteTiSolicitudCreada;
use App\Listeners\SoporteTi\DispararAgenteSoporteTi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispararAgenteSoporteTiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(array(
            'soporte-ti.agente_enabled' => true,
            'soporte-ti.agente_fire_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_test/fire',
            'soporte-ti.agente_token' => 'token-test',
        ));
    }

    public function test_dispara_el_agente_para_ticket_tipo_b()
    {
        Http::fake(array('*' => Http::response(array('claude_code_session_url' => 'https://claude.ai/code/session_x'))));

        $event = new SoporteTiSolicitudCreada(array('id' => 48, 'codigo' => 'INC-26-0048', 'tipo_solicitud' => 'B'));
        (new DispararAgenteSoporteTi())->handle($event);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.anthropic.com/v1/claude_code/routines/trig_test/fire'
                && $request->hasHeader('Authorization', 'Bearer token-test')
                && $request->hasHeader('anthropic-beta', 'experimental-cc-routine-2026-04-01')
                && $request['text'] === 'Nuevo ticket Soporte TI: id=48 codigo=INC-26-0048';
        });
    }

    public function test_no_dispara_para_proyectos_tipo_a()
    {
        Http::fake();

        $event = new SoporteTiSolicitudCreada(array('id' => 7, 'codigo' => 'PRY-26-0007', 'tipo_solicitud' => 'A'));
        (new DispararAgenteSoporteTi())->handle($event);

        Http::assertNothingSent();
    }

    public function test_no_se_encola_si_falta_configuracion()
    {
        config(array('soporte-ti.agente_token' => ''));

        $event = new SoporteTiSolicitudCreada(array('id' => 48, 'codigo' => 'INC-26-0048', 'tipo_solicitud' => 'B'));

        $this->assertFalse((new DispararAgenteSoporteTi())->shouldQueue($event));
    }
}
