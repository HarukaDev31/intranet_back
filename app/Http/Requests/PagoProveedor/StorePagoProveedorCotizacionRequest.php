<?php

namespace App\Http\Requests\PagoProveedor;

class StorePagoProveedorCotizacionRequest extends UpdatePagoProveedorCotizacionRequest
{
    public function rules(): array
    {
        return [
            'cod_cotizacion_cc' => ['required', 'string', 'regex:/^CO\d{8}$/i'],
        ] + parent::rules();
    }

    public function messages(): array
    {
        return [
            'cod_cotizacion_cc.regex' => 'El número de cotización de Consolidada debe tener el formato CO y 8 dígitos.',
        ] + parent::messages();
    }
}
