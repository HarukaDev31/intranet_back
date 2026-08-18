<?php

namespace App\Events;

use App\Models\CargaConsolidada\ConsolidadoFacturaComercialBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FacturaComercialBatchFinished implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, Queueable, SerializesModels;

    /** @var ConsolidadoFacturaComercialBatch */
    public $batch;

    /** @var string */
    public $message;

    public function __construct(ConsolidadoFacturaComercialBatch $batch, $message = '')
    {
        $this->batch = $batch;
        $this->message = (string) $message;
        $this->onQueue('notificaciones');
    }

    public function broadcastQueue()
    {
        return 'notificaciones';
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('Documentacion-notifications'),
            new PrivateChannel('Coordinacion-notifications'),
            new PrivateChannel('JefeImportacion-notifications'),
        ];
    }

    public function broadcastAs()
    {
        return 'FacturaComercialBatchFinished';
    }

    public function broadcastWith()
    {
        $estado = strtoupper((string) $this->batch->estado);
        $hasFile = $estado === 'COMPLETED' && !empty($this->batch->file_path);

        return [
            'batch_id' => (int) $this->batch->id,
            'id_contenedor' => (int) $this->batch->id_contenedor,
            'estado' => $this->batch->estado,
            'message' => $this->message,
            'nombre_archivo' => $this->batch->nombre_archivo,
            'has_file' => $hasFile,
            'created_by' => $this->batch->created_by ? (int) $this->batch->created_by : null,
            'tipo_evento' => 'factura_comercial_batch_finished',
        ];
    }
}
