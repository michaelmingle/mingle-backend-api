<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'interest_ids' => ['present', 'array', 'max:50'],
            'interest_ids.*' => ['integer', 'exists:interests,id'],
        ];
    }
}
