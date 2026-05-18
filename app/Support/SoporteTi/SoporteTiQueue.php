<?php

namespace App\Support\SoporteTi;

/**
 * Nombre de cola Horizon para jobs y eventos broadcast de Soporte TI.
 */
trait SoporteTiQueue
{
    /**
     * @return string
     */
    public static function soporteTiQueueName()
    {
        return (string) config('soporte-ti.queue', 'soporte_ti');
    }

    /**
     * Cola para ShouldBroadcast (Laravel encola el broadcast).
     *
     * @return string
     */
    public function broadcastQueue()
    {
        return static::soporteTiQueueName();
    }

    protected function assignSoporteTiQueue()
    {
        $this->onQueue(static::soporteTiQueueName());
    }
}
