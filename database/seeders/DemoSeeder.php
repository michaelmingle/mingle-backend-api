<?php

namespace Database\Seeders;

use App\Enums\ContactVisibility;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Event;
use App\Models\Interest;
use App\Models\Skill;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Demo dataset for local development and QA: an admin, a handful of members
 * clustered around a single venue, two events and a couple of connections.
 *
 * Deliberately NOT part of DatabaseSeeder, and it refuses to run in production.
 * Run it with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** Accra, Ghana -- the demo venue everyone is clustered around. */
    private const VENUE_LAT = 5.6037;

    private const VENUE_LNG = -0.1870;

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command?->warn('DemoSeeder skipped: refusing to seed demo data in production.');

            return;
        }

        $this->call([SkillSeeder::class, InterestSeeder::class, PlanSeeder::class]);

        $profiles = app(ProfileService::class);

        $admin = $this->makeUser($profiles, 'Mingle Admin', 'admin@mingle.test', [
            'is_admin' => true,
            'is_verified' => true,
        ], [
            'professional_title' => 'Platform Operations',
            'industry' => 'Technology',
            'location_text' => 'Accra, Ghana',
        ]);

        $people = [
            ['Ama Boateng', 'ama@mingle.test', 'Senior Laravel Engineer', 'Technology', ['Laravel', 'PHP', 'AWS'], ['Technology', 'Startups'], ['networking', 'collaboration']],
            ['Kwame Mensah', 'kwame@mingle.test', 'Product Manager', 'Technology', ['Product Management', 'Analytics'], ['Startups', 'Entrepreneurship'], ['business', 'mentorship']],
            ['Nadia Osei', 'nadia@mingle.test', 'UI/UX Designer', 'Design', ['UI/UX Design', 'Product Design'], ['Design', 'Photography'], ['clients', 'collaboration']],
            ['Tunde Ade', 'tunde@mingle.test', 'Data Scientist', 'Technology', ['Data Science', 'Python'], ['Technology', 'Investing'], ['employment', 'networking']],
            ['Lena Fischer', 'lena@mingle.test', 'Growth Marketer', 'Media', ['Marketing', 'Growth'], ['Entrepreneurship', 'Travel'], ['clients', 'business']],
            ['Sam Adjei', 'sam@mingle.test', 'Founder', 'Finance', ['Business Development', 'Finance'], ['Investing', 'Startups'], ['mentorship', 'business']],
        ];

        $created = [];

        foreach ($people as $index => [$name, $email, $title, $industry, $skills, $interests, $lookingFor]) {
            $user = $this->makeUser($profiles, $name, $email, [
                'is_verified' => $index < 2,
                'is_premium' => $index === 0,
            ], [
                'professional_title' => $title,
                'industry' => $industry,
                'location_text' => 'Accra, Ghana',
                'bio' => $title.' based in Accra. Always up for a good conversation.',
                'is_discoverable' => true,
                // Spread the group over a few hundred metres around the venue.
                'latitude' => self::VENUE_LAT + ($index * 0.0009),
                'longitude' => self::VENUE_LNG + ($index * 0.0007),
                'discovery_radius_meters' => 2000,
                'looking_for' => $lookingFor,
            ]);

            $user->skills()->sync(Skill::whereIn('name', $skills)->pluck('id'));
            $user->interests()->sync(Interest::whereIn('name', $interests)->pluck('id'));
            $user->profile->recalculateCompletion();

            $created[] = $user;
        }

        // One member shares contact details with connections, one with everyone,
        // so the privacy rules are visible in the demo data.
        $created[0]->contactPreferences->update([
            'email_visibility' => ContactVisibility::Connections,
            'phone_visibility' => ContactVisibility::Connections,
        ]);
        $created[1]->contactPreferences->update([
            'email_visibility' => ContactVisibility::Everyone,
            'whatsapp_visibility' => ContactVisibility::Everyone,
            'whatsapp_number' => '+233200000000',
        ]);

        $summit = Event::create([
            'organizer_id' => $admin->id,
            'title' => 'Accra Tech Summit 2026',
            'description' => 'The largest gathering of builders in West Africa.',
            'location_text' => 'Accra International Conference Centre',
            'latitude' => self::VENUE_LAT,
            'longitude' => self::VENUE_LNG,
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addDays(2),
            'category' => 'Conference',
        ]);

        $meetup = Event::create([
            'organizer_id' => $created[1]->id,
            'title' => 'Founders Coffee Meetup',
            'description' => 'Informal monthly meetup for early-stage founders.',
            'location_text' => 'Osu, Accra',
            'latitude' => self::VENUE_LAT + 0.004,
            'longitude' => self::VENUE_LNG + 0.003,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
            'category' => 'Meetup',
        ]);

        foreach ($created as $index => $user) {
            $summit->eventAttendees()->create([
                'user_id' => $user->id,
                // One attendee opts out of networking to exercise the filter.
                'is_networking_enabled' => $index !== 4,
                'joined_at' => now(),
            ]);
        }

        foreach (array_slice($created, 0, 3) as $user) {
            $meetup->eventAttendees()->create([
                'user_id' => $user->id,
                'is_networking_enabled' => true,
                'joined_at' => now(),
            ]);
        }

        $summit->refreshAttendeeCount();
        $meetup->refreshAttendeeCount();

        // An accepted connection made at the summit, plus one request still pending.
        $this->connect($created[0], $created[1], $summit->id);

        ConnectionRequest::create([
            'sender_id' => $created[2]->id,
            'receiver_id' => $created[0]->id,
            'message' => 'Loved your talk -- would be great to swap notes on design systems.',
            'event_id' => $summit->id,
        ]);

        $this->command?->info('Demo data seeded. All demo accounts use the password "password".');
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $profileAttributes
     */
    private function makeUser(
        ProfileService $profiles,
        string $name,
        string $email,
        array $userAttributes = [],
        array $profileAttributes = [],
    ): User {
        $user = User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'name' => $name,
                'password' => 'password',
                'auth_provider' => 'email',
                'email_verified_at' => now(),
                'last_active_at' => now(),
            ], $userAttributes)
        );

        $profiles->bootstrapFor($user);
        $user->profile->fill($profileAttributes)->save();
        $user->profile->recalculateCompletion();

        return $user->refresh();
    }

    private function connect(User $a, User $b, ?int $eventId = null): Connection
    {
        [$one, $two] = Connection::canonicalPair($a->id, $b->id);

        return Connection::firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
            ['event_id' => $eventId, 'connected_at' => now()],
        );
    }
}
