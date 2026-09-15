<?php

namespace Tests\Feature;

use App\Enums\MetricType;
use App\Enums\RoleName;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_metrics_explorer_does_not_list_another_organization_host(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        $this->createMonitoredHost($acme, ['hostname' => 'acme-metrics']);
        $this->createMonitoredHost($globex, ['hostname' => 'globex-metrics']);

        $this->actingAsMember($admin, $acme)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('acme-metrics')
            ->assertDontSee('globex-metrics');
    }

    public function test_metric_samples_are_stored_against_the_agent_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        ['host' => $acmeHost, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($acme);
        ['host' => $globexHost] = $this->createMonitoredHost($globex);

        $this->postJson('/api/v1/agent/metrics', [
            'metrics' => [
                ['type' => 'cpu', 'name' => 'usage', 'value' => 12],
            ],
        ], $this->agentHeaders($agent, $apiKey))->assertStatus(202);

        $this->assertSame(1, MetricSample::query()->withoutGlobalScopes()->where('host_id', $acmeHost->id)->count());
        $this->assertSame(0, MetricSample::query()->withoutGlobalScopes()->where('host_id', $globexHost->id)->count());
        $this->assertSame(MetricType::Cpu, MetricSample::query()->withoutGlobalScopes()->firstOrFail()->metric_type);
    }
}
