<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReminderPagoWhatsAppFinished implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int */
    public $idCotizacion;

    /** @var string */
    public $cliente;

    /** @var string */
    public $carga;

    /** @var bool */
    public $success;

    /** @var string */
    public $message;

    /** @var string */
    public $queue = 'notificaciones';

    public function __construct($idCotizacion, $cliente, $carga, $success, $message)
    {
        $this->idCotizacion = (int) $idCotizacion;
        $this->cliente = (string) $cliente;
        $this->carga = (string) $carga;
        $this->success = (bool) $success;
        $this->message = (string) $message;
    }

    public function broadcastQueue()
    {
        return 'notificaciones';
    }

    public function broadcastOn()
    {
        return new PrivateChannel('Contabilidad-notifications');
    }

    public function broadcastAs()
    {
        return 'ReminderPagoWhatsAppFinished';
    }

    public function broadcastWith()
    {
        return [
            'id_cotizacion' => $this->idCotizacion,
            'cliente' => $this->cliente,
            'carga' => $this->carga,
            'success' => $this->success,
            'message' => $this->message,
            'tipo_evento' => 'reminder_pago_whatsapp_finished',
        ];
    }
}
