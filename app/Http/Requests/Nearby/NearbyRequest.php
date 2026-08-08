<?php

namespace App\Http\Requests\Nearby;

use App\Services\DiscoveryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NearbyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'filter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'looking_for' => ['sometimes', 'nullable', 'string', 'max:64'],
            'skill' => ['sometimes', 'nullable', 'string', 'max:255'],
            'radius_meters' => ['sometimes', 'integer', 'min:50', 'max:'.(int) config('mingle.discovery.max_radius_meters')],
            'sort' => ['sometimes', Rule::in(DiscoveryService::SORTS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->only(['filter', 'industry', 'profession', 'looking_for', 'skill', 'radius_meters']);
    }
}
