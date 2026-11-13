<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorAprobacionService;
use App\Services\PagoProveedor\PagoProveedorEtapa1Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PagoProveedorSolicitud $solicitud */
        $solicitud = $this->resource;
        $solicitud->loadMissing(['proveedores.datos', 'proveedores.consolidatedProvider', 'proveedores.aplicaciones.abono', 'proveedores.ejecuciones', 'abonos.aplicaciones', 'cotizacion']);
        $solicitud->proveedores->each(fn (PagoProveedorSolicitudProveedor $provider) => $provider->setRelation('solicitud', $solicitud));
        $solicitud->abonos->each(fn (PagoProveedorAbono $abono) => $abono->setRelation('solicitud', $solicitud));

        $etapa1 = app(PagoProveedorEtapa1Service::class);
        $quoted = 0.0;
        $paid = 0.0;
        $pending = 0.0;
        $excess = 0.0;

        foreach ($solicitud->proveedores as $provider) {
            if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                continue;
            }
            $required = $etapa1->requiredAmount($provider);
            $balance = $provider->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT
                ? app(PagoProveedorAprobacionService::class)->outstanding($provider)
                : $etapa1->balance($provider);
            $quoted += (float) $provider->total_amount;
            $paid += round($required - $balance, 2);
            $pending += $balance;
        }
        foreach ($solicitud->abonos as $abono) {
            if ($abono->status !== PagoProveedorAbono::STATUS_CANCELLED) {
                $excess += (float) $abono->surplus;
            }
        }

        return [
            'id' => $solicitud->id,
            'numero_orden' => $solicitud->order_number,
            'cotizacion_id' => $solicitud->quote_id,
            'cotizacion_codigo' => $solicitud->cotizacion?->code,
            'cod_cotizacion_cc' => $solicitud->consolidated_quote_code,
            'cliente_nombre' => $solicitud->client_name,
            'cliente_documento' => $solicitud->client_document,
            'cliente_whatsapp' => $solicitud->client_whatsapp,
            'cliente_correo' => $solicitud->client_email,
            'consolidado' => $solicitud->consolidation,
            'tipo_cambio' => $solicitud->exchange_rate,
            'creado_en' => optional($solicitud->created_at)->toIso8601String(),
            'total_cotizado' => round($quoted, 2),
            'total_abonado' => round($paid, 2),
            'saldo_pendiente' => round($pending, 2),
            'excedente' => round($excess, 2),
            'proveedores' => SolicitudProveedorDetalleResource::collection($solicitud->proveedores)->resolve(),
            'documentos' => $this->documentos($solicitud),
            'abonos' => AbonoResource::collection($solicitud->abonos)->resolve(),
            'historial' => HistorialResource::collection(
                PagoProveedorHistorial::query()
                    ->where('entity_type', PagoProveedorHistorial::ENTITY_REQUEST)
                    ->where('entity_id', $solicitud->id)
                    ->orderByDesc('id')
                    ->limit(40)
                    ->get()
            )->resolve(),
        ];
    }

    /**
     * PDF ya emitidos de la solicitud con su paso: el resumen general (que se emite
     * sobre la cotización) y el balance de cada proveedor. Marca los que se regenerarán
     * porque el proceso avanzó o retrocedió de etapa.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentos(PagoProveedorSolicitud $solicitud): array
    {
        $servicio = app(\App\Services\PagoProveedor\PagoProveedorDocumentoService::class);
        $emitidos = [];

        if ($solicitud->cotizacion !== null) {
            $resumen = app(\App\Services\PagoProveedor\PagoProveedorPdfService::class)->estadoResumen($solicitud->cotizacion);
            if ($resumen !== null) {
                $emitidos[] = ['alcance_id' => 0, 'documento' => 'resumen'] + $resumen;
            }
        }

        $pdf = app(\App\Services\PagoProveedor\PagoProveedorPdfService::class);

        foreach ($servicio->estadoDeSolicitud((int) $solicitud->id) as $documento) {
            $proveedor = $documento['alcance_id'] > 0
                ? $solicitud->proveedores->firstWhere('id', (int) $documento['alcance_id'])
                : null;
            $pasoActual = $proveedor !== null
                ? $servicio->pasoDeSolicitudProveedor($proveedor)
                : \App\Services\PagoProveedor\PagoProveedorDocumentoService::pasoDeProveedores($solicitud->proveedores);

            // El balance también se rehace si cambian los montos, no solo la etapa.
            $vigente = $proveedor !== null ? $pdf->estadoBalance($proveedor) : null;
            $desactualizado = $vigente !== null
                ? (bool) $vigente['desactualizado']
                : ((int) $documento['paso'] !== $pasoActual);

            $emitidos[] = $documento + [
                'paso_actual' => $pasoActual,
                'paso_actual_nombre' => \App\Models\PagoProveedor\PagoProveedorDocumento::stepLabel($pasoActual),
                'desactualizado' => $desactualizado,
            ];
        }

        return $emitidos;
    }
}
