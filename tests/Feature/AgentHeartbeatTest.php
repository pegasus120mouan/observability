<?php

namespace Tests\Feature;

use App\Enums\AgentStatus;
use App\Enums\HostStatus;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_heartbeat_marks_the_host_and_agent_online(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'status' => HostStatus::Offline,
            'last_seen_at' => now()->subHour(),
        ], [
            'status' => AgentStatus::Offline,
            'last_seen_at' => now()->subHour(),
        ]);

        $this->postJson('/api/v1/agent/heartbeat', [
            'version' => '0.1.1',
            'ip_address' => '10.0.1.99',
        ], $this->agentHeaders($agent, $apiKey))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AgentStatus::Online->value)
            ->assertJsonPath('data.host_status', HostStatus::Online->value);

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'status' => AgentStatus::Online->value,
            'version' => '0.1.1',
        ]);
        $this->assertDatabaseHas('hosts', [
            'id' => $host->id,
            'status' => HostStatus::Online->value,
            'ip_address' => '10.0.1.99',
        ]);
    }

    public function test_config_requires_agent_credentials(): void
    {
        $this->getJson('/api/v1/agent/config')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/heartbeat', [], $this->agentHeaders($agent, 'saha_wrong_key'))
            ->assertUnauthorized();
    }

    public function test_revoked_agent_cannot_heartbeat(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [], [
            'status' => AgentStatus::Revoked,
            'revoked_at' => now(),
        ]);

        $this->postJson('/api/v1/agent/heartbeat', [], $this->agentHeaders($agent, $apiKey))
            ->assertUnauthorized();
    }

    public function test_authenticated_agent_can_read_config(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->getJson('/api/v1/agent/config', $this->agentHeaders($agent, $apiKey))
            ->assertOk()
            ->assertJsonPath('data.agent_id', $agent->agent_uid)
            ->assertJsonPath('data.collectors.cpu', true)
            ->assertJsonPath('data.collectors.logs', true)
            ->assertJsonPath('data.collectors.apm', true);
    }
}
