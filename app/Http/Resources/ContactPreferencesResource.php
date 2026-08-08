<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only ever returned to the owning user -- it describes who may see their
 * contact details, so it is never embedded in another user's profile payload.
 *
 * @mixin \App\Models\ContactSharingPreference
 */
class ContactPreferencesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'phone_visibility' => $this->phone_visibility?->value,
            'whatsapp_visibility' => $this->whatsapp_visibility?->value,
            'email_visibility' => $this->email_visibility?->value,
            'distance_visibility' => $this->distance_visibility,
            'whatsapp_number' => $this->whatsapp_number,
        ];
    }
}
