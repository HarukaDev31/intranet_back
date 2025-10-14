<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserProfileRequest extends FormRequest
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
        
        return [
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'fecha_nacimiento' => 'sometimes|date',
            'country' => 'sometimes|string|exists:pais,ID_Pais',
            'city' => 'sometimes|string|exists:provincia,ID_Provincia',
            'departamento' => 'sometimes|string|exists:departamento,ID_Departamento',
            'distrito' => 'sometimes|string|exists:distrito,ID_Distrito',
            'phone' => 'sometimes|string|max:20|unique:users,whatsapp,' . $userId,
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:10240',
            'dni' => 'sometimes|string|max:20|unique:users,dni,' . $userId,
            'goals' => 'sometimes|nullable|string'
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
            'full_name.string' => 'El nombre completo debe ser texto',
            'full_name.max' => 'El nombre completo no puede exceder 255 caracteres',
            'email.email' => 'El email no es válido',
            'email.max' => 'El email no puede exceder 255 caracteres',
            'email.unique' => 'El email ya está registrado',
            'age.integer' => 'La edad debe ser un número entero',
            'age.min' => 'La edad debe ser mayor a 0',
            'age.max' => 'La edad no puede ser mayor a 120',
            'country.string' => 'El país debe ser texto',
            'country.max' => 'El país no puede exceder 100 caracteres',
            'phone.string' => 'El teléfono debe ser texto',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'phone.unique' => 'El número de teléfono ya está registrado',
            'dni.string' => 'El DNI debe ser texto',
            'dni.max' => 'El DNI no puede exceder 20 caracteres',
            'dni.unique' => 'El DNI ya está registrado',
            'photo.image' => 'El archivo debe ser una imagen',
            'photo.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif',
            'photo.max' => 'La imagen no puede ser mayor a 2MB',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'city.string' => 'La ciudad debe ser texto',
            'city.exists' => 'La ciudad no existe',
            'departamento.string' => 'El departamento debe ser texto',
            'departamento.exists' => 'El departamento no existe',
            'distrito.string' => 'El distrito debe ser texto',
            'distrito.exists' => 'El distrito no existe',
            'goal.string' => 'El objetivo debe ser texto',
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
            // Verificar que al menos un campo esté presente
            $hasAnyField = $this->has('full_name') || 
                          $this->has('email') || 
                          $this->has('age') || 
                          $this->has('country') || 
                          $this->has('city') || 
                          $this->has('departamento') || 
                          $this->has('distrito') || 
                          $this->has('phone') || 
                          $this->has('dni') ||
                          $this->hasFile('photo');
            
            if (!$hasAnyField) {
                $validator->errors()->add('general', 'Debe proporcionar al menos un campo para actualizar');
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
