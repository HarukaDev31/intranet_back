<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Contenedor;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

    public function handle(): void
    {
        try {
            $cotizacion = DB::table('contenedor_consolidado_cotizacion as CC')
                ->select([
                    'CC.telefono',
                    'CC.id_contenedor',
                    'CC.impuestos_final',
                    'CC.volumen_final',
                    'CC.monto_final',
                    'CC.tarifa_final',
                    'CC.nombre',
                    'CC.logistica_final',
                    'CC.recargos_descuentos_final',
                    'CC.servicios_extra_final',
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                        JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND (ccp.name = "LOGISTICA" OR ccp.name = "IMPUESTOS")
                    ) as total_pagos')
                ])
                ->where('CC.id', $this->idCotizacion)
                ->first();

            if (!$cotizacion) {
                Log::warning('SendReminderPagoWhatsAppJob: cotización no encontrada', [
                    'id_cotizacion' => $this->idCotizacion,
                ]);
                return;
            }

            $contenedor = Contenedor::select('carga', 'fecha_arribo')
                ->where('id', $cotizacion->id_contenedor)
                ->first();

            $carga = $contenedor ? $contenedor->carga : 'N/A';
            $fechaArribo = $contenedor ? $contenedor->fecha_arribo : null;
            $recargosDescuentosFinal = (float) ($cotizacion->recargos_descuentos_final ?? 0);
            $serviciosExtraFinal = (float) ($cotizacion->servicios_extra_final ?? 0);
            $logisticaFinal = (float) ($cotizacion->logistica_final ?? 0);
            $impuestosFinal = (float) ($cotizacion->impuestos_final ?? 0);

            $totalCotizacion = $logisticaFinal + $impuestosFinal + $serviciosExtraFinal + $recargosDescuentosFinal;
            $totalPagos = (float) ($cotizacion->total_pagos ?? 0);
            $pendiente = $totalCotizacion - $totalPagos;

            $message = "🙋🏽‍♀ RECORDATORÍO DE PAGO\n\n"
                . "📦 Consolidado #{$carga}\n"
                . "Usted cuenta con un pago pendiente, es necesario realizar el pago para continuar con el proceso de nacionalización.\n\n"
                . "Resumen de Pago\n"
                . "✅ Cotización final: $" . number_format($totalCotizacion, 2, '.', '') . "\n"
                . "✅ Adelanto: $" . number_format($totalPagos, 2, '.', '') . "\n"
                . "✅ *Pendiente de pago: $" . number_format($pendiente, 2, '.', '') . "*\n"
                . ($fechaArribo ? "Último día de pago: " . date('d/m/Y', strtotime($fechaArribo)) . "\n" : '')
                . "\nPor favor debe enviar el comprobante de pago a la brevedad.";

            $rawTelefono = (string) ($cotizacion->telefono ?? '');
            $telefonoDigits = preg_replace('/\D/', '', $rawTelefono);
            if (strlen($telefonoDigits) === 9) {
                $telefonoDigits = '51' . $telefonoDigits;
            } elseif (strlen($telefonoDigits) === 10 && substr($telefonoDigits, 0, 1) === '0') {
                $telefonoDigits = '51' . substr($telefonoDigits, 1);
            }

            if (empty($telefonoDigits)) {
                Log::warning('SendReminderPagoWhatsAppJob: teléfono inválido o vacío', [
                    'cotizacion_id' => $this->idCotizacion,
                    'telefono_raw' => $rawTelefono,
                ]);
                return;
            }

            $this->phoneNumberId = $telefonoDigits . '@c.us';

            Log::info('SendReminderPagoWhatsAppJob enviando', [
                'cotizacion_id' => $this->idCotizacion,
                'telefono_raw' => $rawTelefono,
                'telefono_normalized' => $telefonoDigits,
                'phoneNumberId' => $this->phoneNumberId,
            ]);

            $result = $this->sendMessage($message, $this->phoneNumberId, $this->sleep, 'administracion');
            $pagosUrl = public_path('assets/images/pagos-full.jpg');
            $this->sendMedia($pagosUrl, 'image/jpg', null, null, 0, 'administracion');

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

