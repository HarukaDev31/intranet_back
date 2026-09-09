<?php

namespace App\Listeners\SoporteTi;

use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Services\Firebase\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificarPushMensajeSoporteTi implements ShouldQueue
{
    use InteractsWithQueue;

    public function viaQueue()
    {
        return (string) config('soporte-ti.queue', 'soporte_ti');
    }

    public function handle(SoporteTiMensajeCreado $event)
    {
        $solicitud = $event->getSolicitud();
        $mensaje = $event->mensaje;

        $emisorId = (int) ($mensaje['usuario_id'] ?? 0);
        $destinatarios = array_values(array_unique(array_filter([
            $solicitud->solicitante_user_id ? (int) $solicitud->solicitante_user_id : null,
            $solicitud->pm_user_id ? (int) $solicitud->pm_user_id : null,
            $solicitud->analista_user_id ? (int) $solicitud->analista_user_id : null,
        ])));

        $destinatarios = array_values(array_filter($destinatarios, fn ($id) => $id !== $emisorId));

        Log::info('NotificarPushMensajeSoporteTi: procesando mensaje.', [
            'solicitud_id' => $solicitud->id,
            'emisor_id' => $emisorId,
            'destinatarios' => $destinatarios,
        ]);

        if (empty($destinatarios)) {
            Log::info('NotificarPushMensajeSoporteTi: sin destinatarios (solo el emisor está involucrado en el ticket), no se envía push.', [
                'solicitud_id' => $solicitud->id,
            ]);
            return;
        }

        $texto = trim((string) ($mensaje['texto'] ?? ''));
        if ($texto === '') {
            $texto = !empty($mensaje['imagenes']) ? 'Imagen adjunta' : 'Nuevo mensaje';
        }

        $title = 'Soporte TI · ' . $solicitud->codigo;
        $body = Str::limit($texto, 120);

        $data = [
            'tipo' => 'soporte_ti_mensaje',
            'solicitud_id' => (string) $solicitud->id,
            'chat_uuid' => (string) $event->chatUuid,
            'mensaje_id' => (string) ($mensaje['id'] ?? ''),
        ];

        app(FcmPushService::class)->sendToUsuarios($destinatarios, $title, $body, $data);
    }
}
