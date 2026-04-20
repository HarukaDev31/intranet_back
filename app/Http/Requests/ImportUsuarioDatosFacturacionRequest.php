<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportUsuarioDatosFacturacionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'excel_file' => 'required|file|mimes:xlsx,xls,xlsm,csv|max:102400',
        ];
    }

    public function messages()
    {
        return [
            'excel_file.required' => 'Debe seleccionar un archivo de Excel.',
            'excel_file.file' => 'El archivo enviado no es v?lido.',
            'excel_file.mimes' => 'Solo se permiten archivos .xlsx, .xls, .xlsm o .csv.',
            'excel_file.max' => 'El archivo no puede superar 100MB.',
        ];
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

