<?php

namespace App\Http\Requests\PagoProveedor;

use Illuminate\Support\Arr;

class ResumenPagoProveedorCotizacionRequest extends UpdatePagoProveedorCotizacionRequest
{
    public function rules(): array
    {
        return [
            'cod_cotizacion_cc' => ['required', 'string', 'max:20'],
            'cotizacion_id' => ['nullable', 'integer'],
        ] + Arr::except(parent::rules(), ['tipo_cambio_confirmado', 'proveedores.*.proforma']);
    }
}
