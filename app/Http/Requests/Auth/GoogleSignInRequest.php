<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
