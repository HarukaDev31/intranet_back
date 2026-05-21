<?php

namespace App\Events;

use App\Models\CargaConsolidada\ConsolidadoPlantillaFinalBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlantillaFinalBatchFinished implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var ConsolidadoPlantillaFinalBatch */
    public $batch;

    /** @var string */
    public $message;

    /** @var string */
    public $queue = 'notificaciones';

    public function __construct(ConsolidadoPlantillaFinalBatch $batch, $message = '')
    {
        $this->batch = $batch;
        $this->message = (string) $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('Coordinacion-notifications');
    }

    public function broadcastAs()
    {
        return 'PlantillaFinalBatchFinished';
    }

    public function broadcastWith()
    {
        return [
            'batch_id' => (int) $this->batch->id,
            'id_contenedor' => (int) $this->batch->id_contenedor,
            'estado' => $this->batch->estado,
            'message' => $this->message,
            'clientes_excel' => (int) $this->batch->clientes_excel,
            'clientes_completados' => (int) $this->batch->clientes_completados,
            'clientes_error' => (int) $this->batch->clientes_error,
            'tipo_evento' => 'plantilla_final_batch_finished',
        ];
    }
}
