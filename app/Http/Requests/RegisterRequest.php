<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'whatsapp' => 'required|string|max:255|unique:users',
            'dni' => 'required|numeric|digits:8',
            'fechaNacimiento' => 'nullable|date',
            'goals' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre es requerido',
            'lastname.string' => 'El apellido debe ser texto',
            'lastname.max' => 'El apellido no puede exceder 255 caracteres',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email no es válido',
            'email.unique' => 'El email ya está registrado',
            'whatsapp.string' => 'El whatsapp debe ser texto',
            'whatsapp.max' => 'El whatsapp no puede exceder 255 caracteres',
            'whatsapp.unique' => 'El número de whatsapp ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'dni.required' => 'El DNI es requerido',
        ];
    }
    public function failedValidation(Validator $validator)
    {
        throw new \Exception($validator->errors()->first(), 422);
    }
}
