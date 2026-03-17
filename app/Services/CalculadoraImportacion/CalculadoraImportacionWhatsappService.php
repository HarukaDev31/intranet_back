<?php

namespace App\Services\CalculadoraImportacion;

use App\Services\ResumenCostosImageService;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;

class CalculadoraImportacionWhatsappService
{
    use WhatsappTrait;

    public function sendCotizacionSequence(?string $whatsappCliente, $calculadora): void
    {
        try {
            if (!$whatsappCliente) {
                Log::warning('No se puede enviar WhatsApp: número no disponible', [
                    'calculadora_id' => $calculadora->id ?? null,
                    'cliente' => $calculadora->nombre_cliente ?? null,
                ]);
                return;
            }

            $phoneNumberId = $this->formatWhatsAppNumber($whatsappCliente);

            $primerMensaje = "Bien, Te envío la cotización de tu importación, en el documento podrás ver el detalle de los costos.\n\n⚠️ Nota: Leer Términos y Condiciones.\n\n🎥 Video Explicativo:\n▶️ https://youtu.be/H7U-_5wCWd4";
            $this->sendMessage($primerMensaje, $phoneNumberId, 2);

            if (!empty($calculadora->url_cotizacion_pdf)) {
                $pdfPath = $this->getPdfPathFromUrl($calculadora->url_cotizacion_pdf);
                if ($pdfPath && file_exists($pdfPath)) {
                    $this->sendMedia($pdfPath, 'application/pdf', null, $phoneNumberId, 3);
                } else {
                    Log::warning('No se pudo enviar PDF: archivo no encontrado', [
                        'calculadora_id' => $calculadora->id ?? null,
                        'url' => $calculadora->url_cotizacion_pdf,
                        'path' => $pdfPath,
                    ]);
                }
            }

            $tercerMensaje = "📊 Aquí te paso el resumen de cuánto te saldría cada modelo y el total de inversión\n\n💰 El primer pago es el SERVICIO DE IMPORTACIÓN y se realiza antes del zarpe de buque 🚢";
            $this->sendMessage($tercerMensaje, $phoneNumberId, 2);

            $resumenCostosService = new ResumenCostosImageService();
            $imagenResumen = $resumenCostosService->generateResumenCostosImage($calculadora);

            if ($imagenResumen) {
                $this->sendMedia($imagenResumen['path'], 'image/png', '📊 Resumen detallado de costos y pagos', $phoneNumberId, 4);
            } else {
                Log::warning('No se pudo generar la imagen del resumen de costos', [
                    'calculadora_id' => $calculadora->id ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar mensajes de WhatsApp: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function formatWhatsAppNumber(string $whatsapp): string
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $whatsapp);

        if (substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
        }

        if (substr($cleanNumber, 0, 2) !== '51') {
            $cleanNumber = '51' . $cleanNumber;
        }

        return $cleanNumber . '@c.us';
    }

    public function getPdfPathFromUrl(string $url): ?string
    {
        try {
            if (strpos($url, 'http') === 0) {
                $parsedUrl = parse_url($url);
                $path = $parsedUrl['path'] ?? '';

                if (strpos($path, '/storage/') === 0) {
                    $path = substr($path, 9);
                }

                return storage_path('app/public/' . $path);
            }

            if (strpos($url, '/storage/') === 0) {
                $path = substr($url, 9);
                return storage_path('app/public/' . $path);
            }

            return storage_path('app/public/boletas/' . $url);
        } catch (\Exception $e) {
            Log::error('Error al obtener ruta del PDF: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }
}

