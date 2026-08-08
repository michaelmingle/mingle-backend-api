<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Administrative view of a user -- moderation state, never credentials. */
/** @mixin \App\Models\User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value,
            'is_admin' => (bool) $this->is_admin,
            'is_verified' => (bool) $this->is_verified,
            'is_premium' => (bool) $this->is_premium,
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
        ];
    }
}
