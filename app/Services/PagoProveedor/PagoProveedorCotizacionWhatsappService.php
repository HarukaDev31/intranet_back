<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;

/**
 * Vista previa y envío del resumen de cotización por el número de ventas.
 */
class PagoProveedorCotizacionWhatsappService
{
    use WhatsappTrait;

    public function __construct(
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionPresenter $presenter,
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorMensajeService $mensajes,
        private readonly PagoProveedorHistorialService $historial,
    ) {
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{texto: string, destinatario: string|null, puede_enviar: bool, nota: string}
     */
    public function vistaPrevia(int $id, array $actor): array
    {
        unset($actor);

        return $this->armarVistaPrevia($this->cotizaciones->encontrar($id));
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{texto: string, destinatario: string|null, puede_enviar: bool, nota: string}
     */
    public function vistaPreviaBorrador(array $datos): array
    {
        return $this->armarVistaPrevia($this->cotizaciones->borrador($datos));
    }

    /**
     * @return array{texto: string, destinatario: string|null, puede_enviar: bool, nota: string}
     */
    private function armarVistaPrevia(PagoProveedorCotizacion $cotizacion): array
    {
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $forRequest = $this->belongsToRequest($cotizacion);
        if (!$forRequest) {
            $this->exigirListoParaEnviar($presentado, false);
        }

        $texto = $this->armarTexto($cotizacion, $presentado, $forRequest);
        $destinatario = trim((string) $cotizacion->client_whatsapp);
        $puedeEnviar = !$forRequest && $destinatario !== '';

        return [
            'texto' => $texto,
            'destinatario' => $destinatario !== '' ? $destinatario : null,
            'puede_enviar' => $puedeEnviar,
            'nota' => match (true) {
                $forRequest => 'Resumen actualizado de la solicitud. Revisa el mensaje; va con el PDF de resumen.',
                $puedeEnviar => 'Revisa el mensaje y pulsa Enviar. Se enviará al WhatsApp del cliente junto con el PDF de resumen.',
                default => 'Falta el WhatsApp del cliente para poder enviar.',
            },
        ];
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{texto: string, destinatario: string, enviado: bool, nota: string}
     */
    public function enviar(int $id, array $actor): array
    {
        /** @var PagoProveedorPdfService $pdfService */
        $pdfService = app(PagoProveedorPdfService::class);

        return $this->enviarCotizacion(
            $this->cotizaciones->encontrar($id),
            $actor,
            fn () => $pdfService->resumenTemporal($id, $actor)
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{texto: string, destinatario: string, enviado: bool, nota: string}
     */
    public function enviarBorrador(array $datos, array $actor): array
    {
        /** @var PagoProveedorPdfService $pdfService */
        $pdfService = app(PagoProveedorPdfService::class);
        $cotizacion = $this->cotizaciones->borrador($datos);

        return $this->enviarCotizacion($cotizacion, $actor, fn () => $pdfService->temporalDeBorrador($cotizacion));
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @param  callable(): array{path: string, filename: string}  $generarPdf
     * @return array{texto: string, destinatario: string, enviado: bool, nota: string}
     */
    private function enviarCotizacion(PagoProveedorCotizacion $cotizacion, array $actor, callable $generarPdf): array
    {
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $this->exigirListoParaEnviar($presentado, true);

        $texto = $this->armarTexto($cotizacion, $presentado);
        $telefono = $this->telefonoCliente((string) $cotizacion->client_whatsapp);
        $archivo = $generarPdf();

        try {
            $respuestaTexto = $this->sendMessageVentas($texto, $telefono, 0);
            if (empty($respuestaTexto['status'])) {
                Log::warning('Pago proveedor WA texto sin status ok', [
                    'quote_id' => $cotizacion->id,
                    'response' => $respuestaTexto,
                ]);
                if (!empty($respuestaTexto['skipped'])) {
                    throw new PagoProveedorException('El envío de WhatsApp está deshabilitado para esta organización.');
                }
                throw new PagoProveedorException('No se pudo enviar el mensaje de WhatsApp.');
            }

            $respuestaMedia = $this->sendMedia(
                $archivo['path'],
                'application/pdf',
                null,
                $telefono,
                2,
                'ventas',
                $archivo['filename']
            );
            if ($respuestaMedia === false || (is_array($respuestaMedia) && isset($respuestaMedia['status']) && !$respuestaMedia['status'])) {
                Log::warning('Pago proveedor WA PDF falló tras el texto', [
                    'quote_id' => $cotizacion->id,
                    'response' => $respuestaMedia,
                ]);
                throw new PagoProveedorException('El mensaje se envió, pero falló el PDF. Revisa e intenta de nuevo.');
            }
        } finally {
            if (is_file($archivo['path'])) {
                @unlink($archivo['path']);
            }
        }

        if ($cotizacion->id) {
            PagoProveedorMensaje::query()->create([
                'quote_id' => $cotizacion->id,
                'message_type' => 'resumen',
                'recipient' => $cotizacion->client_whatsapp,
                'body' => $texto,
                'user_id' => $actor['id'],
                'created_at' => now(),
            ]);
            $this->historial->registrar(
                PagoProveedorHistorial::ENTITY_QUOTE,
                $cotizacion->id,
                'Resumen enviado por WhatsApp',
                $actor,
                'Enviado desde el número de ventas a ' . $cotizacion->client_whatsapp
            );
        } else {
            Log::info('Pago proveedor: resumen enviado sin cotización guardada', [
                'cod_cotizacion_cc' => $cotizacion->consolidated_quote_code,
                'user_id' => $actor['id'],
            ]);
        }

        return [
            'texto' => $texto,
            'destinatario' => (string) $cotizacion->client_whatsapp,
            'enviado' => true,
            'nota' => 'Resumen y PDF enviados por WhatsApp desde el número de ventas.',
        ];
    }

    /**
     * @param  array<string, mixed>  $presentado
     */
    private function exigirListoParaEnviar(array $presentado, bool $exigeWhatsapp): void
    {
        if (!empty($presentado['vencida'])) {
            throw new PagoProveedorException('La cotización está vencida. Renuévala para enviar el resumen.');
        }
        if (($presentado['estado'] ?? '') !== 'pendiente') {
            throw new PagoProveedorException('Solo se envía el resumen de una cotización pendiente.');
        }
        $faltantes = $presentado['faltantes_pdf'] ?? [];
        if (is_array($faltantes) && count($faltantes) > 0) {
            throw new PagoProveedorException(implode(' ', $faltantes));
        }
        if ($exigeWhatsapp && trim((string) ($presentado['cliente_whatsapp'] ?? '')) === '') {
            throw new PagoProveedorException('Falta el WhatsApp del cliente.');
        }
    }

    /**
     * @param  array<string, mixed>  $presentado
     */
    private function armarTexto($cotizacion, array $presentado, bool $forRequest = false): string
    {
        $detalle = $cotizacion->proveedores->map(function ($proveedor) {
            return [
                'code_supplier' => $proveedor->code_supplier,
                'nombre_proveedor' => $proveedor->supplier_name,
                'producto' => $proveedor->product,
            ];
        })->all();

        return $this->mensajes->resumen(
            (string) $cotizacion->client_name,
            $presentado['resumen'],
            $detalle,
            $forRequest ? null : $cotizacion->valid_until?->timezone(PagoProveedorVigenciaService::ZONA)->format('d/m/Y'),
            $forRequest
        );
    }

    private function belongsToRequest(PagoProveedorCotizacion $cotizacion): bool
    {
        return $cotizacion->status === PagoProveedorCotizacion::STATUS_CONFIRMED && $cotizacion->solicitud()->exists();
    }

    private function telefonoCliente(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            throw new PagoProveedorException('Falta el WhatsApp del cliente.');
        }
        if (strlen($digits) <= 9) {
            $digits = '51' . $digits;
        }

        return $digits . '@c.us';
    }
}
