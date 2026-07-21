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

    public const TEMPLATE_RESUMEN_PAGO = 'pb_consolidado_resumen_pago_v1';

    /**
     * Cotizaciones COBRANDO sin envío exitoso de la plantilla final.
     * Cotizacion tiene timestamps=false → no se puede filtrar por updated_at.
     *
     * @return array<int, object>
     */
    public function findMissingOrFailedSends(
        ?Carbon $activityFrom = null,
        ?Carbon $activityTo = null,
        ?int $limit = 200,
        ?int $idContenedor = null,
        bool $strictActivity = false
    ): array {
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.id',
                'CC.nombre',
                'CC.telefono',
                'CC.id_contenedor',
                'CC.estado_cotizacion_final',
            ])
            ->where('CC.estado_cotizacion_final', 'COBRANDO')
            ->whereNull('CC.deleted_at')
            ->orderBy('CC.id');

        if ($idContenedor !== null && $idContenedor > 0) {
            $query->where('CC.id_contenedor', $idContenedor);
        }

        $rows = $query->get();
        $candidates = [];

        foreach ($rows as $row) {
            $phoneDigits = $this->normalizePhoneDigits((string) ($row->telefono ?? ''));
            if ($phoneDigits === '') {
                $candidates[] = (object) array_merge((array) $row, [
                    'skip_reason' => 'telefono_invalido',
                    'needs_resend' => false,
                    'phone_digits' => '',
                    'activity_in_range' => false,
                ]);
                continue;
            }

            $hasOk = $this->hasSuccessfulTemplateSend(
                $phoneDigits,
                self::TEMPLATE_COTIZACION_FINAL,
                null
            );

            $activityInRange = false;
            if ($activityFrom !== null && $activityTo !== null) {
                $activityInRange = $this->hasOutboundActivityInRange(
                    $phoneDigits,
                    $activityFrom->copy()->startOfDay(),
                    $activityTo->copy()->endOfDay()
                );
            }

            $candidates[] = (object) array_merge((array) $row, [
                'phone_digits' => $phoneDigits,
                'needs_resend' => !$hasOk,
                'skip_reason' => $hasOk ? 'ya_enviado_ok' : null,
                'activity_in_range' => $activityInRange,
            ]);
        }

        if ($activityFrom !== null && $activityTo !== null) {
            $withActivity = array_values(array_filter($candidates, static function ($row) {
                return !empty($row->needs_resend) && !empty($row->activity_in_range);
            }));

            if ($withActivity !== []) {
                $candidates = $withActivity;
            } elseif ($strictActivity) {
                $candidates = [];
            } else {
                // Fallback: todos los COBRANDO sin plantilla OK (sin updated_at no hay mejor filtro).
                $candidates = array_values(array_filter($candidates, static function ($row) {
                    return !empty($row->needs_resend)
                        || (($row->skip_reason ?? '') === 'telefono_invalido');
                }));
            }
        }

        if ($limit !== null && $limit > 0 && count($candidates) > $limit) {
            $candidates = array_slice($candidates, 0, $limit);
        }

        return $candidates;
    }

    /**
     * @return array{cobrando_total:int,sin_plantilla_ok:int,con_actividad:int}
     */
    public function diagnostics(?Carbon $activityFrom, ?Carbon $activityTo, ?int $idContenedor = null): array
    {
        $base = DB::table('contenedor_consolidado_cotizacion')
            ->where('estado_cotizacion_final', 'COBRANDO')
            ->whereNull('deleted_at');

        if ($idContenedor !== null && $idContenedor > 0) {
            $base->where('id_contenedor', $idContenedor);
        }

        $cobrandoTotal = (int) (clone $base)->count();
        $all = $this->findMissingOrFailedSends(null, null, null, $idContenedor, false);
        $sinOk = 0;
        $conActividad = 0;

        foreach ($all as $row) {
            if (empty($row->needs_resend)) {
                continue;
            }
            $sinOk++;
            if ($activityFrom !== null && $activityTo !== null && !empty($row->phone_digits)) {
                if ($this->hasOutboundActivityInRange(
                    (string) $row->phone_digits,
                    $activityFrom->copy()->startOfDay(),
                    $activityTo->copy()->endOfDay()
                )) {
                    $conActividad++;
                }
            }
        }

        return [
            'cobrando_total' => $cobrandoTotal,
            'sin_plantilla_ok' => $sinOk,
            'con_actividad' => $conActividad,
        ];
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

    private function hasSuccessfulTemplateSend(string $phoneDigits, string $template, ?Carbon $since): bool
    {
        $query = WaInboxMessage::query()
            ->where('direction', 'out')
            ->where('template_name', $template)
            ->whereIn('delivery_status', ['sent', 'delivered', 'read', 'pending'])
            ->whereHas('conversation', function ($q) use ($phoneDigits) {
                $q->where('phone_e164', $phoneDigits)
                    ->orWhere('phone_e164', 'like', '%' . $phoneDigits);
            });

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return $query->exists();
    }

    private function hasOutboundActivityInRange(string $phoneDigits, Carbon $from, Carbon $to): bool
    {
        return WaInboxMessage::query()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) {
                $q->where('template_name', self::TEMPLATE_RESUMEN_PAGO)
                    ->orWhere('template_name', self::TEMPLATE_COTIZACION_FINAL)
                    ->orWhereNull('template_name');
            })
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
