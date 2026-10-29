<?php

namespace App\Http\Requests\PagoProveedor;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoProveedorCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cod_cotizacion_cc' => ['required', 'string', 'regex:/^CO\d{8}$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'cod_cotizacion_cc.regex' => 'El número de cotización de Consolidada debe tener el formato CO y 8 dígitos.',
        ];
    }
}
