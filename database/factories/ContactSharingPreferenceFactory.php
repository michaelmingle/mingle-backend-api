<?php

namespace Database\Factories;

use App\Enums\ContactVisibility;
use App\Models\ContactSharingPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactSharingPreference> */
class ContactSharingPreferenceFactory extends Factory
{
    protected $model = ContactSharingPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone_visibility' => ContactVisibility::Hidden,
            'whatsapp_visibility' => ContactVisibility::Hidden,
            'email_visibility' => ContactVisibility::Hidden,
            'distance_visibility' => true,
            'whatsapp_number' => null,
        ];
    }

    public function openToEveryone(): static
    {
        return $this->state(fn () => [
            'phone_visibility' => ContactVisibility::Everyone,
            'whatsapp_visibility' => ContactVisibility::Everyone,
            'email_visibility' => ContactVisibility::Everyone,
        ]);
    }

    public function connectionsOnly(): static
    {
        return $this->state(fn () => [
            'phone_visibility' => ContactVisibility::Connections,
            'whatsapp_visibility' => ContactVisibility::Connections,
            'email_visibility' => ContactVisibility::Connections,
        ]);
    }
}
