<?php

namespace App\Support\WhatsApp;

/**
 * Cola Horizon para jobs y broadcasts del WhatsApp Inbox (coordinación).
 */
trait WaInboxQueue
{
    /**
     * @return string
     */
    public static function waInboxQueueName()
    {
        return (string) config('meta_whatsapp.inbox_queue', config('meta_whatsapp.queue', 'notificaciones'));
    }

    /**
     * Cola para ShouldBroadcast (Laravel encola el job de broadcast).
     *
     * @return string
     */
    public function broadcastQueue()
    {
        return static::waInboxQueueName();
    }
}
