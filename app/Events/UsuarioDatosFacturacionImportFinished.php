<?php

namespace App\Events;

use App\Models\ImportUsuarioDatosFacturacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsuarioDatosFacturacionImportFinished implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var ImportUsuarioDatosFacturacion */
    public $import;

    /** @var string */
    public $status;

    /** @var string */
    public $message;

    /** @var array */
    public $estadisticas;

    /** @var string */
    public $queue = 'notificaciones';

    public function __construct(ImportUsuarioDatosFacturacion $import, $status, $message, array $estadisticas = [])
    {
        $this->import = $import;
        $this->status = (string) $status;
        $this->message = (string) $message;
        $this->estadisticas = $estadisticas;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('Contabilidad-notifications');
    }

    public function broadcastAs()
    {
        return 'UsuarioDatosFacturacionImportFinished';
    }

    public function broadcastWith()
    {
        return [
            'id_import' => (int) $this->import->id,
            'nombre_archivo' => $this->import->nombre_archivo,
            'status' => $this->status,
            'message' => $this->message,
            'estadisticas' => $this->estadisticas,
            'cantidad_rows' => (int) $this->import->cantidad_rows,
            'updated_at' => now()->toIso8601String(),
            'tipo_evento' => 'usuario_datos_facturacion_import_finished',
        ];
    }
}
