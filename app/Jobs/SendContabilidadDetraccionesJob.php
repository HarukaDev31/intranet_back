<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Comprobante;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía las constancias de detracción de una cotización al cliente por WhatsApp
 * usando la instancia de administración.
 */
class SendContabilidadDetraccionesJob implements ShouldQueue
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
                Log::error('SendContabilidadDetraccionesJob: cotización no encontrada', ['id' => $this->idCotizacion]);
                return;
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono ?? '');
            if (empty($telefono)) {
                Log::error('SendContabilidadDetraccionesJob: sin teléfono', ['id' => $this->idCotizacion]);
                return;
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';

            $message = "Buen día somos del area de contabilidad de Pro Business.\n" .
                "Se adjunta la constancia de detracción correspondiente al consolidado # {$carga}.";

            $comprobantes = Comprobante::where('quotation_id', $this->idCotizacion)
                ->where('tiene_detraccion', true)
                ->with('constancia')
                ->get();

            if ($comprobantes->isEmpty()) {
                Log::warning('SendContabilidadDetraccionesJob: sin comprobantes con detracción', ['id' => $this->idCotizacion]);
                return;
            }

            $enviados = 0;
            foreach ($comprobantes as $comp) {
                if (!$comp->constancia || empty($comp->constancia->file_path)) {
                    continue;
                }
                $filePath = storage_path('app/' . $comp->constancia->file_path);
                if (!file_exists($filePath)) {
                    Log::warning('SendContabilidadDetraccionesJob: constancia no encontrada', ['path' => $filePath]);
                    continue;
                }
                $mimeType = mime_content_type($filePath) ?: 'application/pdf';
                $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $comp->constancia->file_name);
                $message = '';
                $enviados++;
            }

            if ($enviados === 0) {
                Log::warning('SendContabilidadDetraccionesJob: ninguna constancia con archivo adjunto', ['id' => $this->idCotizacion]);
            } else {
                Log::info('SendContabilidadDetraccionesJob: completado', [
                    'id_cotizacion' => $this->idCotizacion,
                    'enviados'      => $enviados,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SendContabilidadDetraccionesJob: error', [
                'id_cotizacion' => $this->idCotizacion,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
