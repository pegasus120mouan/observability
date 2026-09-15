<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentServiceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_agent_discovers_running_host_services(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);

        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'Apache', 'type' => 'apache'],
                ['name' => 'MySQL', 'type' => 'mysql'],
                ['name' => 'PostgreSQL', 'type' => 'postgres'],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applications', 3)
            ->assertJsonPath('data.stopped', 0);

        $this->assertDatabaseHas('applications', [
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'slug' => 'apache-webserver',
            'type' => ApplicationType::Apache->value,
            'status' => ApplicationStatus::Healthy->value,
            'discovered' => true,
        ]);
        $this->assertDatabaseHas('applications', [
            'slug' => 'mysql-webserver',
            'type' => ApplicationType::Mysql->value,
        ]);
        $this->assertDatabaseHas('applications', [
            'slug' => 'postgresql-webserver',
            'type' => ApplicationType::Postgres->value,
        ]);
    }

    public function test_missing_discovered_service_is_marked_unknown(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);

        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'Apache', 'type' => 'apache'],
                ['name' => 'MySQL', 'type' => 'mysql'],
            ],
        ], $this->agentHeaders($agent, $apiKey))->assertStatus(202);

        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'Apache', 'type' => 'apache'],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('data.applications', 1)
            ->assertJsonPath('data.stopped', 1);

        $this->assertDatabaseHas('applications', [
            'host_id' => $host->id,
            'slug' => 'apache-webserver',
            'status' => ApplicationStatus::Healthy->value,
        ]);
        $this->assertDatabaseHas('applications', [
            'host_id' => $host->id,
            'slug' => 'mysql-webserver',
            'status' => ApplicationStatus::Unknown->value,
        ]);
    }

    public function test_empty_service_list_marks_discovered_services_unknown(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);

        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'Apache', 'type' => 'apache'],
            ],
        ], $this->agentHeaders($agent, $apiKey))->assertStatus(202);

        $this->postJson('/api/v1/agent/services', [
            'services' => [],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('data.applications', 0)
            ->assertJsonPath('data.stopped', 1);

        $this->assertDatabaseHas('applications', [
            'slug' => 'apache-webserver',
            'status' => ApplicationStatus::Unknown->value,
        ]);
    }

    public function test_unknown_service_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'COBOL', 'type' => 'cobol'],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable();

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
    }

    public function test_service_discovery_requires_agent_credentials(): void
    {
        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'Apache', 'type' => 'apache'],
            ],
        ])->assertUnauthorized();
    }
}
