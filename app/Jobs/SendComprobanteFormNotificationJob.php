<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\ComprobanteForm;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Mail\ComprobanteFormConfirmationMail;
use App\Traits\MailTrait;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
/**
 * 1. Notifica al área de administración (número por defecto) cuando el cliente envía el formulario.
 * 2. Envía confirmación al cliente por WhatsApp (desde la instancia 'administracion') y por email.
 */
class SendComprobanteFormNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MailTrait, WhatsappTrait;

    public int $comprobanteFormId;

    public function __construct(int $comprobanteFormId)
    {
        $this->comprobanteFormId = $comprobanteFormId;
        $this->onQueue('notificaciones');
    }

    public function handle(): void
    {
        try {
            $form = ComprobanteForm::find($this->comprobanteFormId);
            if (!$form) {
                Log::error('SendComprobanteFormNotificationJob: formulario no encontrado', ['id' => $this->comprobanteFormId]);
                return;
            }

            $cotizacion = Cotizacion::find($form->id_cotizacion);
            if (!$cotizacion) {
                Log::error('SendComprobanteFormNotificationJob: cotización no encontrada', ['id_cotizacion' => $form->id_cotizacion]);
                return;
            }

            $contenedor = Contenedor::find($form->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            $anioCarga = $contenedor && !empty($contenedor->f_inicio)
                ? date('Y', strtotime((string) $contenedor->f_inicio))
                : date('Y');
            $cargaTexto = is_numeric($carga) ? str_pad((string) $carga, 2, '0', STR_PAD_LEFT) : (string) $carga;
            $consolidadoLabel = '#' . $cargaTexto . '-' . $anioCarga;

            $clienteNombre   = $cotizacion->nombre ?? 'Cliente';
            $tipoComprobante = $form->tipo_comprobante;

            // ── Datos según tipo de comprobante ──────────────────────────────
            if ($tipoComprobante === 'FACTURA') {
                $dom = $form->domicilio_fiscal ? "\n-Domicilio fiscal: {$form->domicilio_fiscal}" : '';
                $datosLineas = "-Razón social: {$form->razon_social}\n-Ruc: {$form->ruc}{$dom}";
            } else {
                $datosLineas = "-Nombre: {$form->nombre_completo}\n-DNI/Carnet: {$form->dni_carnet}";
            }

            // ── 1. WhatsApp al área de administración (número por defecto) ───
            $msgAdmin = "📋 *Nuevo formulario de comprobante recibido*\n\n" .
                "Consolidado #{$carga}\n" .
                "Cliente: {$clienteNombre}\n\n" .
                "Tipo de comprobante: {$tipoComprobante}\n" .
                $datosLineas .
                ($form->destino_entrega ? "\nDestino de entrega: {$form->destino_entrega}" : '');

            // phone = null → usa DEFAULT_WHATSAPP_NUMBER del .env
            //$this->sendMessage($msgAdmin, null, 0, 'administracion');

            // ── 2. WhatsApp de confirmación al cliente ───────────────────────
            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono ?? '');
            if (!empty($telefono)) {
                if (strlen($telefono) < 9) {
                    $telefono = '51' . $telefono;
                }
                $numeroCliente = $telefono . '@c.us';

                if ($tipoComprobante === 'FACTURA') {
                    $msgCliente = "Hola 👋\n" .
                        "*Consolidado {$consolidadoLabel}*\n" .
                   
                        "Tu formulario fue completado correctamente ✅\n\n" .
                        "Datos de Facturación:\n" .
                        "• *Tipo de comprobante: Factura* \n" .
                        "• RUC: " . ($form->ruc ?? '-') . "\n" .
                        "• Razón social: " . ($form->razon_social ?? '-') . "\n\n" .
                        "📦 Con esta información se emitirá tu comprobante una vez realizada la entrega de tu carga.\n" .
                        "📌 Si necesitas corregir algún dato, responde este mensaje. 😊";
                } else {
                    $msgCliente = "Hola 👋\n" .
                        "*Consolidado {$consolidadoLabel}*\n" .
                        "Tu formulario fue completado correctamente ✅\n\n" .
                        "Datos de Facturación:\n" .
                        "• *Tipo de comprobante: Boleta* \n" .
                        "• DNI: " . ($form->dni_carnet ?? '-') . "\n" .
                        "• Nombre completo: " . ($form->nombre_completo ?? '-') . "\n\n" .
                        "📦 Con esta información se emitirá tu comprobante una vez realizada la entrega de tu carga.\n" .
                   
                        "📌 Si necesitas corregir algún dato, responde este mensaje. 😊";
                }

                $this->sendMessage($msgCliente, $numeroCliente, 0, 'administracion');
            }

            // ── 3. Email de confirmación al cliente ──────────────────────────
            $this->sendMailTo(
                $cotizacion->correo ?? null,
                new ComprobanteFormConfirmationMail($form, $cotizacion, $carga)
            );

            Log::info('SendComprobanteFormNotificationJob: completado', [
                'comprobante_form_id' => $this->comprobanteFormId,
                'id_cotizacion'       => $form->id_cotizacion,
            ]);
        } catch (\Exception $e) {
            Log::error('SendComprobanteFormNotificationJob: error', [
                'comprobante_form_id' => $this->comprobanteFormId,
                'error'               => $e->getMessage(),
                'trace'               => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
