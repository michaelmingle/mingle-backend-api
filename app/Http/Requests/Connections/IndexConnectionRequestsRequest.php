<?php

namespace App\Http\Requests\Connections;

use App\Enums\ConnectionRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConnectionRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'direction' => ['sometimes', Rule::in(['incoming', 'outgoing'])],
            'status' => ['sometimes', Rule::in(ConnectionRequestStatus::values())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
