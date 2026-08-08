<?php

namespace App\Http\Resources;

use App\Models\ContactSharingPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Another user's profile as seen by the current viewer.
 *
 * Contact details (email / phone / WhatsApp) are only included when the subject's
 * contact_sharing_preferences allow it for this viewer, and distance is dropped
 * when they have turned distance sharing off. The viewer context is set
 * explicitly rather than inferred inside the resource so list endpoints can
 * resolve connectedness once, in bulk, instead of per row.
 *
 * @mixin User
 */
class PublicUserResource extends JsonResource
{
    private bool $isConnected = false;

    private ?float $distanceMeters = null;

    /** @var array<int, string> */
    private array $sharedInterests = [];

    private ?bool $hasPendingRequest = null;

    private bool $includeContact = true;

    /**
     * Drops the contact block entirely. List surfaces (/nearby, event
     * attendees, search) use this: proximity feeds broadcast to strangers, so
     * contact details are only ever revealed on a deliberate profile view even
     * when the subject set visibility to "everyone".
     */
    public function withoutContact(): static
    {
        $this->includeContact = false;

        return $this;
    }

    public function connected(bool $isConnected): static
    {
        $this->isConnected = $isConnected;

        return $this;
    }

    public function distance(?float $meters): static
    {
        $this->distanceMeters = $meters;

        return $this;
    }

    /** @param  array<int, string>  $names */
    public function sharedInterests(array $names): static
    {
        $this->sharedInterests = array_values($names);

        return $this;
    }

    public function pendingRequest(?bool $pending): static
    {
        $this->hasPendingRequest = $pending;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $prefs = $user->relationLoaded('contactPreferences')
            ? $user->contactPreferences
            : $user->contactPreferences()->first();

        $profile = $user->relationLoaded('profile') ? $user->profile : $user->profile()->first();

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'is_verified' => (bool) $user->is_verified,
            'is_premium' => (bool) $user->is_premium,
            'avatar_url' => $profile?->avatar_url,
            'professional_title' => $profile?->professional_title,
            'bio' => $profile?->bio,
            'industry' => $profile?->industry,
            'location_text' => $profile?->location_text,
            'looking_for' => $profile?->looking_for ?? [],
            'portfolio_url' => $profile?->portfolio_url,
            'linkedin_url' => $profile?->linkedin_url,
            'github_url' => $profile?->github_url,
            'website_url' => $profile?->website_url,
            'shared_interests' => $this->sharedInterests,
            'distance_meters' => $this->visibleDistance($prefs),
            'is_connected' => $this->isConnected,
            'last_active_at' => $user->last_active_at?->toIso8601String(),
        ];

        if ($this->includeContact) {
            $payload['contact'] = $this->contactPayload($user, $prefs);
        }

        if ($user->relationLoaded('skills')) {
            $payload['skills'] = SkillResource::collection($user->skills);
        }

        if ($user->relationLoaded('interests')) {
            $payload['interests'] = InterestResource::collection($user->interests);
        }

        if ($this->hasPendingRequest !== null) {
            $payload['has_pending_request'] = $this->hasPendingRequest;
        }

        return $payload;
    }

    private function visibleDistance(?ContactSharingPreference $prefs): ?float
    {
        if ($this->distanceMeters === null) {
            return null;
        }

        if ($prefs && ! $prefs->distance_visibility) {
            return null;
        }

        return round($this->distanceMeters, 1);
    }

    /** @return array<string, string> */
    private function contactPayload(User $user, ?ContactSharingPreference $prefs): array
    {
        if (! $prefs) {
            return [];
        }

        return array_filter([
            'email' => $prefs->allows($prefs->email_visibility, $this->isConnected) ? $user->email : null,
            'phone' => $prefs->allows($prefs->phone_visibility, $this->isConnected) ? $user->phone : null,
            'whatsapp_number' => $prefs->allows($prefs->whatsapp_visibility, $this->isConnected) ? $prefs->whatsapp_number : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
