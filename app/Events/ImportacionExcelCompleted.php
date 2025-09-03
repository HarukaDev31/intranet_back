<?php

namespace App\Events;

use App\Models\ImportProducto;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ImportacionExcelCompleted implements ShouldBroadcast,ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $importProducto;
    public $status;
    public $message;
    public $estadisticas;
    public $queue = 'notificaciones';
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(ImportProducto $importProducto, string $status, string $message, array $estadisticas = [])
    {
        Log::info('ImportacionExcelCompleted', [
            'import_id' => $importProducto->id,
            'nombre_archivo' => $importProducto->nombre_archivo,
            'status' => $status,
            'message' => $message,
            'estadisticas' => $estadisticas
        ]);

        $this->importProducto = $importProducto;
        $this->status = $status;
        $this->message = $message;
        $this->estadisticas = $estadisticas;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // El prefijo 'private-' se agrega automáticamente por Laravel
        return new PrivateChannel('Documentacion-notifications');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->importProducto->id,
            'nombre_archivo' => $this->importProducto->nombre_archivo,
            'status' => $this->status,
            'message' => $this->message,
            'estadisticas' => $this->estadisticas,
            'cantidad_rows' => $this->importProducto->cantidad_rows,
            'created_at' => $this->importProducto->created_at->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'tipo_evento' => 'importacion_excel_completed'
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'ImportacionExcelCompleted';
    }
}
