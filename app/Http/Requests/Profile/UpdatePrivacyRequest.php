<?php

namespace App\Http\Requests\Profile;

use App\Enums\ContactVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $visibility = ['sometimes', Rule::in(ContactVisibility::values())];

        return [
            'phone_visibility' => $visibility,
            'whatsapp_visibility' => $visibility,
            'email_visibility' => $visibility,
            'distance_visibility' => ['sometimes', 'boolean'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
