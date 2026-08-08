<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class IndexEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'upcoming' => ['sometimes', 'boolean'],
            'mine' => ['sometimes', 'boolean'],
            'attending' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
