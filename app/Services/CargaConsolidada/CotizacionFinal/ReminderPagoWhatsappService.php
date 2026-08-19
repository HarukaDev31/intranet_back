<?php

namespace App\Services\CargaConsolidada\CotizacionFinal;

use App\Jobs\SendReminderPagoWhatsAppJob;
use App\Models\CargaConsolidada\Contenedor;
use App\Traits\FileTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReminderPagoWhatsappService
{
    use FileTrait;

    /**
     * @return array{success:bool,message?:string,data?:array<string,mixed>}
     */
    public function preview(int $idCotizacion): array
    {
        $payload = $this->buildPayload($idCotizacion);
        if ($payload === null) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        if ($payload['phone'] === '') {
            return ['success' => false, 'message' => 'El cliente no tiene un teléfono válido'];
        }

        return [
            'success' => true,
            'data' => [
                'id_cotizacion' => $idCotizacion,
                'cliente' => $payload['nombre'],
                'phone' => $payload['phone'],
                'carga' => $payload['carga'],
                'message' => $payload['message'],
                'has_pdf' => $payload['has_pdf'],
                'pdf_url' => $payload['pdf_url'],
            ],
        ];
    }

    /**
     * @return array{nombre:string,phone:string,phone_id:string,carga:string,message:string,has_pdf:bool,pdf_url:?string}|null
     */
    public function buildPayload(int $idCotizacion): ?array
    {
        $cotizacion = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.id',
                'CC.telefono',
                'CC.id_contenedor',
                'CC.estado_cotizacion_final',
                'CC.impuestos_final',
                'CC.monto_final',
                'CC.nombre',
                'CC.logistica_final',
                'CC.servicios_extra_final',
                'CC.descuento',
                'CC.cotizacion_final_url',
                'CC.recargos',
                DB::raw('(
                    SELECT IFNULL(SUM(cccp.monto), 0)
                    FROM contenedor_consolidado_cotizacion_coordinacion_pagos cccp
                    JOIN cotizacion_coordinacion_pagos_concept ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_cotizacion = CC.id
                    AND (ccp.name = "LOGISTICA" OR ccp.name = "IMPUESTOS")
                ) as total_pagos'),
            ])
            ->where('CC.id', $idCotizacion)
            ->first();

        if (!$cotizacion) {
            return null;
        }

        $contenedor = Contenedor::select('carga', 'fecha_arribo')
            ->where('id', $cotizacion->id_contenedor)
            ->first();

        $carga = $contenedor ? (string) $contenedor->carga : 'N/A';
        $fechaArribo = $contenedor ? $contenedor->fecha_arribo : null;
        $recargos=(float) ($cotizacion->recargos ?? 0);
        $logisticaFinal = (float) ($cotizacion->logistica_final ?? 0);
        $impuestosFinal = (float) ($cotizacion->impuestos_final ?? 0);
        $serviciosExtraFinal = (float) ($cotizacion->servicios_extra_final ?? 0);
        $descuento = (float) ($cotizacion->descuento ?? 0);
        $totalCotizacion = $logisticaFinal + $impuestosFinal + $serviciosExtraFinal - $descuento + $recargos;
        $totalPagos = (float) ($cotizacion->total_pagos ?? 0);
        $pendiente = $totalCotizacion - $totalPagos;
        $isAjustado = ($cotizacion->estado_cotizacion_final ?? '') === 'AJUSTADO';
        $descripcionPendiente = $isAjustado
            ? 'Usted cuenta con un pago pendiente por concepto de Ajuste de Valor, es necesario realizar el pago para continuar con el proceso de nacionalización.'
            : 'Usted cuenta con un pago pendiente, es necesario realizar el pago para continuar con el proceso de nacionalización.';

        $message = "🙋🏽‍♀ *RECORDATORÍO DE PAGO*\n\n"
            . "📦 *Consolidado #{$carga}*\n"
            . $descripcionPendiente . "\n\n"
            . "*Resumen de Pago*\n"
            . "✅ Cotización final: $" . number_format($totalCotizacion, 2, '.', '') . "\n"
            . "✅ Adelanto: $" . number_format($totalPagos, 2, '.', '') . "\n"
            . "✅ *Pendiente de pago: $" . number_format($pendiente, 2, '.', '') . "*\n"
            . $this->formatUltimoDiaPagoLine($fechaArribo)
            . "\nPor favor debe enviar el comprobante de pago a la brevedad.";

        $phone = $this->normalizePhone((string) ($cotizacion->telefono ?? ''));
        $pdfUrl = $this->cdnStorageUrl($cotizacion->cotizacion_final_url ?? null);

        return [
            'nombre' => (string) ($cotizacion->nombre ?? ''),
            'phone' => $phone,
            'phone_id' => $phone !== '' ? $phone . '@c.us' : '',
            'carga' => $carga,
            'message' => $message,
            'has_pdf' => $pdfUrl !== null && $pdfUrl !== '',
            'pdf_url' => $pdfUrl,
        ];
    }

    public function enqueue(int $idCotizacion, int $sleep = 0): void
    {
        SendReminderPagoWhatsAppJob::dispatch($idCotizacion, $sleep);
    }

    public function formatUltimoDiaPagoLine(?string $fechaArribo): string
    {
        if ($fechaArribo === null || trim($fechaArribo) === '') {
            return '';
        }

        $tz = 'America/Lima';
        $hoy = Carbon::now($tz)->startOfDay();
        $limite = Carbon::parse($fechaArribo, $tz)->startOfDay();
        $fechaMostrar = $hoy->greaterThan($limite) ? $hoy : $limite;

        return 'Último día de pago: ' . $fechaMostrar->format('d/m/Y') . "\n";
    }

    public function normalizePhone(string $rawTelefono): string
    {
        $telefonoDigits = preg_replace('/\D/', '', $rawTelefono) ?? '';
        if (strlen($telefonoDigits) === 9) {
            return '51' . $telefonoDigits;
        }
        if (strlen($telefonoDigits) === 10 && substr($telefonoDigits, 0, 1) === '0') {
            return '51' . substr($telefonoDigits, 1);
        }

        return $telefonoDigits;
    }
}
