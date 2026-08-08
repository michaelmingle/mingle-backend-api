<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'avatar_url' => $this->avatar_url,
            'professional_title' => $this->professional_title,
            'bio' => $this->bio,
            'industry' => $this->industry,
            'location_text' => $this->location_text,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_discoverable' => $this->is_discoverable,
            'discovery_radius_meters' => $this->discovery_radius_meters,
            'looking_for' => $this->looking_for ?? [],
            'portfolio_url' => $this->portfolio_url,
            'linkedin_url' => $this->linkedin_url,
            'github_url' => $this->github_url,
            'website_url' => $this->website_url,
            'profile_completion_percent' => $this->profile_completion_percent,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
