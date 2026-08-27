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

    /** @var string */
    public $evento;

    /** @var int|null */
    public $solicitudId;

    /** @var string|null */
    public $codigo;

    /**
     * @param string $message
     * @param string $evento
     * @param int|null $solicitudId
     * @param string|null $codigo
     */
    public function __construct($message, $evento = 'creado', $solicitudId = null, $codigo = null)
    {
        $this->message = (string) $message;
        $this->evento = (string) $evento;
        $this->solicitudId = $solicitudId !== null ? (int) $solicitudId : null;
        $this->codigo = $codigo !== null ? (string) $codigo : null;
        $this->onQueue((string) config('soporte-ti.whatsapp_queue', 'notificaciones'));
    }

    /**
     * @return void
     */
    public function handle()
    {
        $appEnv = app()->environment();
        $enabled = (bool) config('soporte-ti.whatsapp_enabled', true);
        $groupId = trim((string) config('soporte-ti.whatsapp_group_id', ''));
        $fromNumber = trim((string) config('soporte-ti.whatsapp_from_number', 'soporte'));
        $queueName = (string) config('soporte-ti.whatsapp_queue', 'notificaciones');

        $context = array(
            'app_env' => $appEnv,
            'evento' => $this->evento,
            'solicitud_id' => $this->solicitudId,
            'codigo' => $this->codigo,
            'enabled' => $enabled,
            'groupId' => $groupId,
            'fromNumber' => $fromNumber,
            'queue' => $queueName,
        );

        if (!$enabled) {
            Log::info('SendSoporteTiWhatsappGrupoJob: omitido (whatsapp deshabilitado)', $context);

            return;
        }

        if ($groupId === '' || $fromNumber === '' || trim($this->message) === '') {
            Log::warning('SendSoporteTiWhatsappGrupoJob: configuración incompleta o mensaje vacío', $context);

            return;
        }

        $groupIdResolved = $this->resolvePhoneNumberForWhatsApp($groupId);
        Log::info('SendSoporteTiWhatsappGrupoJob: inicio envío', array_merge($context, array(
            'groupId_resolved' => $groupIdResolved,
            'mensaje_preview' => mb_substr(trim($this->message), 0, 120),
        )));

        try {
            $result = $this->sendMessage($this->message, $groupId, 0, $fromNumber);
            $ok = !empty($result['status']);

            if ($ok) {
                Log::info('SendSoporteTiWhatsappGrupoJob: envío ok', array_merge($context, array(
                    'groupId_resolved' => $groupIdResolved,
                    'http_ok' => true,
                    'api_response' => isset($result['response']) ? $result['response'] : null,
                )));
            } else {
                Log::warning('SendSoporteTiWhatsappGrupoJob: envío sin status ok', array_merge($context, array(
                    'groupId_resolved' => $groupIdResolved,
                    'result' => $result,
                )));
            }
        } catch (\Exception $e) {
            Log::error('SendSoporteTiWhatsappGrupoJob: excepción', array_merge($context, array(
                'groupId_resolved' => $groupIdResolved,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            )));
            throw $e;
        }
    }
}
