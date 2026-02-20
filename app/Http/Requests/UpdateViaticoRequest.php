<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateViaticoRequest extends FormRequest
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
            'subject' => 'sometimes|string|max:255',
            'reimbursement_date' => 'sometimes|date',
            'requesting_area' => 'sometimes|string|max:255',
            'expense_description' => 'sometimes|string',
            'total_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:PENDING,CONFIRMED,REJECTED',
            'receipt_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls|max:102400',
            'payment_receipt_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg,pdf,doc,docx|max:102400',
            'payment_receipt_banco' => 'nullable|string|max:100',
            'payment_receipt_monto' => 'nullable|numeric|min:0',
            'payment_receipt_fecha_cierre' => 'nullable|date',
            'delete_file' => 'sometimes|boolean',
            'delete_retribucion_id' => 'nullable|integer|exists:viaticos_retribuciones,id',
            'items' => 'sometimes|array|min:0',
            'items.*.id' => 'nullable|integer|exists:viaticos_pagos,id',
            'items.*.concepto' => 'required|string|max:255',
            'items.*.monto' => 'required|numeric|min:0',
            'items.*.receipt_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls|max:102400',
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
            'status.in' => 'El estado debe ser PENDING, CONFIRMED o REJECTED',
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
