<?php

namespace App\Http\Requests\PagoProveedor;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoProveedorProformaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proforma' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }
}
