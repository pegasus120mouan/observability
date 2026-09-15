<?php

namespace Tests\Feature;

use App\Enums\MetricType;
use App\Enums\RoleName;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostLiveMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_host_live_json_returns_the_latest_usage_samples(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);

        MetricSample::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'metric_type' => MetricType::Cpu,
            'metric_name' => 'usage',
            'value' => 41.5,
            'unit' => 'percent',
            'collected_at' => now()->subHour(),
        ]);
        MetricSample::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'metric_type' => MetricType::Cpu,
            'metric_name' => 'usage',
            'value' => 12.25,
            'unit' => 'percent',
            'collected_at' => now()->subMinute(),
        ]);
        MetricSample::factory()->forMetric(MetricType::Memory, 'usage')->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'value' => 64.0,
            'collected_at' => now()->subMinute(),
        ]);

        $this->actingAsMember($user, $organization)
            ->getJson(route('hosts.live', ['host' => $host, 'range' => '6h']))
            ->assertOk()
            ->assertJsonPath('usage.cpu', 12.25)
            ->assertJsonPath('usage.memory', 64)
            ->assertJsonPath('status', $host->status->value);
    }

    public function test_host_show_renders_the_latest_sample_older_than_fifteen_minutes(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);

        MetricSample::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'metric_type' => MetricType::Cpu,
            'metric_name' => 'usage',
            'value' => 33.0,
            'unit' => 'percent',
            'collected_at' => now()->subMinutes(45),
        ]);

        $this->actingAsMember($user, $organization)
            ->get(route('hosts.show', $host))
            ->assertOk()
            ->assertSee('data-live-host', false)
            ->assertSee('33.0', false);
    }

    public function test_live_index_omits_hosts_from_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        ['host' => $acmeHost] = $this->createMonitoredHost($acme);
        ['host' => $globexHost] = $this->createMonitoredHost($globex);

        MetricSample::factory()->create([
            'organization_id' => $globex->id,
            'host_id' => $globexHost->id,
            'metric_type' => MetricType::Cpu,
            'metric_name' => 'usage',
            'value' => 99.0,
            'unit' => 'percent',
        ]);

        $this->actingAsMember($admin, $acme)
            ->getJson(route('hosts.live-index', [
                'ids' => $acmeHost->id.','.$globexHost->id,
            ]))
            ->assertOk()
            ->assertJsonMissingPath('hosts.'.$globexHost->id)
            ->assertJsonPath('hosts.'.$acmeHost->id.'.cpu', null);
    }

    public function test_guest_is_redirected_away_from_live_metrics(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization);

        $this->getJson(route('hosts.live', $host))
            ->assertUnauthorized();
    }

    public function test_cross_tenant_live_metrics_return_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        ['host' => $foreignHost] = $this->createMonitoredHost($globex);

        $this->actingAsMember($admin, $acme)
            ->getJson(route('hosts.live', $foreignHost))
            ->assertNotFound();
    }
}
