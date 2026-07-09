<?php

namespace App\Http\Requests\Fabricante;

use Illuminate\Foundation\Http\FormRequest;

class FabricanteFcmTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:4096'],
        ];
    }
}
