<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreViaticoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'subject' => 'required|string|max:255',
            'reimbursement_date' => 'required|date',
            'requesting_area' => 'required|string|max:255',
            'expense_description' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'receipt_file' => 'nullable|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls|max:10240'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'subject.required' => 'El asunto es obligatorio',
            'subject.string' => 'El asunto debe ser texto',
            'subject.max' => 'El asunto no puede exceder 255 caracteres',
            'reimbursement_date.required' => 'La fecha de reintegro es obligatoria',
            'reimbursement_date.date' => 'La fecha de reintegro debe ser una fecha válida',
            'requesting_area.required' => 'El área solicitante es obligatoria',
            'requesting_area.string' => 'El área solicitante debe ser texto',
            'requesting_area.max' => 'El área solicitante no puede exceder 255 caracteres',
            'expense_description.required' => 'La descripción del gasto es obligatoria',
            'expense_description.string' => 'La descripción del gasto debe ser texto',
            'total_amount.required' => 'El monto total es obligatorio',
            'total_amount.numeric' => 'El monto total debe ser un número',
            'total_amount.min' => 'El monto total debe ser mayor o igual a 0',
            'receipt_file.image' => 'El archivo debe ser una imagen',
            'receipt_file.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif',
            'receipt_file.max' => 'La imagen no puede ser mayor a 10MB'
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
