<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\IncidentEventType;
use App\Enums\IncidentStatus;
use App\Enums\RoleName;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_opening_an_incident_records_a_created_event(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);

        $response = $this->actingAsMember($admin, $organization)
            ->post(route('incidents.store'), [
                'title' => 'Disk filling on database host',
                'description' => 'Disk usage crossed the alert threshold.',
                'severity' => 'high',
                'priority' => 'p2',
                'host_id' => $host->id,
                'assigned_to' => $admin->id,
            ]);

        $incident = Incident::query()->withoutGlobalScopes()->where('title', 'Disk filling on database host')->first();

        $this->assertNotNull($incident);
        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame($organization->id, $incident->organization_id);
        $this->assertSame($host->id, $incident->host_id);
        $this->assertSame($admin->id, $incident->assigned_to);
        $this->assertDatabaseHas('incident_events', [
            'incident_id' => $incident->id,
            'type' => IncidentEventType::Created->value,
        ]);
        $this->assertDatabaseHas('incident_events', [
            'incident_id' => $incident->id,
            'type' => IncidentEventType::Assigned->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::IncidentCreated->value,
            'resource_id' => $incident->id,
        ]);
    }

    public function test_title_is_required_to_open_an_incident(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->from(route('incidents.create'))
            ->post(route('incidents.store'), [
                'severity' => 'high',
            ])
            ->assertRedirect(route('incidents.create'))
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Incident::query()->withoutGlobalScopes()->count());
    }

    public function test_status_cannot_move_backward(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $incident = Incident::factory()->forHost($host)->create([
            'status' => IncidentStatus::Investigating,
        ]);

        $this->actingAsMember($admin, $organization)
            ->from(route('incidents.show', $incident))
            ->put(route('incidents.update', $incident), [
                'status' => 'open',
                'priority' => 'p2',
                'severity' => 'high',
            ])
            ->assertRedirect(route('incidents.show', $incident))
            ->assertSessionHasErrors([
                'status' => 'Incidents cannot move backward from Investigating.',
            ]);

        $this->assertSame(IncidentStatus::Investigating, $incident->fresh()->status);
    }

    public function test_resolving_an_incident_sets_resolved_at_and_audits(): void
    {
        $this->freezeTime();

        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $incident = Incident::factory()->forHost($host)->create([
            'status' => IncidentStatus::Investigating,
        ]);

        $this->actingAsMember($admin, $organization)
            ->put(route('incidents.update', $incident), [
                'status' => 'resolved',
                'priority' => 'p2',
                'severity' => 'high',
                'root_cause' => 'Disk filled with binary logs.',
                'resolution' => 'Rotated logs and expanded the volume.',
            ])
            ->assertRedirect();

        $incident->refresh();

        $this->assertSame(IncidentStatus::Resolved, $incident->status);
        $this->assertSame(now()->timestamp, $incident->resolved_at?->timestamp);
        $this->assertSame('Disk filled with binary logs.', $incident->root_cause);
        $this->assertDatabaseHas('incident_events', [
            'incident_id' => $incident->id,
            'type' => IncidentEventType::StatusChanged->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::IncidentResolved->value,
            'resource_id' => $incident->id,
        ]);
    }

    public function test_comment_appends_to_the_timeline(): void
    {
        $organization = Organization::factory()->create();
        $operator = $this->createMember(RoleName::Operator, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $incident = Incident::factory()->forHost($host)->create();

        $this->actingAsMember($operator, $organization)
            ->post(route('incidents.comment', $incident), [
                'message' => 'Checking disk on db-01.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('incident_events', [
            'incident_id' => $incident->id,
            'type' => IncidentEventType::Comment->value,
            'message' => 'Checking disk on db-01.',
            'user_id' => $operator->id,
        ]);
    }

    public function test_closed_incident_rejects_comments_and_updates(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $incident = Incident::factory()->forHost($host)->create([
            'status' => IncidentStatus::Closed,
            'closed_at' => now(),
            'resolved_at' => now(),
        ]);

        $this->actingAsMember($admin, $organization)
            ->from(route('incidents.show', $incident))
            ->post(route('incidents.comment', $incident), [
                'message' => 'Too late.',
            ])
            ->assertRedirect(route('incidents.show', $incident))
            ->assertSessionHasErrors([
                'message' => 'A closed incident cannot receive comments.',
            ]);

        $this->actingAsMember($admin, $organization)
            ->from(route('incidents.show', $incident))
            ->put(route('incidents.update', $incident), [
                'status' => 'closed',
                'priority' => 'p2',
                'severity' => 'high',
                'resolution' => 'Should not save.',
            ])
            ->assertRedirect(route('incidents.show', $incident))
            ->assertSessionHasErrors([
                'status' => 'A closed incident cannot be updated.',
            ]);

        $this->assertSame(0, IncidentEvent::query()->withoutGlobalScopes()->where('incident_id', $incident->id)->count());
        $this->assertNull($incident->fresh()->resolution);
    }

    public function test_promoting_an_alert_links_it_once(): void
    {
        $organization = Organization::factory()->create();
        $operator = $this->createMember(RoleName::Operator, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'db-01']);
        $alert = Alert::factory()->forHost($host)->create([
            'title' => 'db-01: Disk high',
        ]);

        $first = $this->actingAsMember($operator, $organization)
            ->post(route('alerts.incident', $alert));

        $incident = Incident::query()->withoutGlobalScopes()->where('title', 'db-01: Disk high')->first();

        $this->assertNotNull($incident);
        $first->assertRedirect(route('incidents.show', $incident));
        $this->assertSame($incident->id, $alert->fresh()->incident_id);
        $this->assertDatabaseHas('incident_events', [
            'incident_id' => $incident->id,
            'type' => IncidentEventType::AlertLinked->value,
        ]);

        $this->actingAsMember($operator, $organization)
            ->post(route('alerts.incident', $alert))
            ->assertRedirect(route('incidents.show', $incident));

        $this->assertSame(1, Incident::query()->withoutGlobalScopes()->count());
    }

    public function test_incident_show_escapes_title(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $incident = Incident::factory()->forHost($host)->create([
            'title' => "<script>alert('xss')</script>",
        ]);

        $content = $this->actingAsMember($admin, $organization)
            ->get(route('incidents.show', $incident))
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $content);
    }
}
