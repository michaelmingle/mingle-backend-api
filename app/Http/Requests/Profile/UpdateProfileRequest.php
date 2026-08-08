<?php

namespace App\Http\Requests\Profile;

use App\Enums\LookingFor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'professional_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'looking_for' => ['sometimes', 'nullable', 'array'],
            'looking_for.*' => [Rule::in(LookingFor::values())],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'github_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /** Payload limited to profile columns -- `name` lives on the user record. */
    public function profileAttributes(): array
    {
        return $this->safe()->except('name');
    }
}
