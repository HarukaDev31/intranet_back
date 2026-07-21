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
     * Catálogo del flujo COBRANDO (orden de envío).
     *
     * @return array<int, array{key:string,label:string,description:string,selected_by_default:bool,has_media:bool,media_label:?string}>
     */
    public function availableTemplates(): array
    {
        return [
            [
                'key' => self::TEMPLATE_COTIZACION_FINAL,
                'label' => 'Cotización final',
                'description' => 'Mensaje con montos + PDF de boleta adjunto',
                'selected_by_default' => true,
                'has_media' => true,
                'media_label' => 'PDF boleta',
            ],
            [
                'key' => self::TEMPLATE_RESUMEN_PAGO,
                'label' => 'Resumen de pago',
                'description' => 'Adelanto / pendiente + imagen de cuentas',
                'selected_by_default' => true,
                'has_media' => true,
                'media_label' => 'Imagen de cuentas',
            ],
        ];
    }

    /**
     * Plantillas con vista previa del texto exacto que se enviaría por Meta.
     *
     * @return array{success:bool,error?:string,templates:array<int,array<string,mixed>>,phone?:string,cliente?:string}
     */
    public function buildTemplatesPreviewForCotizacion(int $idCotizacion): array
    {
        $ctx = $this->buildSendContext($idCotizacion);
        if ($ctx === null) {
            return [
                'success' => false,
                'error' => 'Cotización o contenedor no encontrado',
                'templates' => [],
            ];
        }

        $previews = [
            self::TEMPLATE_COTIZACION_FINAL => $this->buildCotizacionFinalPreviewText($ctx),
            self::TEMPLATE_RESUMEN_PAGO => $this->buildResumenPagoPreviewText($ctx),
        ];

        $templates = [];
        $order = 1;
        foreach ($this->availableTemplates() as $tpl) {
            $key = $tpl['key'];
            $templates[] = array_merge($tpl, [
                'order' => $order++,
                'preview' => $previews[$key] ?? '',
                'preview_type' => 'text',
            ]);
        }

        return [
            'success' => true,
            'templates' => $templates,
            'phone' => (string) ($ctx['phone'] ?? ''),
            'cliente' => (string) ($ctx['nombre'] ?? ''),
            'carga' => (string) ($ctx['carga'] ?? ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function defaultTemplateKeys(): array
    {
        return array_values(array_map(static function (array $t) {
            return $t['key'];
        }, array_filter($this->availableTemplates(), static function (array $t) {
            return !empty($t['selected_by_default']);
        })));
    }

    /**
     * @return array{status:bool,error?:string,queued?:bool,id_cotizacion:int,sent?:array<int,string>,failed?:array<string,string>}
     */
    public function sendForCotizacion(int $idCotizacion): array
    {
        return $this->sendSelectedTemplates($idCotizacion, $this->defaultTemplateKeys());
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @return array{status:bool,error?:string,queued?:bool,id_cotizacion:int,sent?:array<int,string>,failed?:array<string,string>}
     */
    public function sendSelectedTemplates(int $idCotizacion, array $templateKeys): array
    {
        $allowed = array_column($this->availableTemplates(), 'key');
        $keys = array_values(array_unique(array_filter(array_map('strval', $templateKeys), static function ($k) use ($allowed) {
            return in_array($k, $allowed, true);
        })));

        if ($keys === []) {
            return [
                'status' => true,
                'id_cotizacion' => $idCotizacion,
                'queued' => false,
                'sent' => [],
                'message' => 'Sin plantillas seleccionadas; no se envió WhatsApp',
            ];
        }

        $ctx = $this->buildSendContext($idCotizacion);
        if ($ctx === null) {
            return ['status' => false, 'error' => 'Cotización o contenedor no encontrado', 'id_cotizacion' => $idCotizacion];
        }
        if ($ctx['phone'] === '') {
            return ['status' => false, 'error' => 'Teléfono inválido', 'id_cotizacion' => $idCotizacion];
        }

        $sent = [];
        $failed = [];
        $anyQueued = false;
        $sleep = 0;

        foreach ($keys as $key) {
            if ($key === self::TEMPLATE_COTIZACION_FINAL) {
                $result = $this->sendCotizacionFinalTemplate($ctx, $sleep);
            } elseif ($key === self::TEMPLATE_RESUMEN_PAGO) {
                $result = $this->sendResumenPagoTemplate($ctx, $sleep > 0 ? $sleep : 5);
            } else {
                continue;
            }

            if (!empty($result['status'])) {
                $sent[] = $key;
                $anyQueued = $anyQueued || !empty($result['queued']);
                $sleep = 5;
            } else {
                $failed[$key] = (string) ($result['error'] ?? 'Error de envío');
            }
        }

        if ($sent === [] && $failed !== []) {
            return [
                'status' => false,
                'error' => reset($failed) ?: 'Fallo envío WhatsApp',
                'id_cotizacion' => $idCotizacion,
                'failed' => $failed,
                'sent' => $sent,
            ];
        }

        return [
            'status' => true,
            'id_cotizacion' => $idCotizacion,
            'queued' => $anyQueued,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

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
     * @return array<string, mixed>|null
     */
    private function buildSendContext(int $idCotizacion): ?array
    {
        $cotizacion = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.id',
                'CC.telefono',
                'CC.id_contenedor',
                'CC.impuestos_final',
                'CC.nombre',
                'CC.logistica_final',
                'CC.servicios_extra_final',
                DB::raw('(
                    SELECT IFNULL(SUM(cccp.monto), 0)
                    FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                    JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_cotizacion = CC.id
                    AND (ccp.name = "LOGISTICA" OR ccp.name = "IMPUESTOS")
                ) as total_pagos'),
            ])
            ->where('CC.id', $idCotizacion)
            ->whereNull('CC.deleted_at')
            ->first();

        if (!$cotizacion) {
            return null;
        }

        $contenedor = Contenedor::query()
            ->select('fecha_arribo', 'carga')
            ->where('id', $cotizacion->id_contenedor)
            ->first();

        if (!$contenedor) {
            return null;
        }

        $extrasCalc = $this->getCalculadoraImportacionExtrasByCotizacion($idCotizacion);
        $logisticaFinal = (float) ($cotizacion->logistica_final ?? 0)
            + (float) ($extrasCalc['recargos'] ?? 0)
            - (float) ($extrasCalc['descuento'] ?? 0);
        $impuestosFinal = (float) ($cotizacion->impuestos_final ?? 0);
        $serviciosExtraFinal = (float) ($cotizacion->servicios_extra_final ?? 0);
        $total = $logisticaFinal + $impuestosFinal + $serviciosExtraFinal;
        $totalPagos = (float) ($cotizacion->total_pagos ?? 0);
        $phoneDigits = $this->normalizePhoneDigits((string) ($cotizacion->telefono ?? ''));

        return [
            'id_cotizacion' => $idCotizacion,
            'phone' => $phoneDigits !== '' ? $phoneDigits . '@c.us' : '',
            'nombre' => (string) ($cotizacion->nombre ?? ''),
            'carga' => (string) $contenedor->carga,
            'fecha_arribo' => $contenedor->fecha_arribo,
            'logistica_final' => $logisticaFinal,
            'impuestos_final' => $impuestosFinal,
            'servicios_extra_final' => $serviciosExtraFinal,
            'total' => $total,
            'total_pagos' => $totalPagos,
            'total_a_pagar' => $total - $totalPagos,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function buildCotizacionFinalPreviewText(array $ctx): string
    {
        return "📦 *Consolidado #" . $ctx['carga'] . "*\n" .
            "Hola " . $ctx['nombre'] . " 😁 un gusto saludarte! \n" .
            "A continuación te envio la cotización final de tu importación📋📦.\n" .
            "🙋‍♂️PAGO PENDIENTE: \n" .
            "☑️Costo CBM: $" . number_format((float) $ctx['logistica_final'], 2) . "\n" .
            "☑️Impuestos: $" . number_format((float) $ctx['impuestos_final'], 2) . "\n" .
            (((float) $ctx['servicios_extra_final']) > 0
                ? "☑️Servicios extras: $" . number_format((float) $ctx['servicios_extra_final'], 2) . "\n"
                : '') .
            "✅Total: $" . number_format((float) $ctx['total'], 2) . "\n" .
            "Pronto le aviso nuevos avances, que tengan buen dia \n" .
            "Último día de pago: " . date('d/m/Y', strtotime((string) $ctx['fecha_arribo'])) . "\n";
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function buildResumenPagoPreviewText(array $ctx): string
    {
        return "💰*Resumen de Pago*\n" .
            "✅Cotización final: $" . number_format((float) $ctx['total'], 2) . "\n" .
            "✅Adelanto: $" . number_format((float) $ctx['total_pagos'], 2) . "\n" .
            "✅ *Pendiente de pago: $" . number_format((float) $ctx['total_a_pagar'], 2) . "*\n";
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{status:bool,error?:string,queued?:bool}
     */
    private function sendCotizacionFinalTemplate(array $ctx, int $sleep): array
    {
        $serviciosExtrasLine = ((float) $ctx['servicios_extra_final']) > 0
            ? '☑️Servicios extras: $' . number_format((float) $ctx['servicios_extra_final'], 2) . "\n"
            : '';

        $message = $this->buildCotizacionFinalPreviewText($ctx);

        $pathPdf = app(CotizacionFinalController::class)
            ->generateBoletaPdfPathForWhatsApp((int) $ctx['id_cotizacion']);

        $result = $this->sendMessage(
            $message,
            (string) $ctx['phone'],
            $sleep,
            'consolidado',
            CoordinacionWhatsappPayload::consolidadoCotizacionFinal(
                (string) $ctx['phone'],
                (string) $ctx['carga'],
                (string) $ctx['nombre'],
                number_format((float) $ctx['logistica_final'], 2, '.', ''),
                number_format((float) $ctx['impuestos_final'], 2, '.', ''),
                $serviciosExtrasLine,
                number_format((float) $ctx['total'], 2, '.', ''),
                date('d/m/Y', strtotime((string) $ctx['fecha_arribo'])),
                $pathPdf ? (string) $pathPdf : '',
                $message,
                $sleep
            )
        );

        if (empty($result['status'])) {
            Log::error('CotizacionFinalCobranzaWhatsappService: fallo plantilla final', [
                'id_cotizacion' => $ctx['id_cotizacion'],
                'error' => $result['error'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{status:bool,error?:string,queued?:bool}
     */
    private function sendResumenPagoTemplate(array $ctx, int $sleep): array
    {
        $messageResumen = $this->buildResumenPagoPreviewText($ctx);
        $pagosUrl = public_path('assets/images/pagos-full.jpg');

        $result = $this->sendMessage(
            $messageResumen,
            (string) $ctx['phone'],
            $sleep,
            'consolidado',
            CoordinacionWhatsappPayload::consolidadoResumenPago(
                (string) $ctx['phone'],
                number_format((float) $ctx['total'], 2, '.', ''),
                number_format((float) $ctx['total_pagos'], 2, '.', ''),
                number_format((float) $ctx['total_a_pagar'], 2, '.', ''),
                $pagosUrl,
                $messageResumen,
                $sleep
            )
        );

        if (empty($result['status'])) {
            Log::warning('CotizacionFinalCobranzaWhatsappService: fallo resumen pago', [
                'id_cotizacion' => $ctx['id_cotizacion'],
                'error' => $result['error'] ?? null,
            ]);
        }

        return $result;
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
