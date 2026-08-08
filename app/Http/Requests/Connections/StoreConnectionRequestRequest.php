<?php

namespace App\Http\Requests\Connections;

use Illuminate\Foundation\Http\FormRequest;

class StoreConnectionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'event_id' => ['sometimes', 'nullable', 'integer', 'exists:events,id'],
        ];
    }
}
