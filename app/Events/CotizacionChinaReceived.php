<?php

namespace App\Events;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CotizacionChinaReceived implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cotizacion;
    public $proveedor;
    public $supplierCode;
    public $qtyBox;
    public $cbmTotal;
    public $message;
    public $queue = 'notificaciones';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Cotizacion $cotizacion, CotizacionProveedor $proveedor, string $supplierCode, float $qtyBox, float $cbmTotal, string $message)
    {
        Log::info('CotizacionChinaReceived', [
            'cotizacion_id' => $cotizacion->id,
            'proveedor_id' => $proveedor->id,
            'supplier_code' => $supplierCode,
            'qty_box' => $qtyBox,
            'cbm_total' => $cbmTotal,
            'message' => $message
        ]);

        $this->cotizacion = $cotizacion;
        $this->proveedor = $proveedor;
        $this->supplierCode = $supplierCode;
        $this->qtyBox = $qtyBox;
        $this->cbmTotal = $cbmTotal;
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
            'proveedor_id' => $this->proveedor->id,
            'supplier_code' => $this->supplierCode,
            'qty_box' => $this->qtyBox,
            'cbm_total' => $this->cbmTotal,
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
        return 'CotizacionChinaReceived';
    }
}

