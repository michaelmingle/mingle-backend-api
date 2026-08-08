<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user's own representation. Contact fields are unconditional
 * here because the viewer *is* the subject; other people's profiles go through
 * PublicUserResource, which honours contact_sharing_preferences.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'auth_provider' => $this->auth_provider,
            'is_admin' => (bool) $this->is_admin,
            'is_verified' => (bool) $this->is_verified,
            'is_premium' => (bool) $this->is_premium,
            'status' => $this->status?->value,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'contact_preferences' => new ContactPreferencesResource($this->whenLoaded('contactPreferences')),
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'interests' => InterestResource::collection($this->whenLoaded('interests')),
        ];
    }
}
