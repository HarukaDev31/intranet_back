<?php

namespace App\Listeners\SoporteTi;

use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Models\Usuario;
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
        $emisorEsStaff = $this->esStaffSoporteTi($emisorId);

        if ($emisorEsStaff) {
            // Respondió alguien de soporte/PM -> avisar al solicitante.
            $destinatarios = array_values(array_filter([
                $solicitud->solicitante_user_id ? (int) $solicitud->solicitante_user_id : null,
            ]));
        } else {
            // Escribió el solicitante (o cualquier no-staff) -> avisar siempre a todo Soporte TI,
            // sin importar si el ticket ya tiene PM/analista asignado.
            $roles = array(Usuario::ROL_PM, Usuario::ROL_SOPORTE);
            $destinatarios = Usuario::query()
                ->whereHas('grupo', function ($q) use ($roles) {
                    $q->whereIn('No_Grupo', $roles);
                })
                ->where(function ($q) {
                    $q->where('Nu_Estado', 1)->orWhereNull('Nu_Estado');
                })
                ->pluck('ID_Usuario')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $destinatarios = array_values(array_unique(array_filter($destinatarios, fn ($id) => $id !== $emisorId)));

        Log::info('NotificarPushMensajeSoporteTi: procesando mensaje.', [
            'solicitud_id' => $solicitud->id,
            'emisor_id' => $emisorId,
            'emisor_es_staff' => $emisorEsStaff,
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

    private function esStaffSoporteTi(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        $usuario = Usuario::query()->with('grupo')->find($usuarioId);
        if (!$usuario) {
            return false;
        }

        $nombreGrupo = strtolower(trim((string) optional($usuario->grupo)->No_Grupo));

        return in_array($nombreGrupo, [
            strtolower(Usuario::ROL_PM),
            strtolower(Usuario::ROL_SOPORTE),
        ], true);
    }
}
