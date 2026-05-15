<?php

namespace App\Jobs\SoporteTi;

use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiMensajeImagen;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\SoporteTi\SoporteTiSolicitudEvidencia;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve un adjunto del chat desde disco local (pendiente) a público y crea registro de imagen + evidencia.
 *
 * @package App\Jobs\SoporteTi
 */
class ProcessSoporteTiChatAdjuntosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    protected $solicitudId;

    /** @var int */
    protected $mensajeId;

    /**
     * Lista de ['local_path' => string relativo a storage/app, 'nombre_original' => string, 'tamano_bytes' => int, 'mime' => ?string]
     *
     * @var array<int, array<string, mixed>>
     */
    protected $archivosPendientes;

    /** @var int */
    protected $ordenEvidencia;

    /**
     * @param array<int, array<string, mixed>> $archivosPendientes
     * @param int                             $ordenEvidencia
     */
    public function __construct($solicitudId, $mensajeId, array $archivosPendientes, $ordenEvidencia)
    {
        $this->solicitudId = (int) $solicitudId;
        $this->mensajeId = (int) $mensajeId;
        $this->archivosPendientes = $archivosPendientes;
        $this->ordenEvidencia = (int) $ordenEvidencia;
    }

    public function handle(SoporteTiService $service)
    {
        $solicitud = SoporteTiSolicitud::with('salaChat')->find($this->solicitudId);
        $mensaje = SoporteTiMensaje::find($this->mensajeId);

        if (!$solicitud || !$mensaje || !$solicitud->salaChat) {
            $this->eliminarPendientes();
            Log::warning('ProcessSoporteTiChatAdjuntosJob: solicitud o mensaje no encontrado', array(
                'solicitud_id' => $this->solicitudId,
                'mensaje_id' => $this->mensajeId,
            ));
            return;
        }

        $orden = 0;
        foreach ($this->archivosPendientes as $meta) {
            $localPath = isset($meta['local_path']) ? (string) $meta['local_path'] : '';
            if ($localPath === '' || strpos($localPath, '..') !== false) {
                continue;
            }
            if (strpos($localPath, 'soporte-ti/pending-chat/') !== 0) {
                Log::warning('ProcessSoporteTiChatAdjuntosJob: ruta pendiente inválida', array('path' => $localPath));
                continue;
            }
            if (!Storage::disk('local')->exists($localPath)) {
                Log::warning('ProcessSoporteTiChatAdjuntosJob: archivo pendiente ausente', array('path' => $localPath));
                continue;
            }

            $base = basename($localPath);
            $publicRel = 'soporte-ti/' . $this->solicitudId . '/chat/' . $base;
            $read = Storage::disk('local')->readStream($localPath);
            if ($read === false) {
                continue;
            }
            Storage::disk('public')->writeStream($publicRel, $read);
            if (is_resource($read)) {
                fclose($read);
            }
            Storage::disk('local')->delete($localPath);

            $url = Storage::disk('public')->url($publicRel);
            $bytes = isset($meta['tamano_bytes']) ? (int) $meta['tamano_bytes'] : 0;
            $tamano = $bytes > 1048576
                ? round($bytes / 1048576, 1) . ' MB'
                : round($bytes / 1024) . ' KB';
            $nombre = isset($meta['nombre_original']) ? (string) $meta['nombre_original'] : $base;
            $mime = isset($meta['mime']) ? (string) $meta['mime'] : null;

            SoporteTiMensajeImagen::create(array(
                'mensaje_id' => $mensaje->id,
                'url' => $url,
                'nombre' => $nombre,
                'tamano' => $tamano,
                'orden' => $orden++,
            ));

            SoporteTiSolicitudEvidencia::create(array(
                'solicitud_id' => $this->solicitudId,
                'mensaje_id' => $mensaje->id,
                'tipo' => 'imagen',
                'texto' => null,
                'url' => $url,
                'nombre' => $nombre,
                'tamano' => $tamano,
                'mime' => $mime,
                'orden' => $this->ordenEvidencia,
            ));
        }

        $mensaje->load(array('imagenes', 'replyTo'));
        event(new SoporteTiMensajeCreado($solicitud, $service->mapMensaje($mensaje, null)));
    }

    public function failed(\Throwable $e)
    {
        Log::error('ProcessSoporteTiChatAdjuntosJob falló', array(
            'solicitud_id' => $this->solicitudId,
            'mensaje_id' => $this->mensajeId,
            'error' => $e->getMessage(),
        ));
        $this->eliminarPendientes();
    }

    protected function eliminarPendientes()
    {
        foreach ($this->archivosPendientes as $meta) {
            $localPath = isset($meta['local_path']) ? (string) $meta['local_path'] : '';
            if ($localPath !== '' && Storage::disk('local')->exists($localPath)) {
                Storage::disk('local')->delete($localPath);
            }
        }
    }
}
