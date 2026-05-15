<?php

namespace App\Events\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\SoporteTi\SoporteTiSolicitudEstado;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SoporteTiEstadoActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payload;

    public function __construct(SoporteTiSolicitud $solicitud, SoporteTiSolicitudEstado $historial = null)
    {
        $solicitud->load('estadoActual', 'salaChat');
        $estado = $solicitud->estadoActual;
        $service = app(SoporteTiService::class);

        $historialApi = null;
        if ($historial) {
            $historial->load(array('estado', 'estadoAnterior', 'usuario'));
            $historialApi = $service->mapHistorial($historial);
        }

        $ultima = $solicitud->ultima_actualizacion
            ? $solicitud->ultima_actualizacion->format('c')
            : now()->toIso8601String();

        $this->payload = array(
            'chat_uuid' => $solicitud->salaChat ? $solicitud->salaChat->chat_uuid : null,
            'codigo' => $solicitud->codigo,
            'estado_id' => $estado ? (int) $estado->id : (int) $solicitud->estado_actual_id,
            'estado_codigo' => $estado ? $estado->codigo : '',
            'estado' => $estado ? $estado->nombre : '',
            'historial' => $historialApi,
            'fase_index' => (int) $solicitud->fase_index,
            'progreso' => (int) $solicitud->progreso,
            'ultima_actualizacion' => $ultima,
            'titulo' => $solicitud->titulo,
        );
    }

    public function broadcastOn()
    {
        return new PrivateChannel('soporte-ti.chat.' . $this->payload['chat_uuid']);
    }

    public function broadcastAs()
    {
        return 'SoporteTiEstadoActualizado';
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
