<?php

namespace App\Support\WhatsApp;

/**
 * Cola Horizon para jobs y broadcasts del WhatsApp Copiloto (ventas).
 */
trait WaCopilotoQueue
{
    /**
     * @return string
     */
    public static function waCopilotoQueueName()
    {
        return (string) config('meta_whatsapp_copiloto.queue', 'notificaciones');
    }

    /**
     * @return string
     */
    public function broadcastQueue()
    {
        return static::waCopilotoQueueName();
    }
}
