<?php

namespace App\Events\CargaConsolidada;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeguimientoConsolidadoDriveLinkUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int */
    public $idContenedor;

    /** @var array<string, mixed> */
    public $data;

    /**
     * @param int $idContenedor
     * @param array<string, mixed> $data
     */
    public function __construct($idContenedor, array $data)
    {
        $this->idContenedor = (int) $idContenedor;
        $this->data = $data;
    }

    /**
     * @return \Illuminate\Broadcasting\PrivateChannel
     */
    public function broadcastOn()
    {
        return new PrivateChannel('carga-consolidada.seguimiento-drive.' . $this->idContenedor);
    }

    /**
     * @return string
     */
    public function broadcastAs()
    {
        return 'SeguimientoConsolidadoDriveLinkUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith()
    {
        return [
            'id_contenedor' => $this->idContenedor,
            'data' => $this->data,
        ];
    }
}
