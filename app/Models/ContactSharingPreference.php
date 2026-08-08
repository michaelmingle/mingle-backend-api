<?php

namespace App\Models;

use App\Enums\ContactVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSharingPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone_visibility',
        'whatsapp_visibility',
        'email_visibility',
        'distance_visibility',
        'whatsapp_number',
    ];

    protected function casts(): array
    {
        return [
            'phone_visibility' => ContactVisibility::class,
            'whatsapp_visibility' => ContactVisibility::class,
            'email_visibility' => ContactVisibility::class,
            'distance_visibility' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether a field with the given visibility should be revealed to a viewer.
     *
     * @param  bool  $isConnected  whether the viewer is an accepted connection
     */
    public function allows(ContactVisibility $visibility, bool $isConnected): bool
    {
        return match ($visibility) {
            ContactVisibility::Everyone => true,
            ContactVisibility::Connections => $isConnected,
            ContactVisibility::Hidden => false,
        };
    }
}
