<?php

namespace App\Http\Requests\Fabricante;

use Illuminate\Foundation\Http\FormRequest;

class FabricanteVerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string', 'size:64'],
        ];
    }
}
