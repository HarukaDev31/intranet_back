<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserBusinessRequest extends FormRequest
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
        $userId = auth()->id();
        $userBusinessId = auth()->user()->id_user_business ?? null;

        return [
            'business_name' => 'nullable|string|max:255',
            'business_ruc' => 'nullable|string|max:20|unique:user_business,ruc,' . $userBusinessId,
            'comercial_capacity' => 'nullable|string|max:255',
            'rubric' => 'nullable|string|max:255',
            'social_address' => 'nullable|string|max:500',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Limpiar datos vacíos o solo espacios
        $data = $this->all();
        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                unset($data[$key]);
            }
        }
        $this->replace($data);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'business_name.string' => 'El nombre de la empresa debe ser texto',
            'business_name.max' => 'El nombre de la empresa no puede exceder 255 caracteres',
            'business_ruc.string' => 'El RUC debe ser texto',
            'business_ruc.max' => 'El RUC no puede exceder 20 caracteres',
            'business_ruc.unique' => 'El RUC ya está registrado',
            'comercial_capacity.string' => 'La capacidad comercial debe ser texto',
            'comercial_capacity.max' => 'La capacidad comercial no puede exceder 255 caracteres',
            'rubric.string' => 'El rubro debe ser texto',
            'rubric.max' => 'El rubro no puede exceder 255 caracteres',
            'social_address.string' => 'La dirección social debe ser texto',
            'social_address.max' => 'La dirección social no puede exceder 500 caracteres',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Si es una actualización, verificar que al menos un campo esté presente
            $user = auth()->user();
            if ($user && $user->id_user_business) {
                $hasAnyField = $this->has('business_name') || 
                              $this->has('business_ruc') || 
                              $this->has('comercial_capacity') || 
                              $this->has('rubric') || 
                              $this->has('social_address');
                
                if (!$hasAnyField) {
                    $validator->errors()->add('general', 'Debe proporcionar al menos un campo para actualizar');
                }
            }
        });
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
