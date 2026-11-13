<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Http\Resources\PagoProveedor\FormularioTtResource;
use App\Http\Resources\PagoProveedor\SolicitudDetalleResource;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

trait RespondeConResource
{
    protected function ok(JsonResource $resource, int $status = 200): JsonResponse
    {
        return $resource->additional(['success' => true])->response()->setStatusCode($status);
    }

    protected function pagina(AnonymousResourceCollection $resources, LengthAwarePaginator $pagina): JsonResponse
    {
        return $resources->additional([
            'success' => true,
            'pagination' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ])->response();
    }

    /**
     * @param  PagoProveedorSolicitud|PagoProveedorDatoProveedor|array<string, mixed>  $payload
     */
    protected function datos(PagoProveedorSolicitud|PagoProveedorDatoProveedor|array $payload, int $status = 200): JsonResponse
    {
        if ($payload instanceof PagoProveedorSolicitud) {
            return $this->ok(new SolicitudDetalleResource($payload), $status);
        }
        if ($payload instanceof PagoProveedorDatoProveedor) {
            return $this->ok(new FormularioTtResource($payload), $status);
        }
        if (($payload['solicitud'] ?? null) instanceof PagoProveedorSolicitud) {
            $payload['solicitud'] = (new SolicitudDetalleResource($payload['solicitud']))->resolve();
        }

        return response()->json(['success' => true, 'data' => $payload], $status);
    }
}
