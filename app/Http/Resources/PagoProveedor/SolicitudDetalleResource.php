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
}
