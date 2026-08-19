<?php

namespace App\Jobs;

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
        try {
            $payload = $service->buildPayload($this->idCotizacion);
            if ($payload === null) {
                Log::warning('SendReminderPagoWhatsAppJob: cotización no encontrada', [
                    'id_cotizacion' => $this->idCotizacion,
                ]);
                return;
            }
            if ($payload['phone'] === '') {
                Log::warning('SendReminderPagoWhatsAppJob: teléfono inválido o vacío', [
                    'cotizacion_id' => $this->idCotizacion,
                ]);
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
        } catch (\Throwable $e) {
            Log::error('Error en SendReminderPagoWhatsAppJob: ' . $e->getMessage(), [
                'cotizacion_id' => $this->idCotizacion,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
