<?php

namespace App\Http\Requests\Nearby;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscoverabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_discoverable' => ['required', 'boolean'],
            'discovery_radius_meters' => [
                'sometimes',
                'integer',
                'min:50',
                'max:'.(int) config('mingle.discovery.max_radius_meters'),
            ],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'event_id' => ['sometimes', 'nullable', 'integer', 'exists:events,id'],
        ];
    }
}
