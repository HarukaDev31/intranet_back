<?php

namespace App\Listeners\SoporteTi;

use App\Events\SoporteTi\SoporteTiSolicitudCreada;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Al crear un ticket Tipo B (INC/CFG), dispara el agente de soporte (scheduled task de Claude Code)
 * vía su API trigger. El agente vuelve a leer el ticket desde la API: aquí solo se envía id y código.
 */
class DispararAgenteSoporteTi implements ShouldQueue
{
    use InteractsWithQueue;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = array(30, 120);

    public function viaQueue()
    {
        return (string) config('soporte-ti.queue', 'soporte_ti');
    }

    public function shouldQueue(SoporteTiSolicitudCreada $event)
    {
        return (bool) config('soporte-ti.agente_enabled')
            && (string) config('soporte-ti.agente_fire_url') !== ''
            && (string) config('soporte-ti.agente_token') !== '';
    }

    public function handle(SoporteTiSolicitudCreada $event)
    {
        $solicitud = $event->payload['solicitud'] ?? null;
        if (!is_array($solicitud) || empty($solicitud['id'])) {
            return;
        }

        if (($solicitud['tipo_solicitud'] ?? null) !== 'B') {
            return;
        }

        $codigo = trim((string) ($solicitud['codigo'] ?? ''));
        $texto = 'Nuevo ticket Soporte TI: id=' . (int) $solicitud['id'] . ' codigo=' . $codigo;

        $response = Http::timeout(15)
            ->withToken((string) config('soporte-ti.agente_token'))
            ->withHeaders(array(
                'anthropic-beta' => 'experimental-cc-routine-2026-04-01',
                'anthropic-version' => '2023-06-01',
            ))
            ->post((string) config('soporte-ti.agente_fire_url'), array('text' => $texto));

        if ($response->failed()) {
            Log::warning('DispararAgenteSoporteTi: el agente no aceptó el disparo.', array(
                'solicitud_id' => $solicitud['id'],
                'status' => $response->status(),
            ));
            $response->throw();
        }

        Log::info('DispararAgenteSoporteTi: agente disparado.', array(
            'solicitud_id' => $solicitud['id'],
            'session_url' => $response->json('claude_code_session_url'),
        ));
    }
}
