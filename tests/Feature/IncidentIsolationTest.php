<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\RoleName;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_incident_list_does_not_include_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $acmeHost] = $this->createMonitoredHost($acme, ['hostname' => 'acme-incident-host']);
        ['host' => $globexHost] = $this->createMonitoredHost($globex, ['hostname' => 'globex-incident-host']);

        Incident::factory()->forHost($acmeHost)->create(['title' => 'acme unique incident']);
        Incident::factory()->forHost($globexHost)->create(['title' => 'globex unique incident']);

        $this->actingAsMember($admin, $acme)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('acme unique incident')
            ->assertDontSee('globex unique incident');
    }

    public function test_cross_tenant_incident_show_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreign = Incident::factory()->forHost($foreignHost)->create(['title' => 'secret incident']);

        $this->actingAsMember($admin, $acme)
            ->get(route('incidents.show', $foreign))
            ->assertNotFound();
    }

    public function test_cross_tenant_incident_update_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreign = Incident::factory()->forHost($foreignHost)->create([
            'status' => IncidentStatus::Open,
        ]);

        $this->actingAsMember($admin, $acme)
            ->put(route('incidents.update', $foreign), [
                'status' => 'resolved',
                'priority' => 'p2',
                'severity' => 'high',
            ])
            ->assertNotFound();

        $this->assertSame(
            IncidentStatus::Open,
            Incident::query()->withoutGlobalScopes()->find($foreign->id)?->status,
        );
    }

    public function test_cross_tenant_incident_comment_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreign = Incident::factory()->forHost($foreignHost)->create();

        $this->actingAsMember($admin, $acme)
            ->post(route('incidents.comment', $foreign), [
                'message' => 'Should not appear.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('incident_events', [
            'incident_id' => $foreign->id,
            'message' => 'Should not appear.',
        ]);
    }

    public function test_cross_tenant_alert_promote_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreign = Alert::factory()->forHost($foreignHost)->create();

        $this->actingAsMember($admin, $acme)
            ->post(route('alerts.incident', $foreign))
            ->assertNotFound();

        $this->assertSame(0, Incident::query()->withoutGlobalScopes()->count());
        $this->assertNull(Alert::query()->withoutGlobalScopes()->find($foreign->id)?->incident_id);
    }

    public function test_cannot_assign_a_user_from_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $foreign = $this->createMember(RoleName::Admin, $globex);
        ['host' => $host] = $this->createMonitoredHost($acme);

        $this->actingAsMember($admin, $acme)
            ->from(route('incidents.create'))
            ->post(route('incidents.store'), [
                'title' => 'Should not assign foreign user',
                'severity' => 'high',
                'host_id' => $host->id,
                'assigned_to' => $foreign->id,
            ])
            ->assertRedirect(route('incidents.create'))
            ->assertSessionHasErrors('assigned_to');

        $this->assertSame(0, Incident::query()->withoutGlobalScopes()->count());
    }

    public function test_cannot_attach_a_host_from_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        ['host' => $foreignHost] = $this->createMonitoredHost($globex);

        $this->actingAsMember($admin, $acme)
            ->from(route('incidents.create'))
            ->post(route('incidents.store'), [
                'title' => 'Should not attach foreign host',
                'severity' => 'high',
                'host_id' => $foreignHost->id,
            ])
            ->assertRedirect(route('incidents.create'))
            ->assertSessionHasErrors('host_id');

        $this->assertSame(0, Incident::query()->withoutGlobalScopes()->count());
    }
}
