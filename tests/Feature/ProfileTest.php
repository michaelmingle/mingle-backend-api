<?php

namespace Tests\Feature;

use App\Models\Interest;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_be_updated(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/profile', [
            'professional_title' => 'Backend Engineer',
            'bio' => 'I build APIs.',
            'industry' => 'Technology',
            'location_text' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'looking_for' => ['networking', 'mentorship'],
            'linkedin_url' => 'https://linkedin.com/in/example',
        ])->assertOk()
            ->assertJsonPath('data.profile.professional_title', 'Backend Engineer')
            ->assertJsonPath('data.profile.looking_for', ['networking', 'mentorship']);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'industry' => 'Technology',
        ]);
    }

    public function test_profile_update_rejects_unknown_looking_for_values(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/profile', ['looking_for' => ['dating']])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_profile_update_rejects_out_of_range_coordinates(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/profile', ['latitude' => 200])->assertStatus(422);
    }

    public function test_completion_percentage_rises_as_the_profile_is_filled_in(): void
    {
        $user = $this->actingAsUser();

        $initial = $this->getJson('/api/profile/completion')->assertOk();
        $this->assertSame(0, $initial->json('data.percent'));
        $this->assertContains('bio', $initial->json('data.missing_fields'));

        $this->putJson('/api/profile', [
            'professional_title' => 'Backend Engineer',
            'bio' => 'I build APIs.',
            'industry' => 'Technology',
            'location_text' => 'Accra',
            'looking_for' => ['networking'],
        ])->assertOk();

        $skill = Skill::factory()->create();
        $interest = Interest::factory()->create();

        $this->putJson('/api/profile/skills', ['skill_ids' => [$skill->id]])->assertOk();
        $this->putJson('/api/profile/interests', ['interest_ids' => [$interest->id]])->assertOk();

        $after = $this->getJson('/api/profile/completion')->assertOk();

        // Everything except the avatar is now set: 7 of 8 weighted fields.
        $this->assertSame(88, $after->json('data.percent'));
        $this->assertSame(['avatar_url'], $after->json('data.missing_fields'));

        // And the cached column is kept in step with the computed value.
        $this->assertSame(88, $user->fresh()->profile->profile_completion_percent);
    }

    public function test_skills_and_interests_are_synced_not_appended(): void
    {
        $user = $this->actingAsUser();
        $skills = Skill::factory()->count(3)->create();

        $this->putJson('/api/profile/skills', ['skill_ids' => $skills->pluck('id')->all()])->assertOk();
        $this->assertCount(3, $user->fresh()->skills);

        $this->putJson('/api/profile/skills', ['skill_ids' => [$skills->first()->id]])->assertOk();
        $this->assertCount(1, $user->fresh()->skills);
    }

    public function test_skill_ids_must_exist(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/profile/skills', ['skill_ids' => [9999]])->assertStatus(422);
    }

    public function test_privacy_preferences_can_be_updated(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/profile/privacy', [
            'phone_visibility' => 'connections',
            'email_visibility' => 'everyone',
            'distance_visibility' => false,
            'whatsapp_number' => '+233200000000',
        ])->assertOk()
            ->assertJsonPath('data.phone_visibility', 'connections')
            ->assertJsonPath('data.distance_visibility', false);

        $this->assertDatabaseHas('contact_sharing_preferences', [
            'user_id' => $user->id,
            'email_visibility' => 'everyone',
        ]);
    }

    public function test_privacy_rejects_unknown_visibility(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/profile/privacy', ['phone_visibility' => 'public'])->assertStatus(422);
    }

    public function test_avatar_upload_stores_the_file_and_returns_a_url(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUser();

        $this->postJson('/api/profile/photo', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertOk()->assertJsonStructure(['data' => ['avatar_url']]);

        $this->assertCount(1, Storage::disk('public')->files('avatars'));
        $this->assertNotNull($user->fresh()->profile->avatar_url);
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $this->postJson('/api/profile/photo', [
            'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }
}
