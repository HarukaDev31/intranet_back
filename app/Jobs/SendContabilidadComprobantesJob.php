<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Comprobante;
use App\Contracts\ObjectStorageConnectorInterface;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía los comprobantes (PDFs) de una cotización al cliente por WhatsApp
 * usando la instancia de administración.
 */
class SendContabilidadComprobantesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    public int $idCotizacion;

    public function __construct(int $idCotizacion)
    {
        $this->idCotizacion = $idCotizacion;
        $this->onQueue('notificaciones');
    }

    public function handle(): void
    {
        try {
            $cotizacion = Cotizacion::find($this->idCotizacion);
            if (!$cotizacion) {
                Log::error('SendContabilidadComprobantesJob: cotización no encontrada', ['id' => $this->idCotizacion]);
                return;
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono ?? '');
            if (empty($telefono)) {
                Log::error('SendContabilidadComprobantesJob: sin teléfono', ['id' => $this->idCotizacion]);
                return;
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';

            $message = "Buen día somos del area de contabilidad de Pro Business.\n" .
                "Le adjunto la factura de tu consolidado # {$carga}🙋🏻‍♀,\n\n" .
                "✅ Verificar que el monto de crédito fiscal sea el correcto.\n" .
                "✅ Recordar, solo recuperan como crédito fiscal el 18% (IGV + IPM) que esta contemplado en su cotización final.\n" .
                "✅ El plazo máximo para notificar una observación de su comprobante es de 24 h. Después de este periodo, no será posible realizar modificaciones de ningún tipo.";

            $comprobantes = Comprobante::where('quotation_id', $this->idCotizacion)->get();
            if ($comprobantes->isEmpty()) {
                Log::warning('SendContabilidadComprobantesJob: sin comprobantes', ['id' => $this->idCotizacion]);
                return;
            }

            $storage = app(ObjectStorageConnectorInterface::class);

            foreach ($comprobantes as $comp) {
                if (!$storage->exists($comp->file_path)) {
                    Log::warning('SendContabilidadComprobantesJob: archivo no encontrado', ['path' => $comp->file_path]);
                    continue;
                }
                $filePath = $storage->localPath($comp->file_path);
                $mimeType = $storage->mimeType($comp->file_path) ?: 'application/pdf';
                $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $comp->file_name);
                $message = ''; // Solo el primer archivo lleva el caption
            }

            Log::info('SendContabilidadComprobantesJob: completado', ['id_cotizacion' => $this->idCotizacion]);
        } catch (\Exception $e) {
            Log::error('SendContabilidadComprobantesJob: error', [
                'id_cotizacion' => $this->idCotizacion,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
