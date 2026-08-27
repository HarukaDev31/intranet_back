<?php

namespace App\Jobs\SoporteTi;

use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Notifica al grupo de WhatsApp Soporte TI (instancia "soporte").
 */
class SendSoporteTiWhatsappGrupoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use WhatsappTrait;

    /** @var string */
    public $message;

    /**
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = (string) $message;
        $this->onQueue((string) config('soporte-ti.whatsapp_queue', 'notificaciones'));
    }

    /**
     * @return void
     */
    public function handle()
    {
        if (!(bool) config('soporte-ti.whatsapp_enabled', true)) {
            return;
        }

        $groupId = trim((string) config('soporte-ti.whatsapp_group_id', ''));
        $fromNumber = trim((string) config('soporte-ti.whatsapp_from_number', 'soporte'));
        if ($groupId === '' || $fromNumber === '' || trim($this->message) === '') {
            Log::warning('SendSoporteTiWhatsappGrupoJob: configuración incompleta o mensaje vacío', array(
                'groupId' => $groupId,
                'fromNumber' => $fromNumber,
            ));

            return;
        }

        try {
            $result = $this->sendMessage($this->message, $groupId, 0, $fromNumber);
            if (empty($result['status'])) {
                Log::warning('SendSoporteTiWhatsappGrupoJob: envío sin status ok', array(
                    'result' => $result,
                ));
            }
        } catch (\Exception $e) {
            Log::error('SendSoporteTiWhatsappGrupoJob: ' . $e->getMessage(), array(
                'trace' => $e->getTraceAsString(),
            ));
            throw $e;
        }
    }
}
