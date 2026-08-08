<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skill_ids' => ['present', 'array', 'max:50'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ];
    }
}
