<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_viewer_can_open_incidents_but_cannot_create_one(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'incident-host']);
        Incident::factory()->forHost($host)->create(['title' => 'visible incident']);

        $this->actingAsMember($viewer, $organization)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('visible incident');

        $this->actingAsMember($viewer, $organization)
            ->get(route('incidents.create'))
            ->assertForbidden();

        $this->actingAsMember($viewer, $organization)
            ->post(route('incidents.store'), [
                'title' => 'Forbidden incident',
                'severity' => 'high',
            ])
            ->assertForbidden();
    }

    public function test_viewer_cannot_comment_or_update_an_incident(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $incident = Incident::factory()->forHost($host)->create();

        $this->actingAsMember($viewer, $organization)
            ->post(route('incidents.comment', $incident), [
                'message' => 'Viewer should not comment.',
            ])
            ->assertForbidden();

        $this->actingAsMember($viewer, $organization)
            ->put(route('incidents.update', $incident), [
                'status' => 'investigating',
                'priority' => 'p2',
                'severity' => 'high',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('incident_events', [
            'incident_id' => $incident->id,
            'message' => 'Viewer should not comment.',
        ]);
    }

    public function test_viewer_cannot_promote_an_alert(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $alert = Alert::factory()->forHost($host)->create();

        $this->actingAsMember($viewer, $organization)
            ->post(route('alerts.incident', $alert))
            ->assertForbidden();

        $this->assertSame(0, Incident::query()->withoutGlobalScopes()->count());
        $this->assertNull($alert->fresh()->incident_id);
    }

    public function test_operator_can_create_and_comment(): void
    {
        $organization = Organization::factory()->create();
        $operator = $this->createMember(RoleName::Operator, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);

        $response = $this->actingAsMember($operator, $organization)
            ->post(route('incidents.store'), [
                'title' => 'Operator opened this',
                'severity' => 'medium',
                'host_id' => $host->id,
            ]);

        $incident = Incident::query()->withoutGlobalScopes()->where('title', 'Operator opened this')->first();

        $this->assertNotNull($incident);
        $response->assertRedirect(route('incidents.show', $incident));

        $this->actingAsMember($operator, $organization)
            ->post(route('incidents.comment', $incident), [
                'message' => 'Looking at metrics now.',
            ])
            ->assertRedirect();
    }

    public function test_guest_is_redirected_from_incidents(): void
    {
        $this->get(route('incidents.index'))->assertRedirect(route('login'));
    }
}
