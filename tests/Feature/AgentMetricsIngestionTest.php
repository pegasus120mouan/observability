<?php

namespace Tests\Feature;

use App\Enums\HostStatus;
use App\Enums\MetricType;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentMetricsIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_agent_can_ingest_metric_samples(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/metrics', [
            'timestamp' => now()->toIso8601String(),
            'metrics' => [
                ['type' => 'cpu', 'name' => 'usage', 'value' => 41.25, 'unit' => 'percent'],
                ['type' => 'memory', 'name' => 'usage', 'value' => 62.5],
                ['type' => 'disk', 'name' => 'usage', 'value' => 55],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inserted', 3)
            ->assertJsonPath('data.host_id', $host->id);

        $this->assertDatabaseHas('metric_samples', [
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'metric_type' => MetricType::Cpu->value,
            'metric_name' => 'usage',
        ]);
        $this->assertSame(3, MetricSample::query()->withoutGlobalScopes()->count());
    }

    public function test_unknown_metric_name_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/metrics', [
            'metrics' => [
                ['type' => 'cpu', 'name' => 'temperature', 'value' => 70],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(0, MetricSample::query()->withoutGlobalScopes()->count());
    }

    public function test_high_usage_marks_the_host_critical(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'status' => HostStatus::Online,
        ]);

        $this->postJson('/api/v1/agent/metrics', [
            'metrics' => [
                ['type' => 'cpu', 'name' => 'usage', 'value' => 96],
                ['type' => 'memory', 'name' => 'usage', 'value' => 40],
                ['type' => 'disk', 'name' => 'usage', 'value' => 40],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('data.host_status', HostStatus::Critical->value);

        $this->assertDatabaseHas('hosts', [
            'id' => $host->id,
            'status' => HostStatus::Critical->value,
        ]);
    }

    public function test_metrics_require_agent_credentials(): void
    {
        $this->postJson('/api/v1/agent/metrics', [
            'metrics' => [
                ['type' => 'cpu', 'name' => 'usage', 'value' => 10],
            ],
        ])->assertUnauthorized();
    }
}
