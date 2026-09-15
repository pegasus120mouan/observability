<?php

namespace Tests\Feature;

use App\Enums\AlertStatus;
use App\Enums\RoleName;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_alert_list_does_not_include_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $acmeHost] = $this->createMonitoredHost($acme, ['hostname' => 'acme-alert-host']);
        ['host' => $globexHost] = $this->createMonitoredHost($globex, ['hostname' => 'globex-alert-host']);

        Alert::factory()->forHost($acmeHost)->create(['title' => 'acme unique alert']);
        Alert::factory()->forHost($globexHost)->create(['title' => 'globex unique alert']);

        $this->actingAsMember($admin, $acme)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('acme unique alert')
            ->assertDontSee('globex unique alert');
    }

    public function test_cross_tenant_alert_show_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreign = Alert::factory()->forHost($foreignHost)->create(['title' => 'secret alert']);

        $this->actingAsMember($admin, $acme)
            ->get(route('alerts.show', $foreign))
            ->assertNotFound();
    }

    public function test_cross_tenant_alert_acknowledge_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreign = Alert::factory()->forHost($foreignHost)->create([
            'status' => AlertStatus::Open,
        ]);

        $this->actingAsMember($admin, $acme)
            ->post(route('alerts.acknowledge', $foreign))
            ->assertNotFound();
    }

    public function test_cross_tenant_rule_update_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $foreignRule = AlertRule::factory()->create([
            'organization_id' => $globex->id,
            'name' => 'globex rule',
        ]);

        $this->actingAsMember($admin, $acme)
            ->put(route('alert-rules.update', $foreignRule), [
                'name' => 'hijacked',
                'metric_type' => 'cpu',
                'condition' => 'gt',
                'threshold' => 10,
                'duration' => 5,
                'severity' => 'low',
                'enabled' => '1',
            ])
            ->assertNotFound();
    }
}
