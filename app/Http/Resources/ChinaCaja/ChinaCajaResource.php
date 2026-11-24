<?php

namespace App\Http\Resources\ChinaCaja;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos ya calculados por los servicios de ChinaCaja (el front solo los pinta).
 */
class ChinaCajaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
