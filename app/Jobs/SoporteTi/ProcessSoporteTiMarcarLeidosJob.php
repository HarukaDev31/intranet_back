<?php

namespace App\Jobs\SoporteTi;

use App\Events\SoporteTi\SoporteTiMensajesLeidos;
use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiMensajeLectura;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Services\SoporteTi\SoporteTiCacheService;
use App\Services\SoporteTi\SoporteTiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Registra lecturas en lote y emite WS con los mensajes que pasan a "leído" para el autor.
 */
class ProcessSoporteTiMarcarLeidosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    protected $solicitudId;

    /** @var int */
    protected $lectorUsuarioId;

    /** @var int[] */
    protected $mensajeIds;

    /**
     * @param int   $solicitudId
     * @param int   $lectorUsuarioId
     * @param int[] $mensajeIds
     */
    public function __construct($solicitudId, $lectorUsuarioId, array $mensajeIds)
    {
        $this->solicitudId = (int) $solicitudId;
        $this->lectorUsuarioId = (int) $lectorUsuarioId;
        $this->mensajeIds = array_values(array_map('intval', $mensajeIds));
    }

    public function handle()
    {
        if (empty($this->mensajeIds)) {
            return;
        }

        $solicitud = SoporteTiSolicitud::with('salaChat')->find($this->solicitudId);
        if (!$solicitud || !$solicitud->salaChat) {
            return;
        }

        $service = app(SoporteTiService::class);
        $now = Carbon::now();
        $salaId = (int) $solicitud->salaChat->id;

        $mensajes = SoporteTiMensaje::whereIn('id', $this->mensajeIds)
            ->where('sala_id', $salaId)
            ->get()
            ->keyBy('id');

        foreach ($this->mensajeIds as $mensajeId) {
            /** @var SoporteTiMensaje|null $mensaje */
            $mensaje = $mensajes->get($mensajeId);
            if (!$mensaje) {
                continue;
            }
            if ($mensaje->es_sistema || !$mensaje->usuario_id) {
                continue;
            }
            if ((int) $mensaje->usuario_id === $this->lectorUsuarioId) {
                continue;
            }

            SoporteTiMensajeLectura::firstOrCreate(
                array(
                    'mensaje_id' => (int) $mensaje->id,
                    'usuario_id' => $this->lectorUsuarioId,
                ),
                array('leido_en' => $now)
            );
        }

        $service->asegurarMiembroSalaPublico($salaId, $this->lectorUsuarioId, 'participante');

        $confirmados = array();
        foreach ($this->mensajeIds as $mensajeId) {
            $mensaje = $mensajes->get($mensajeId);
            if (!$mensaje) {
                continue;
            }
            if ($service->mensajeLeidoPorDestinatarios($mensaje)) {
                $confirmados[] = (int) $mensaje->id;
            }
        }

        $confirmados = array_values(array_unique($confirmados));
        if (!empty($confirmados)) {
            event(new SoporteTiMensajesLeidos($solicitud, $this->lectorUsuarioId, $confirmados));
        }

        $autores = SoporteTiMensaje::whereIn('id', $this->mensajeIds)
            ->whereNotNull('usuario_id')
            ->pluck('usuario_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $cache = app(SoporteTiCacheService::class);
        $cache->invalidateAfterLecturasWrite(
            (string) $solicitud->salaChat->chat_uuid,
            array_merge($autores, array($this->lectorUsuarioId))
        );
    }
}
