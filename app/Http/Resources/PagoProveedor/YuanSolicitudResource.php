<?php

namespace App\Http\Resources\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorIngreso;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class YuanSolicitudResource extends JsonResource
{
    use FileTrait;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{request: PagoProveedorSolicitud, providers: Collection<int, PagoProveedorSolicitudProveedor>, number?: int} $group */
        $group = $this->resource;
        $solicitud = $group['request'];
        $yuan = app(PagoProveedorYuanService::class);
        $summary = $yuan->summary($group);
        $manual = $solicitud->origin === PagoProveedorSolicitud::ORIGIN_MANUAL;

        return [
            'request_id' => $solicitud->id,
            'number' => $group['number'] ?? null,
            'code' => $group['providers']->pluck('request_code')->filter()->unique()->implode(' / '),
            'date' => $yuan->dateOf($solicitud),
            'country' => $yuan->countryOf($solicitud),
            'partner' => PagoProveedorSolicitud::partnerLabel($solicitud->partner),
            'client' => $solicitud->client_name ?: $solicitud->partner,
            'organization_id' => $solicitud->organizacion_id,
            'organization' => $solicitud->organizacion?->No_Organizacion,
            // #1747: nombre del socio que envía los dólares (organización; las de Ventas son de Pro Business).
            'partner_name' => $solicitud->organizacion?->No_Organizacion
                ?: ($manual ? PagoProveedorSolicitud::partnerLabel($solicitud->partner) : PagoProveedorSolicitud::PARTNER_OWN_NAME),
            'manual' => $manual,
            'consolidation' => $solicitud->consolidation,
            ...$summary,
            // China edita todas: las manuales completas y las de Ventas solo en los datos del proveedor.
            'can_edit' => true,
            'providers' => YuanPagoResource::collection($group['providers'])->resolve($request),
            'incomes' => $solicitud->ingresos->map(fn (PagoProveedorIngreso $income) => [
                'id' => $income->id,
                'usd_amount' => $income->usd_amount,
                'exchange_rate' => $income->exchange_rate,
                'yuan_amount' => $income->yuan_amount,
                'received_on' => $income->received_on->toDateString(),
                'receipt_name' => $income->receipt_name,
                'receipt_url' => $income->receipt_path ? $this->cdnStorageUrl($income->receipt_path) : null,
                'can_remove' => $yuan->canRemoveIncome($solicitud, $group['providers']),
            ])->values()->all(),
        ];
    }
}
