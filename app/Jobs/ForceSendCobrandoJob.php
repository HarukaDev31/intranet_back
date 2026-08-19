<?php

namespace App\Jobs;

use App\Events\ReminderInicialWhatsAppFinished;
use App\Services\CargaConsolidada\ReminderInicialWhatsappService;
use App\Traits\DatabaseConnectionTrait;
use App\Traits\WhatsappTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ForceSendCobrandoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, DatabaseConnectionTrait;

    protected $idCotizacion;
    protected $idContainer;
    protected $domain;

    /**
     * Create a new job instance.
     */
    public function __construct($idCotizacion, $idContainer, $domain = null)
    {
        $this->idCotizacion = $idCotizacion;
        $this->idContainer = $idContainer;
        $this->domain = $domain;
        $this->onQueue('importaciones');
    }

    /**
     * Execute the job.
     */
    public function handle(ReminderInicialWhatsappService $service): void
    {
        $cliente = '';
        $carga = '';

        try {
            $this->setDatabaseConnection($this->domain);

            Log::info("Iniciando Job ForceSendCobrando", [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'domain' => $this->domain
            ]);

            $payload = $service->buildPayload((int) $this->idCotizacion, $this->idContainer);
            if ($payload === null) {
                Log::warning('ForceSendCobrandoJob: cotización o contenedor no encontrado', [
                    'id_cotizacion' => $this->idCotizacion,
                    'id_container' => $this->idContainer,
                ]);
                $this->notifyContabilidad(false, 'Cotización no encontrada', $cliente, $carga);
                return;
            }

            $cliente = (string) ($payload['nombre'] ?? '');
            $carga = (string) ($payload['carga'] ?? '');

            if ($payload['phone'] === '') {
                Log::warning('ForceSendCobrandoJob: teléfono inválido o vacío', [
                    'cotizacion_id' => $this->idCotizacion,
                ]);
                $this->notifyContabilidad(false, 'El cliente no tiene un teléfono válido', $cliente, $carga);
                return;
            }

            $this->phoneNumberId = $payload['phone_id'];

            Log::info('ForceSendCobrandoJob enviando a WhatsApp redis', [
                'id_cotizacion' => $this->idCotizacion,
                'phoneNumberId' => $this->phoneNumberId,
                'fromNumber' => 'administracion',
                'api' => 'https://redis.probusiness.pe/api/whatsapp/messageV2',
            ]);

            $msgResult = $this->sendMessage($payload['message'], $this->phoneNumberId, 0, 'administracion');
            if (!is_array($msgResult) || empty($msgResult['status'])) {
                Log::error('ForceSendCobrandoJob: falló envío de texto (call API)', [
                    'id_cotizacion' => $this->idCotizacion,
                    'phoneNumberId' => $this->phoneNumberId,
                    'result' => $msgResult,
                ]);
                throw new Exception('Falló call API /messageV2: ' . json_encode($msgResult['response'] ?? $msgResult));
            }

            $pagosUrl = public_path('assets/images/pagos-full.jpg');
            $mediaResult = $this->sendMedia($pagosUrl, 'image/jpg', null, $this->phoneNumberId, 10, 'administracion');
            if ($mediaResult === false || !is_array($mediaResult) || empty($mediaResult['status'])) {
                Log::error('ForceSendCobrandoJob: falló envío de imagen pagos (call API)', [
                    'id_cotizacion' => $this->idCotizacion,
                    'phoneNumberId' => $this->phoneNumberId,
                    'pagosUrl' => $pagosUrl,
                    'exists' => is_file($pagosUrl),
                    'result' => $mediaResult,
                ]);
                throw new Exception('Falló call API /mediaV2: ' . json_encode(is_array($mediaResult) ? ($mediaResult['response'] ?? $mediaResult) : $mediaResult));
            }

            Log::info("Mensaje de cobranza enviado exitosamente via Job", [
                'id_cotizacion' => $this->idCotizacion,
                'cliente' => $cliente,
                'telefono' => $payload['phone'],
                'phoneNumberId' => $this->phoneNumberId,
                'message_api' => $msgResult,
                'media_api' => [
                    'status' => $mediaResult['status'] ?? null,
                    'http_hint' => $mediaResult['response'] ?? null,
                ],
            ]);

            $this->notifyContabilidad(
                true,
                $cliente !== '' ? "Se envió el recordatorio de inicial a {$cliente}." : 'Se envió el recordatorio de inicial al cliente.',
                $cliente,
                $carga
            );
        } catch (\Throwable $e) {
            Log::error('Error en ForceSendCobrandoJob: ' . $e->getMessage(), [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'trace' => $e->getTraceAsString()
            ]);
            $this->notifyContabilidad(false, 'No se pudo enviar el recordatorio de inicial.', $cliente, $carga);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('ForceSendCobrandoJob falló', [
            'id_cotizacion' => $this->idCotizacion,
            'id_container' => $this->idContainer,
            'error' => $exception->getMessage()
        ]);
        $this->notifyContabilidad(false, 'No se pudo enviar el recordatorio de inicial.', '', '');
    }

    private function notifyContabilidad($success, $message, $cliente, $carga): void
    {
        try {
            event(new ReminderInicialWhatsAppFinished(
                $this->idCotizacion,
                $cliente,
                $carga,
                $success,
                $message
            ));
        } catch (\Throwable $e) {
            Log::error('ForceSendCobrandoJob: no se pudo emitir WebSocket a contabilidad: ' . $e->getMessage(), [
                'cotizacion_id' => $this->idCotizacion,
            ]);
        }
    }
}
