<?php

namespace App\Services\CargaConsolidada\CotizacionFinal;

use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\WhatsappInbox\WaInboxMessage;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\WhatsappTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Envío / reintento de plantillas Meta al pasar cotización final a COBRANDO.
 */
class CotizacionFinalCobranzaWhatsappService
{
    use WhatsappTrait;

    public const TEMPLATE_COTIZACION_FINAL = 'pb_consolidado_cotizacion_final_v1';

    /**
     * Cotizaciones COBRANDO en el rango sin envío exitoso de la plantilla final.
     *
     * @return array<int, object>
     */
    public function findMissingOrFailedSends(Carbon $from, Carbon $to, ?int $limit = 200): array
    {
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.id',
                'CC.nombre',
                'CC.telefono',
                'CC.id_contenedor',
                'CC.updated_at',
                'CC.estado_cotizacion_final',
            ])
            ->where('CC.estado_cotizacion_final', 'COBRANDO')
            ->whereNull('CC.deleted_at')
            ->whereBetween('CC.updated_at', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ])
            ->orderBy('CC.id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $candidates = [];

        foreach ($rows as $row) {
            $phoneDigits = $this->normalizePhoneDigits((string) ($row->telefono ?? ''));
            if ($phoneDigits === '') {
                $candidates[] = (object) array_merge((array) $row, [
                    'skip_reason' => 'telefono_invalido',
                    'needs_resend' => false,
                ]);
                continue;
            }

            $hasOk = $this->hasSuccessfulTemplateSend(
                $phoneDigits,
                self::TEMPLATE_COTIZACION_FINAL,
                $from->copy()->startOfDay()
            );

            $candidates[] = (object) array_merge((array) $row, [
                'phone_digits' => $phoneDigits,
                'needs_resend' => !$hasOk,
                'skip_reason' => $hasOk ? 'ya_enviado_ok' : null,
            ]);
        }

        return $candidates;
    }

    /**
     * @return array{status:bool,error?:string,queued?:bool,id_cotizacion:int}
     */
    public function sendForCotizacion(int $idCotizacion): array
    {
        $cotizacion = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.id',
                'CC.telefono',
                'CC.id_contenedor',
                'CC.impuestos_final',
                'CC.volumen_final',
                'CC.monto_final',
                'CC.tarifa_final',
                'CC.nombre',
                'CC.logistica_final',
                'CC.servicios_extra_final',
                'CC.estado_cotizacion_final',
            ])
            ->where('CC.id', $idCotizacion)
            ->whereNull('CC.deleted_at')
            ->first();

        if (!$cotizacion) {
            return ['status' => false, 'error' => 'Cotización no encontrada', 'id_cotizacion' => $idCotizacion];
        }

        $contenedor = Contenedor::query()
            ->select('fecha_arribo', 'carga')
            ->where('id', $cotizacion->id_contenedor)
            ->first();

        if (!$contenedor) {
            return ['status' => false, 'error' => 'Contenedor no encontrado', 'id_cotizacion' => $idCotizacion];
        }

        $extrasCalc = $this->getCalculadoraImportacionExtrasByCotizacion($idCotizacion);
        $logisticaFinal = (float) ($cotizacion->logistica_final ?? 0)
            + (float) ($extrasCalc['recargos'] ?? 0)
            - (float) ($extrasCalc['descuento'] ?? 0);
        $impuestosFinal = (float) ($cotizacion->impuestos_final ?? 0);
        $serviciosExtraFinal = (float) ($cotizacion->servicios_extra_final ?? 0);
        $total = $logisticaFinal + $impuestosFinal + $serviciosExtraFinal;
        $carga = (string) $contenedor->carga;
        $fechaArribo = $contenedor->fecha_arribo;
        $nombre = (string) ($cotizacion->nombre ?? '');

        $phoneDigits = $this->normalizePhoneDigits((string) ($cotizacion->telefono ?? ''));
        if ($phoneDigits === '') {
            return ['status' => false, 'error' => 'Teléfono inválido', 'id_cotizacion' => $idCotizacion];
        }

        $phone = $phoneDigits . '@c.us';
        $serviciosExtrasLine = $serviciosExtraFinal > 0
            ? '☑️Servicios extras: $' . number_format($serviciosExtraFinal, 2) . "\n"
            : '';

        $message = "📦 *Consolidado #" . $carga . "*\n" .
            "Hola " . $nombre . " 😁 un gusto saludarte! \n" .
            "A continuación te envio la cotización final de tu importación📋📦.\n" .
            "🙋‍♂️PAGO PENDIENTE: \n" .
            "☑️Costo CBM: $" . number_format($logisticaFinal, 2) . "\n" .
            "☑️Impuestos: $" . number_format($impuestosFinal, 2) . "\n" .
            ($serviciosExtraFinal > 0 ? "☑️Servicios extras: $" . number_format($serviciosExtraFinal, 2) . "\n" : '') .
            "✅Total: $" . number_format($total, 2) . "\n" .
            "Pronto le aviso nuevos avances, que tengan buen dia \n" .
            "Último día de pago: " . date('d/m/Y', strtotime((string) $fechaArribo)) . "\n";

        $pathPdf = app(CotizacionFinalController::class)->generateBoletaPdfPathForWhatsApp($idCotizacion);

        $resultFinal = $this->sendMessage(
            $message,
            $phone,
            0,
            'consolidado',
            CoordinacionWhatsappPayload::consolidadoCotizacionFinal(
                $phone,
                $carga,
                $nombre,
                number_format($logisticaFinal, 2, '.', ''),
                number_format($impuestosFinal, 2, '.', ''),
                $serviciosExtrasLine,
                number_format($total, 2, '.', ''),
                date('d/m/Y', strtotime((string) $fechaArribo)),
                $pathPdf ? (string) $pathPdf : '',
                $message
            )
        );

        if (empty($resultFinal['status'])) {
            $error = (string) ($resultFinal['error'] ?? 'Fallo envío plantilla cotización final');
            Log::error('CotizacionFinalCobranzaWhatsappService: fallo plantilla final', [
                'id_cotizacion' => $idCotizacion,
                'error' => $error,
            ]);

            return [
                'status' => false,
                'error' => $error,
                'id_cotizacion' => $idCotizacion,
                'queued' => !empty($resultFinal['queued']),
            ];
        }

        return [
            'status' => true,
            'id_cotizacion' => $idCotizacion,
            'queued' => !empty($resultFinal['queued']),
        ];
    }

    public function normalizePhoneDigits(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) === 9) {
            return '51' . $digits;
        }
        if (strlen($digits) === 10 && substr($digits, 0, 1) === '0') {
            return '51' . substr($digits, 1);
        }

        return $digits;
    }

    private function hasSuccessfulTemplateSend(string $phoneDigits, string $template, Carbon $since): bool
    {
        return WaInboxMessage::query()
            ->where('direction', 'out')
            ->where('template_name', $template)
            ->whereIn('delivery_status', ['sent', 'delivered', 'read', 'pending'])
            ->where('created_at', '>=', $since)
            ->whereHas('conversation', function ($q) use ($phoneDigits) {
                $q->where('phone_e164', $phoneDigits)
                    ->orWhere('phone_e164', 'like', '%' . $phoneDigits);
            })
            ->exists();
    }

    /**
     * @return array{recargos:float,descuento:float}
     */
    private function getCalculadoraImportacionExtrasByCotizacion(int $idCotizacion): array
    {
        if ($idCotizacion <= 0 || !Schema::hasTable('calculadora_importacion')) {
            return ['recargos' => 0.0, 'descuento' => 0.0];
        }

        $row = DB::table('calculadora_importacion')
            ->where('id_cotizacion', $idCotizacion)
            ->orderByDesc('id')
            ->first(['tarifa_total_extra_proveedor', 'tarifa_total_extra_item', 'tarifa_descuento']);

        if (!$row) {
            return ['recargos' => 0.0, 'descuento' => 0.0];
        }

        $recargos = (float) ($row->tarifa_total_extra_proveedor ?? 0) + (float) ($row->tarifa_total_extra_item ?? 0);
        $descuento = (float) ($row->tarifa_descuento ?? 0);

        return ['recargos' => round($recargos, 2), 'descuento' => round($descuento, 2)];
    }
}
