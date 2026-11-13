<?php

namespace App\Http\Resources\ChinaBilling;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de operación o cobro ya calculada por ChinaBillingReader (el front solo la pinta).
 */
class ChinaBillingRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
