<?php

namespace App\Listeners\SoporteTi;

use App\Events\SoporteTi\SoporteTiSolicitudCreada;
use App\Models\Usuario;
use App\Services\Firebase\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class NotificarPushSolicitudCreada implements ShouldQueue
{
    use InteractsWithQueue;

    public function viaQueue()
    {
        return (string) config('soporte-ti.queue', 'soporte_ti');
    }

    public function handle(SoporteTiSolicitudCreada $event)
    {
        $solicitud = $event->payload['solicitud'] ?? null;
        if (!is_array($solicitud)) {
            return;
        }

        $roles = array(Usuario::ROL_PM, Usuario::ROL_SOPORTE);

        $staffIds = Usuario::query()
            ->whereHas('grupo', function ($q) use ($roles) {
                $q->whereIn('No_Grupo', $roles);
            })
            ->where(function ($q) {
                $q->where('Nu_Estado', 1)->orWhereNull('Nu_Estado');
            })
            ->pluck('ID_Usuario')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($staffIds)) {
            return;
        }

        $titulo = 'Nuevo ticket · ' . ($solicitud['codigo'] ?? ('#' . ($solicitud['id'] ?? '')));
        $body = Str::limit((string) ($solicitud['titulo'] ?? 'Se registró una nueva solicitud de soporte'), 120);

        $data = array(
            'tipo' => 'soporte_ti_solicitud_creada',
            'solicitud_id' => (string) ($solicitud['id'] ?? ''),
            'chat_uuid' => (string) ($solicitud['chat_uuid'] ?? ''),
        );

        app(FcmPushService::class)->sendToUsuarios($staffIds, $titulo, $body, $data);
    }
}
