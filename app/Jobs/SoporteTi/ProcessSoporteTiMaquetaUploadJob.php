<?php

namespace App\Jobs\SoporteTi;

use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Models\SoporteTi\SoporteTiMaqueta;
use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiMensajeImagen;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Services\SoporteTi\SoporteTiCacheService;
use App\Services\SoporteTi\SoporteTiService;
use App\Support\SoporteTi\SoporteTiQueue;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve la maqueta desde pendiente a disco público, actualiza el registro y adjunta imagen al mensaje.
 *
 * @package App\Jobs\SoporteTi
 */
class ProcessSoporteTiMaquetaUploadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SoporteTiQueue;

    /** @var int */
    protected $solicitudId;

    /** @var int */
    protected $mensajeId;

    /** @var int */
    protected $maquetaId;

    /** @var string */
    protected $localPathPending;

    public function __construct($solicitudId, $mensajeId, $maquetaId, $localPathPending)
    {
        $this->solicitudId = (int) $solicitudId;
        $this->mensajeId = (int) $mensajeId;
        $this->maquetaId = (int) $maquetaId;
        $this->localPathPending = (string) $localPathPending;
        $this->assignSoporteTiQueue();
    }

    public function handle(SoporteTiService $service)
    {
        $solicitud = SoporteTiSolicitud::with('salaChat')->find($this->solicitudId);
        $maqueta = SoporteTiMaqueta::find($this->maquetaId);

        if (!$solicitud || !$maqueta) {
            $this->eliminarPendienteSiExiste();
            Log::warning('ProcessSoporteTiMaquetaUploadJob: solicitud o maqueta no encontrada', array(
                'solicitud_id' => $this->solicitudId,
                'maqueta_id' => $this->maquetaId,
            ));
            return;
        }

        $path = $this->localPathPending;
        if ($path === '' || strpos($path, '..') !== false || strpos($path, 'soporte-ti/pending-maqueta/') !== 0) {
            Log::warning('ProcessSoporteTiMaquetaUploadJob: ruta pendiente inválida', array('path' => $path));
            return;
        }

        if (!Storage::disk('local')->exists($path)) {
            Log::warning('ProcessSoporteTiMaquetaUploadJob: archivo pendiente ausente', array('path' => $path));
            return;
        }

        $base = basename($path);
        $publicRel = 'soporte-ti/' . $this->solicitudId . '/maqueta/' . $base;
        $read = Storage::disk('local')->readStream($path);
        if ($read === false) {
            return;
        }
        Storage::disk('public')->writeStream($publicRel, $read);
        if (is_resource($read)) {
            fclose($read);
        }
        Storage::disk('local')->delete($path);

        $url = Storage::disk('public')->url($publicRel);
        $maqueta->ruta_archivo = $publicRel;
        $maqueta->url_preview = $url;
        $maqueta->save();

        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $mensaje = null;
        if ($this->mensajeId > 0) {
            $mensaje = SoporteTiMensaje::find($this->mensajeId);
        }

        if ($mensaje && $solicitud->salaChat) {
            SoporteTiMensajeImagen::create(array(
                'mensaje_id' => $mensaje->id,
                'url' => $url,
                'nombre' => $maqueta->nombre,
                'tamano' => $maqueta->tamano,
                'orden' => 0,
            ));

            $mensaje->load(array('imagenes', 'replyTo'));
            event(new SoporteTiMensajeCreado($solicitud, $service->mapMensaje($mensaje, null)));

            $service->crearMensajeSistema(
                $solicitud->salaChat,
                $solicitud,
                'Maqueta "' . $maqueta->nombre . '" subida. Pendiente de aprobación del solicitante.'
            );
        }

        $solicitud->load('salaChat');
        app(SoporteTiCacheService::class)->invalidateAfterSolicitudWrite($solicitud);
    }

    public function failed(\Throwable $e)
    {
        Log::error('ProcessSoporteTiMaquetaUploadJob falló', array(
            'solicitud_id' => $this->solicitudId,
            'maqueta_id' => $this->maquetaId,
            'error' => $e->getMessage(),
        ));
        $this->eliminarPendienteSiExiste();
    }

    protected function eliminarPendienteSiExiste()
    {
        if ($this->localPathPending !== '' && Storage::disk('local')->exists($this->localPathPending)) {
            Storage::disk('local')->delete($this->localPathPending);
        }
    }
}
