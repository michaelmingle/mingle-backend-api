<?php

namespace App\Http\Requests\Connections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConnectionsRequest extends FormRequest
{
    public const TABS = ['all', 'recent', 'events', 'favorites'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tab' => ['sometimes', Rule::in(self::TABS)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
