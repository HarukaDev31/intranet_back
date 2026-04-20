<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RollbackUsuarioDatosFacturacionImportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id_import' => 'required|integer|exists:imports_usuario_datos_facturacion,id',
        ];
    }

    public function messages()
    {
        return [
            'id_import.required' => 'El id de importaci?n es obligatorio.',
            'id_import.integer' => 'El id de importaci?n debe ser num?rico.',
            'id_import.exists' => 'La importaci?n indicada no existe.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'id_import' => $this->route('idImport'),
        ]);
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validaci?n',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

