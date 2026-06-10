<?php

namespace App\Events\CargaConsolidada;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentacionExpedienteObservacionCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var string */
    public $queue = 'notificaciones';

    /** @var int */
    public $idProveedor;

    /** @var array<string, mixed> */
    public $observacion;

    /**
     * @param int $idProveedor
     * @param array<string, mixed> $observacion
     */
    public function __construct($idProveedor, array $observacion)
    {
        $this->idProveedor = (int) $idProveedor;
        $this->observacion = $observacion;
    }

    /**
     * @return \Illuminate\Broadcasting\PrivateChannel
     */
    public function broadcastOn()
    {
        return new PrivateChannel('coordinacion-documentacion-expediente.' . $this->idProveedor);
    }

    /**
     * @return string
     */
    public function broadcastAs()
    {
        return 'DocumentacionExpedienteObservacionCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith()
    {
        return [
            'id_proveedor' => $this->idProveedor,
            'observacion' => $this->observacion,
        ];
    }
}
