<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\ApplicationRequest;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentHttpRequestIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_agent_attaches_http_samples_to_a_discovered_apache_application(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);
        $application = Application::factory()->forOrganization($organization)->create([
            'host_id' => $host->id,
            'name' => 'Apache',
            'slug' => 'apache-webserver',
            'type' => ApplicationType::Apache,
            'discovered' => true,
            'status' => ApplicationStatus::Healthy,
        ]);

        $this->postJson('/api/v1/agent/http-requests', [
            'timestamp' => now()->toIso8601String(),
            'name' => 'Apache',
            'type' => 'apache',
            'requests' => [
                [
                    'occurred_at' => now()->subSeconds(2)->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/health',
                    'status_code' => 200,
                    'duration_us' => 2740,
                ],
                [
                    'occurred_at' => now()->subSecond()->toIso8601String(),
                    'method' => 'POST',
                    'resource' => '/login',
                    'status_code' => 500,
                    'duration_us' => 11800,
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inserted', 2)
            ->assertJsonPath('data.application_id', $application->id);

        $this->assertSame(1, Application::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('application_requests', [
            'application_id' => $application->id,
            'method' => 'GET',
            'resource' => '/health',
            'status_code' => 200,
            'duration_us' => 2740,
        ]);
        $this->assertDatabaseHas('application_metrics', [
            'application_id' => $application->id,
            'request_count' => 2,
            'error_count' => 1,
        ]);
        $this->assertSame(
            ApplicationStatus::Critical,
            Application::query()->withoutGlobalScopes()->find($application->id)?->status,
        );
    }

    public function test_http_samples_create_an_application_when_none_exists(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);

        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
            'requests' => [
                [
                    'occurred_at' => now()->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/',
                    'status_code' => 200,
                    'duration_us' => 900,
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202);

        $application = Application::query()->withoutGlobalScopes()->where('slug', 'apache-webserver')->first();

        $this->assertNotNull($application);
        $this->assertSame($host->id, $application->host_id);
        $this->assertSame(ApplicationType::Apache, $application->type);
        $this->assertSame(ApplicationStatus::Healthy, $application->status);
        $this->assertSame(1, ApplicationRequest::query()->withoutGlobalScopes()->count());
    }

    public function test_service_discovery_does_not_clear_http_error_status(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);

        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
            'requests' => [
                [
                    'occurred_at' => now()->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/',
                    'status_code' => 500,
                    'duration_us' => 1000,
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))->assertStatus(202);

        $this->postJson('/api/v1/agent/services', [
            'services' => [
                ['name' => 'Apache', 'type' => 'apache'],
            ],
        ], $this->agentHeaders($agent, $apiKey))->assertStatus(202);

        $this->assertDatabaseHas('applications', [
            'slug' => 'apache-webserver',
            'status' => ApplicationStatus::Critical->value,
        ]);
    }

    public function test_invalid_status_code_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
            'requests' => [
                [
                    'occurred_at' => now()->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/',
                    'status_code' => 99,
                    'duration_us' => 10,
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(0, ApplicationRequest::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationMetric::query()->withoutGlobalScopes()->count());
    }

    public function test_not_found_responses_do_not_mark_the_application_critical(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);
        $application = Application::factory()->forOrganization($organization)->create([
            'host_id' => $host->id,
            'name' => 'Apache',
            'slug' => 'apache-webserver',
            'type' => ApplicationType::Apache,
            'discovered' => true,
            'status' => ApplicationStatus::Critical,
        ]);

        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
            'requests' => [
                [
                    'occurred_at' => now()->subSeconds(2)->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/',
                    'status_code' => 200,
                    'duration_us' => 900,
                ],
                [
                    'occurred_at' => now()->subSecond()->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/favicon.ico',
                    'status_code' => 404,
                    'duration_us' => 400,
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202);

        $this->assertDatabaseHas('application_metrics', [
            'application_id' => $application->id,
            'request_count' => 2,
            'error_count' => 0,
        ]);
        $this->assertSame(
            ApplicationStatus::Healthy,
            Application::query()->withoutGlobalScopes()->find($application->id)?->status,
        );
    }

    public function test_http_request_ingest_requires_agent_credentials(): void
    {
        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
            'requests' => [
                [
                    'occurred_at' => now()->toIso8601String(),
                    'method' => 'GET',
                    'resource' => '/',
                    'status_code' => 200,
                    'duration_us' => 10,
                ],
            ],
        ])->assertUnauthorized();
    }

    public function test_agent_can_ingest_live_apache_status_without_access_log_lines(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization, [
            'hostname' => 'webserver',
        ]);
        $application = Application::factory()->forOrganization($organization)->create([
            'host_id' => $host->id,
            'name' => 'Apache',
            'slug' => 'apache-webserver',
            'type' => ApplicationType::Apache,
            'discovered' => true,
        ]);

        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
            'sample' => [
                'request_count' => 24,
                'error_count' => 0,
                'req_per_sec' => 12.5,
                'busy_workers' => 3,
                'idle_workers' => 7,
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('data.inserted', 24)
            ->assertJsonPath('data.application_id', $application->id);

        $this->assertSame(0, ApplicationRequest::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('application_metrics', [
            'application_id' => $application->id,
            'request_count' => 24,
            'error_count' => 0,
        ]);

        $fresh = Application::query()->withoutGlobalScopes()->find($application->id);

        $this->assertNotNull($fresh);
        $this->assertEqualsWithDelta(12.5, (float) $fresh->runtime_stats['req_per_sec'], 0.01);
        $this->assertSame(3, (int) $fresh->runtime_stats['busy_workers']);
        $this->assertSame(7, (int) $fresh->runtime_stats['idle_workers']);
    }

    public function test_empty_http_payload_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/http-requests', [
            'name' => 'Apache',
            'type' => 'apache',
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(0, ApplicationRequest::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationMetric::query()->withoutGlobalScopes()->count());
    }
}
