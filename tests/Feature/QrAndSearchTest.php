<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Skill;
use App\Notifications\QrConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QrAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_token_is_created_lazily_and_reused(): void
    {
        $user = $this->actingAsUser();

        $first = $this->getJson('/api/qr/me')->assertOk()->json('data');

        $this->assertNotEmpty($first['token']);
        $this->assertStringContainsString($first['token'], $first['deep_link']);
        // The payload is an opaque token, never the raw primary key.
        $this->assertNotSame((string) $user->id, $first['token']);
        $this->assertSame(40, strlen($first['token']));

        $second = $this->getJson('/api/qr/me')->assertOk()->json('data.token');

        $this->assertSame($first['token'], $second);
        $this->assertDatabaseCount('qr_tokens', 1);
    }

    public function test_rotating_the_qr_token_invalidates_the_previous_one(): void
    {
        $scanned = $this->makeUser();

        $this->actingAsUser($scanned);
        $old = $this->getJson('/api/qr/me')->json('data.token');
        $new = $this->postJson('/api/qr/rotate')->assertCreated()->json('data.token');

        $this->assertNotSame($old, $new);

        $this->actingAsUser();
        $this->postJson('/api/qr/scan', ['token' => $old])->assertStatus(404);
        $this->postJson('/api/qr/scan', ['token' => $new])->assertOk();
    }

    public function test_scanning_a_token_resolves_the_profile_and_notifies_the_owner(): void
    {
        Notification::fake();

        $owner = $this->makeUser(['name' => 'Code Owner'], ['professional_title' => 'Architect']);

        $this->actingAsUser($owner);
        $token = $this->getJson('/api/qr/me')->json('data.token');

        $scanner = $this->actingAsUser();

        $this->postJson('/api/qr/scan', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.name', 'Code Owner')
            ->assertJsonPath('data.professional_title', 'Architect')
            ->assertJsonPath('data.is_connected', false);

        Notification::assertSentTo($owner, QrConnection::class);
    }

    public function test_scanning_an_unknown_token_returns_a_not_found_envelope(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/qr/scan', ['token' => 'does-not-exist'])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_unified_search_returns_people_events_and_skills(): void
    {
        $this->actingAsUser();

        $this->makeUser(['name' => 'Laravel Larry'], ['professional_title' => 'Backend Engineer']);
        Event::factory()->create(['title' => 'Laravel Conference']);
        Skill::factory()->create(['name' => 'Laravel']);

        $data = $this->getJson('/api/search?q=Laravel')->assertOk()->json('data');

        $this->assertSame('Laravel Larry', $data['people'][0]['name']);
        $this->assertSame('Laravel Conference', $data['events'][0]['title']);
        $this->assertSame('Laravel', $data['skills'][0]['name']);
    }

    public function test_typed_search_is_paginated(): void
    {
        $this->actingAsUser();

        $this->makeUser(['name' => 'Searchable Person']);

        $response = $this->getJson('/api/search?q=Searchable&type=people')->assertOk();

        $this->assertSame('Searchable Person', $response->json('data.items.0.name'));
        $this->assertSame(1, $response->json('data.meta.total'));
    }

    public function test_search_excludes_blocked_users(): void
    {
        $viewer = $this->actingAsUser();
        $blocked = $this->makeUser(['name' => 'Unwanted Contact']);

        $viewer->blocks()->create(['blocked_user_id' => $blocked->id]);

        $this->assertSame([], $this->getJson('/api/search?q=Unwanted&type=people')->json('data.items'));
    }

    public function test_search_requires_a_query(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/search')->assertStatus(422);
    }
}
