<?php

namespace Tests\Feature;

use App\Enums\AlertStatus;
use App\Enums\RoleName;
use App\Models\Alert;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_viewer_can_open_alerts_but_cannot_create_a_rule(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'alert-host']);
        Alert::factory()->forHost($host)->create(['title' => 'visible alert']);

        $this->actingAsMember($viewer, $organization)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('visible alert');

        $this->actingAsMember($viewer, $organization)
            ->get(route('hosts.show', $host))
            ->assertOk()
            ->assertSee('Active alerts');

        $this->actingAsMember($viewer, $organization)
            ->post(route('alert-rules.store'), [
                'name' => 'Forbidden CPU',
                'metric_type' => 'cpu',
                'condition' => 'gt',
                'threshold' => 90,
                'duration' => 5,
                'severity' => 'critical',
            ])
            ->assertForbidden();
    }

    public function test_viewer_cannot_acknowledge_an_alert(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $alert = Alert::factory()->forHost($host)->create(['status' => AlertStatus::Open]);

        $this->actingAsMember($viewer, $organization)
            ->post(route('alerts.acknowledge', $alert))
            ->assertForbidden();

        $this->assertSame(AlertStatus::Open, $alert->fresh()->status);
    }

    public function test_operator_can_acknowledge_and_resolve(): void
    {
        $organization = Organization::factory()->create();
        $operator = $this->createMember(RoleName::Operator, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $alert = Alert::factory()->forHost($host)->create(['status' => AlertStatus::Open]);

        $this->actingAsMember($operator, $organization)
            ->post(route('alerts.acknowledge', $alert))
            ->assertRedirect();

        $this->assertSame(AlertStatus::Acknowledged, $alert->fresh()->status);

        $this->actingAsMember($operator, $organization)
            ->post(route('alerts.resolve', $alert))
            ->assertRedirect();

        $this->assertSame(AlertStatus::Resolved, $alert->fresh()->status);
    }

    public function test_admin_can_create_an_alert_rule(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('alert-rules.store'), [
                'name' => 'CPU critical',
                'description' => 'Demo rule',
                'metric_type' => 'cpu',
                'condition' => 'gt',
                'threshold' => 90,
                'duration' => 5,
                'severity' => 'critical',
                'enabled' => '1',
                'notify_mail' => '1',
                'mail_targets' => 'ops@acme.test',
            ])
            ->assertRedirect(route('alert-rules.index'));

        $this->assertDatabaseHas('alert_rules', [
            'organization_id' => $organization->id,
            'name' => 'CPU critical',
            'metric_type' => 'cpu',
        ]);
    }

    public function test_guest_is_redirected_from_alerts(): void
    {
        $this->get(route('alerts.index'))->assertRedirect(route('login'));
    }
}
