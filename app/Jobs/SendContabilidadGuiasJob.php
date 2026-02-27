<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\GuiaRemision;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía las guías de remisión de una cotización al cliente por WhatsApp
 * usando la instancia de administración.
 */
class SendContabilidadGuiasJob implements ShouldQueue
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
                Log::error('SendContabilidadGuiasJob: cotización no encontrada', ['id' => $this->idCotizacion]);
                return;
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono ?? '');
            if (empty($telefono)) {
                Log::error('SendContabilidadGuiasJob: sin teléfono', ['id' => $this->idCotizacion]);
                return;
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';

            $message = "Buen día somos del area de contabilidad de Pro Business.\n" .
                "Se adjunta la guía de remision correspondiente al consolidado # {$carga}.";

            $guias = GuiaRemision::where('quotation_id', $this->idCotizacion)->orderBy('created_at', 'asc')->get();
            if ($guias->isEmpty()) {
                Log::warning('SendContabilidadGuiasJob: sin guías', ['id' => $this->idCotizacion]);
                return;
            }

            foreach ($guias as $guia) {
                $filePath = storage_path('app/' . $guia->file_path);
                if (!file_exists($filePath)) {
                    Log::warning('SendContabilidadGuiasJob: archivo no encontrado', ['path' => $filePath]);
                    continue;
                }
                $mimeType = mime_content_type($filePath) ?: 'application/pdf';
                $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $guia->file_name);
                $message = '';
            }

            Log::info('SendContabilidadGuiasJob: completado', ['id_cotizacion' => $this->idCotizacion]);
        } catch (\Exception $e) {
            Log::error('SendContabilidadGuiasJob: error', [
                'id_cotizacion' => $this->idCotizacion,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
