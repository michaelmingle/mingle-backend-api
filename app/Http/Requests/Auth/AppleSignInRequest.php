<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AppleSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identity_token' => ['required', 'string'],
            // Apple hands the display name to the client only on the very
            // first authorization -- it's never in the token itself.
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
