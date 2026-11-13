<?php

namespace App\Http\Requests\PagoProveedor;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePagoProveedorCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_cambio' => ['nullable', 'numeric', 'gt:0'],
            'tipo_cambio_confirmado' => ['required', 'boolean'],
            'proveedores' => ['required', 'array', 'min:1'],
            'proveedores.*.id' => ['nullable', 'integer'],
            'proveedores.*.code_supplier' => ['nullable', 'string', 'max:50'],
            'proveedores.*.nombre_proveedor' => ['nullable', 'string', 'max:150'],
            'proveedores.*.producto' => ['nullable', 'string', 'max:255'],
            'proveedores.*.moneda' => ['required', 'in:RMB,USD'],
            'proveedores.*.tipo_pago' => ['required', 'in:transferencia,tt,tarjeta'],
            'proveedores.*.valor_carga_usd' => ['nullable', 'numeric', 'min:0'],
            'proveedores.*.valor_carga_rmb' => ['nullable', 'numeric', 'min:0'],
            'proveedores.*.modalidad_abono' => ['required', 'in:total,fraccionado'],
            'proveedores.*.modalidad_pago' => ['required', 'in:total,fraccionado'],
            'proveedores.*.porcentaje_1' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'proveedores.*.porcentaje_2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'proveedores.*.fecha_cuota_2' => ['nullable', 'date'],
            'proveedores.*.comision_bancaria' => ['required', 'boolean'],
            'proveedores.*.comision_administrativa' => ['required', 'boolean'],
            'proveedores.*.proforma' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedores.*.proforma.mimes' => 'La proforma debe ser PDF, imagen, Word o Excel.',
            'proveedores.*.proforma.max' => 'La proforma no debe superar los 10 MB.',
        ];
    }
}
