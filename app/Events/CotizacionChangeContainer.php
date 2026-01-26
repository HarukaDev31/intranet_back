<?php

namespace App\Events;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CotizacionChangeContainer implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cotizacion;
    public $contenedorOrigen;
    public $contenedorDestino;
    public $usuario;
    public $message;
    public $queue = 'notificaciones';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Cotizacion $cotizacion, Contenedor $contenedorOrigen, Contenedor $contenedorDestino, $usuario, string $message)
    {
        Log::info('CotizacionChangeContainer', [
            'cotizacion_id' => $cotizacion->id,
            'contenedor_origen_id' => $contenedorOrigen->id,
            'contenedor_destino_id' => $contenedorDestino->id,
            'usuario_id' => $usuario->ID_Usuario,
            'message' => $message
        ]);

        $this->cotizacion = $cotizacion;
        $this->contenedorOrigen = $contenedorOrigen;
        $this->contenedorDestino = $contenedorDestino;
        $this->usuario = $usuario;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('Coordinacion-notifications'),
            new PrivateChannel('Cotizador-notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_nombre' => $this->cotizacion->nombre,
            'contenedor_origen_id' => $this->contenedorOrigen->id,
            'contenedor_origen_carga' => $this->contenedorOrigen->carga,
            'contenedor_destino_id' => $this->contenedorDestino->id,
            'contenedor_destino_carga' => $this->contenedorDestino->carga,
            'usuario_id' => $this->usuario->ID_Usuario,
            'usuario_nombre' => $this->usuario->No_Nombres_Apellidos,
            'message' => $this->message,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'CotizacionChangeContainer';
    }
}

