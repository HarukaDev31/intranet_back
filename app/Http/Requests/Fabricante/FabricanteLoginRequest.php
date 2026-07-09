<?php

namespace App\Http\Requests\Fabricante;

use Illuminate\Foundation\Http\FormRequest;

class FabricanteLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:128'],
            'platform' => ['required', 'in:ios,android,web'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
