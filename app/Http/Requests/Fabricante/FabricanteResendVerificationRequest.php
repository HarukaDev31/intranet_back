<?php

namespace App\Http\Requests\Fabricante;

use Illuminate\Foundation\Http\FormRequest;

class FabricanteResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
