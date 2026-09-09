<?php

namespace App\Jobs;

use App\Events\ReminderPagoWhatsAppFinished;
use App\Services\CargaConsolidada\CotizacionFinal\ReminderPagoWhatsappService;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderPagoWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    private int $idCotizacion;
    private int $sleep;

    public function __construct(int $idCotizacion, int $sleep = 0)
    {
        $this->idCotizacion = $idCotizacion;
        $this->sleep = max(0, $sleep);
        $this->onQueue('importaciones');
    }

    public function handle(ReminderPagoWhatsappService $service): void
    {
        $cliente = '';
        $carga = '';

        try {
            $payload = $service->buildPayload($this->idCotizacion);
            if ($payload === null) {
                Log::warning('SendReminderPagoWhatsAppJob: cotización no encontrada', [
                    'id_cotizacion' => $this->idCotizacion,
                ]);
                $this->notifyContabilidad(false, 'Cotización no encontrada', $cliente, $carga);
                return;
            }

            $cliente = (string) ($payload['nombre'] ?? '');
            $carga = (string) ($payload['carga'] ?? '');

            if ($payload['phone'] === '') {
                Log::warning('SendReminderPagoWhatsAppJob: teléfono inválido o vacío', [
                    'cotizacion_id' => $this->idCotizacion,
                ]);
                $this->notifyContabilidad(false, 'El cliente no tiene un teléfono válido', $cliente, $carga);
                return;
            }

            $this->phoneNumberId = $payload['phone_id'];

            Log::info('SendReminderPagoWhatsAppJob enviando', [
                'cotizacion_id' => $this->idCotizacion,
                'telefono_normalized' => $payload['phone'],
                'phoneNumberId' => $this->phoneNumberId,
            ]);

            $result = $this->sendMessage($payload['message'], $this->phoneNumberId, $this->sleep, 'administracion');
            $pagosUrl = public_path('assets/images/pagos-full.jpg');
            $this->sendMedia($pagosUrl, 'image/jpg', null, null, 10, 'administracion');

            Log::info('SendReminderPagoWhatsAppJob resultado', [
                'cotizacion_id' => $this->idCotizacion,
                'result' => $result,
            ]);

            $this->notifyContabilidad(
                true,
                $cliente !== '' ? "Se envió el recordatorio de pago a {$cliente}." : 'Se envió el recordatorio de pago al cliente.',
                $cliente,
                $carga
            );
        } catch (\Throwable $e) {
            Log::error('Error en SendReminderPagoWhatsAppJob: ' . $e->getMessage(), [
                'cotizacion_id' => $this->idCotizacion,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->notifyContabilidad(false, 'No se pudo enviar el recordatorio de pago.', $cliente, $carga);
        }
    }

    private function notifyContabilidad(bool $success, string $message, string $cliente, string $carga): void
    {
        try {
            event(new ReminderPagoWhatsAppFinished(
                $this->idCotizacion,
                $cliente,
                $carga,
                $success,
                $message
            ));
        } catch (\Throwable $e) {
            Log::error('SendReminderPagoWhatsAppJob: no se pudo emitir WebSocket a contabilidad: ' . $e->getMessage(), [
                'cotizacion_id' => $this->idCotizacion,
            ]);
        }
    }
}
