<?php

namespace App\Console\Commands;

use App\Models\ConnectionRequest;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\NetworkingSuggestion;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Once a day, gives each discoverable user at most one "someone worth
 * meeting" nudge: the nearest other discoverable person, within their own
 * discovery radius, who shares a skill or interest and isn't already
 * connected, pending, or blocked.
 *
 * Runs at city scale, not global scale: it's one nearby query per
 * discoverable user, which is fine for an MVP's user counts but is the first
 * thing to revisit (e.g. batching by geohash) if this ever needs to run
 * against hundreds of thousands of discoverable profiles.
 */
class SendNetworkingSuggestions extends Command
{
    protected $signature = 'networking:send-suggestions';

    protected $description = 'Suggest one nearby, relevant person per day to each discoverable user';

    /** Don't re-suggest the same pair while an earlier suggestion is still fresh. */
    private const DEDUPE_WINDOW_DAYS = 7;

    public function handle(): int
    {
        $sent = 0;

        Profile::query()
            ->discoverable()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('user.skills', 'user.interests')
            ->chunkById(100, function (Collection $profiles) use (&$sent) {
                foreach ($profiles as $profile) {
                    if ($this->suggestFor($profile)) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} networking suggestion(s).");

        return self::SUCCESS;
    }

    private function suggestFor(Profile $profile): bool
    {
        $user = $profile->user;

        if (! $user) {
            return false;
        }

        $radiusKm = max(0.1, $profile->discovery_radius_meters / 1000);

        $candidates = Profile::query()
            ->discoverableNearby($profile->latitude, $profile->longitude, $radiusKm)
            ->where('profiles.user_id', '!=', $user->id)
            ->with('user.skills', 'user.interests')
            ->limit(25)
            ->get();

        foreach ($candidates as $candidate) {
            $candidateUser = $candidate->user;

            if (! $candidateUser
                || $user->hasBlockRelationshipWith($candidateUser)
                || $user->isConnectedWith($candidateUser)
                || ConnectionRequest::query()->pendingBetween($user->id, $candidateUser->id)->exists()
                || $this->recentlySuggested($user->id, $candidateUser->id)
            ) {
                continue;
            }

            $reasons = $this->sharedReasons($user, $candidateUser);

            if ($reasons === []) {
                continue;
            }

            $user->notify(new NetworkingSuggestion($candidateUser, reasons: $reasons));

            return true;
        }

        return false;
    }

    /** @return array<int, string> */
    private function sharedReasons(User $user, User $candidate): array
    {
        $sharedSkills = $user->skills->pluck('name')->intersect($candidate->skills->pluck('name'));
        $sharedInterests = $user->interests->pluck('name')->intersect($candidate->interests->pluck('name'));

        return $sharedSkills->merge($sharedInterests)->values()->all();
    }

    private function recentlySuggested(int $userId, int $candidateId): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $userId)
            ->where('type', NetworkingSuggestion::class)
            ->where('data->user_id', $candidateId)
            ->where('created_at', '>=', now()->subDays(self::DEDUPE_WINDOW_DAYS))
            ->exists();
    }
}
