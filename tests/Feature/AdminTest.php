<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_refused_across_the_admin_surface(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/reports')->assertStatus(403);
        $this->getJson('/api/admin/analytics/summary')->assertStatus(403);
    }

    public function test_an_admin_can_list_and_filter_users(): void
    {
        $this->actingAsUser($this->makeUser(['is_admin' => true]));
        $this->makeUser(['name' => 'Regular Rita', 'email' => 'rita@example.com']);
        $this->makeUser(['name' => 'Suspended Sam', 'status' => 'suspended']);

        $this->assertCount(3, $this->getJson('/api/admin/users')->assertOk()->json('data.items'));

        $filtered = $this->getJson('/api/admin/users?status=suspended')->json('data.items');
        $this->assertSame('Suspended Sam', $filtered[0]['name']);

        $searched = $this->getJson('/api/admin/users?search=rita@example.com')->json('data.items');
        $this->assertSame('Regular Rita', $searched[0]['name']);
    }

    public function test_an_admin_can_grant_and_revoke_the_verification_badge(): void
    {
        $this->actingAsUser($this->makeUser(['is_admin' => true]));
        $target = $this->makeUser();

        $this->putJson("/api/admin/users/{$target->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.is_verified', true);

        $this->putJson("/api/admin/users/{$target->id}/verify", ['value' => false])
            ->assertOk()
            ->assertJsonPath('data.is_verified', false);
    }

    public function test_suspending_a_user_revokes_their_tokens_and_hides_them(): void
    {
        $this->actingAsUser($this->makeUser(['is_admin' => true]));

        $target = $this->makeUser(['email' => 'target@example.com'], ['is_discoverable' => true]);
        $target->createToken('mobile');

        $this->putJson("/api/admin/users/{$target->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertSame(0, $target->fresh()->tokens()->count());
        $this->assertFalse($target->fresh()->profile->is_discoverable);

        // And the restriction can be lifted again.
        $this->putJson("/api/admin/users/{$target->id}/suspend", ['value' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_an_admin_can_ban_a_user(): void
    {
        $this->actingAsUser($this->makeUser(['is_admin' => true]));
        $target = $this->makeUser();

        $this->putJson("/api/admin/users/{$target->id}/ban")
            ->assertOk()
            ->assertJsonPath('data.status', 'banned');
    }

    public function test_an_admin_can_triage_reports(): void
    {
        $admin = $this->makeUser(['is_admin' => true]);
        $reporter = $this->makeUser();
        $target = $this->makeUser();

        $this->actingAsUser($reporter);
        $this->postJson("/api/users/{$target->id}/report", ['reason' => 'spam'])->assertCreated();

        $this->actingAsUser($admin);
        $reports = $this->getJson('/api/admin/reports')->assertOk()->json('data.items');
        $this->assertCount(1, $reports);
        $this->assertSame('spam', $reports[0]['reason']);

        $this->putJson("/api/admin/reports/{$reports[0]['id']}", ['status' => 'actioned'])
            ->assertOk()
            ->assertJsonPath('data.status', 'actioned');

        $this->assertDatabaseHas('reports', ['id' => $reports[0]['id'], 'status' => 'actioned']);
        $this->assertCount(0, $this->getJson('/api/admin/reports?status=pending')->json('data.items'));
    }

    public function test_report_status_is_validated(): void
    {
        $this->actingAsUser($this->makeUser(['is_admin' => true]));
        $report = Report::create([
            'reporter_id' => $this->makeUser()->id,
            'reported_user_id' => $this->makeUser()->id,
            'reason' => 'spam',
        ]);

        $this->putJson("/api/admin/reports/{$report->id}", ['status' => 'nonsense'])->assertStatus(422);
    }

    public function test_analytics_summary_reports_totals_and_acceptance_rate(): void
    {
        $admin = $this->makeUser(['is_admin' => true]);
        $a = $this->makeUser();
        $b = $this->makeUser();
        $c = $this->makeUser();

        Event::factory()->create(['organizer_id' => $admin->id]);

        // One accepted request and one declined -> a 50% acceptance rate.
        $this->actingAsUser($a);
        $accepted = $this->postJson("/api/users/{$b->id}/connect")->json('data.id');
        $declined = $this->postJson("/api/users/{$c->id}/connect")->json('data.id');

        $this->actingAsUser($b);
        $this->postJson("/api/connections/requests/{$accepted}/accept")->assertOk();

        $this->actingAsUser($c);
        $this->postJson("/api/connections/requests/{$declined}/decline")->assertOk();

        $this->actingAsUser($admin);
        $summary = $this->getJson('/api/admin/analytics/summary')->assertOk()->json('data');

        $this->assertSame(4, $summary['total_users']);
        $this->assertSame(1, $summary['total_connections']);
        $this->assertSame(1, $summary['total_events']);
        $this->assertSame(0.5, $summary['connection_acceptance_rate']);
        $this->assertArrayHasKey('active_users_7d', $summary);
        $this->assertArrayHasKey('pending_reports', $summary);
    }

    public function test_acceptance_rate_is_null_when_nothing_has_been_handled(): void
    {
        $this->actingAsUser($this->makeUser(['is_admin' => true]));

        $this->assertNull(
            $this->getJson('/api/admin/analytics/summary')->assertOk()->json('data.connection_acceptance_rate')
        );
    }
}
