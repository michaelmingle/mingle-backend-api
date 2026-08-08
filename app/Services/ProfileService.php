<?php

namespace App\Services;

use App\Models\ContactSharingPreference;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /** Every user gets a profile and a (locked-down by default) preferences row. */
    public function bootstrapFor(User $user): User
    {
        Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'discovery_radius_meters' => (int) config('mingle.discovery.default_radius_meters'),
                'is_discoverable' => false,
                'looking_for' => [],
            ]
        );

        ContactSharingPreference::firstOrCreate(['user_id' => $user->id]);

        return $user->load(['profile', 'contactPreferences']);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Profile $profile, array $attributes): Profile
    {
        $profile->fill($attributes)->save();
        $profile->recalculateCompletion();

        return $profile->refresh();
    }

    /**
     * Stores an avatar on the configured disk and returns its public URL. The
     * disk is config-driven so swapping local -> S3 needs no code change.
     */
    public function storeAvatar(Profile $profile, UploadedFile $file): string
    {
        $disk = (string) config('mingle.uploads.disk');
        $path = $file->store((string) config('mingle.uploads.avatar_path'), $disk);

        $previous = $profile->avatar_url;

        $profile->avatar_url = Storage::disk($disk)->url($path);
        $profile->save();
        $profile->recalculateCompletion();

        $this->deletePrevious($disk, $previous);

        return $profile->avatar_url;
    }

    private function deletePrevious(string $disk, ?string $previousUrl): void
    {
        if (blank($previousUrl)) {
            return;
        }

        $prefix = (string) config('mingle.uploads.avatar_path');
        $position = strpos($previousUrl, $prefix.'/');

        if ($position === false) {
            return;
        }

        $relative = substr($previousUrl, $position);

        if (Storage::disk($disk)->exists($relative)) {
            Storage::disk($disk)->delete($relative);
        }
    }
}
