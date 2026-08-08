<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class JoinEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_networking_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
