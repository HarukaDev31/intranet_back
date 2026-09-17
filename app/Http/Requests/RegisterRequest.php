<?php

namespace App\Http\Requests;

use App\Support\Phone\CountryPhoneHelper;
use App\Support\Register\ComoEnteroCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $prefix = (string) $this->input('whatsapp_prefix', '');
        $hasPrefixCol = Schema::hasColumn('users', 'whatsapp_prefix');

        $whatsappRules = ['required', 'string', 'max:255'];
        if ($hasPrefixCol) {
            $whatsappRules[] = Rule::unique('users', 'whatsapp')->where(function ($query) use ($prefix) {
                if ($prefix !== '') {
                    $query->where('whatsapp_prefix', $prefix);
                } else {
                    $query->where(function ($q) {
                        $q->whereNull('whatsapp_prefix')->orWhere('whatsapp_prefix', '');
                    });
                }
            });
        } else {
            $whatsappRules[] = 'unique:users';
        }

        return [
            'nombre' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'whatsapp' => $whatsappRules,
            'whatsapp_prefix' => 'nullable|string|max:8',
            'tipo_documento' => 'required|string|in:DNI,ID',
            'dni' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $tipo = strtoupper((string) $this->input('tipo_documento', 'DNI'));
                    $value = trim((string) $value);
                    if ($tipo === 'DNI') {
                        if (!preg_match('/^\d{8}$/', $value)) {
                            $fail('El DNI debe tener exactamente 8 dígitos');
                        }
                        return;
                    }
                    // ID: documento genérico (letras/números), 5–15 caracteres
                    if (!preg_match('/^[A-Za-z0-9\-]{5,15}$/', $value)) {
                        $fail('El ID debe tener entre 5 y 15 caracteres alfanuméricos');
                    }
                },
            ],
            'fechaNacimiento' => 'nullable|date',
            'goals' => 'nullable|string',
            'provincia_id' => 'nullable|integer|exists:provincia,ID_Provincia',
            'departamento_id' => 'nullable|integer|exists:departamento,ID_Departamento',
            'distrito_id' => 'nullable|integer|exists:distrito,ID_Distrito',
            'no_como_entero' => ['nullable', 'integer', Rule::in(ComoEnteroCatalog::acceptedCodes())],
            'no_otros_como_entero_empresa' => 'nullable|string|max:255',
            'pais_id' => 'nullable|integer|exists:pais,ID_Pais',
            'whatsapp_pais_id' => 'nullable|integer|exists:pais,ID_Pais',
        ];
    }

    /**
     * Separa prefijo y número local. No concatena el internacional en whatsapp.
     */
    protected function prepareForValidation()
    {
        $paisTel = $this->input('whatsapp_pais_id') ?: $this->input('pais_id');
        $code = CountryPhoneHelper::codeForPaisId($paisTel);
        $digits = CountryPhoneHelper::digits($this->input('whatsapp'));
        $national = CountryPhoneHelper::nationalNumber($digits);
        if ($national === '') {
            $national = $digits;
        }

        $this->merge([
            'whatsapp' => $national,
            'whatsapp_prefix' => $code ?: null,
            'tipo_documento' => strtoupper((string) ($this->input('tipo_documento') ?: 'DNI')),
            'dni' => trim((string) $this->input('dni')),
        ]);
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
            'dni.required' => 'El documento de identidad es requerido',
            'tipo_documento.required' => 'El tipo de documento es requerido',
            'tipo_documento.in' => 'El tipo de documento debe ser DNI o ID',
            'provincia_id.integer' => 'La provincia debe ser un número válido',
            'provincia_id.exists' => 'La provincia seleccionada no es válida',
            'departamento_id.integer' => 'El departamento debe ser un número válido',
            'departamento_id.exists' => 'El departamento seleccionado no es válido',
            'distrito_id.integer' => 'El distrito debe ser un número válido',
            'distrito_id.exists' => 'El distrito seleccionado no es válido',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
