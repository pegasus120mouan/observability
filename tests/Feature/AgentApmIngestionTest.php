<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApmIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_agent_can_ingest_application_metrics(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/apm', [
            'timestamp' => now()->toIso8601String(),
            'applications' => [
                [
                    'name' => 'orders-api',
                    'type' => 'laravel',
                    'environment' => 'production',
                    'version' => '1.4.2',
                    'endpoint' => 'https://api.acme.test/orders',
                    'metrics' => [
                        'request_count' => 420,
                        'error_count' => 3,
                        'response_time_avg' => 182,
                        'response_time_p95' => 421,
                        'status_codes' => ['200' => 400, '500' => 3, '404' => 17],
                    ],
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inserted', 1)
            ->assertJsonPath('data.applications', 1);

        $application = Application::query()->withoutGlobalScopes()->where('slug', 'orders-api')->first();

        $this->assertNotNull($application);
        $this->assertSame($host->id, $application->host_id);
        $this->assertSame(ApplicationType::Laravel, $application->type);
        $this->assertSame(ApplicationStatus::Healthy, $application->status);
        $this->assertDatabaseHas('application_metrics', [
            'application_id' => $application->id,
            'request_count' => 420,
            'error_count' => 3,
        ]);
    }

    public function test_unknown_application_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/apm', [
            'applications' => [
                [
                    'name' => 'orders-api',
                    'type' => 'cobol',
                    'metrics' => [
                        'request_count' => 10,
                        'error_count' => 0,
                        'response_time_avg' => 50,
                        'response_time_p95' => 80,
                    ],
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationMetric::query()->withoutGlobalScopes()->count());
    }

    public function test_high_error_rate_marks_the_application_critical(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/apm', [
            'applications' => [
                [
                    'name' => 'orders-api',
                    'type' => 'laravel',
                    'metrics' => [
                        'request_count' => 100,
                        'error_count' => 20,
                        'response_time_avg' => 200,
                        'response_time_p95' => 400,
                    ],
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202);

        $this->assertDatabaseHas('applications', [
            'slug' => 'orders-api',
            'status' => ApplicationStatus::Critical->value,
        ]);
    }

    public function test_cannot_point_apm_samples_at_a_foreign_host(): void
    {
        $organization = Organization::factory()->create();
        $foreign = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);
        ['host' => $foreignHost] = $this->createMonitoredHost($foreign);

        $this->postJson('/api/v1/agent/apm', [
            'applications' => [
                [
                    'name' => 'orders-api',
                    'type' => 'laravel',
                    'host_id' => $foreignHost->id,
                    'metrics' => [
                        'request_count' => 10,
                        'error_count' => 0,
                        'response_time_avg' => 50,
                        'response_time_p95' => 80,
                    ],
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable();

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
    }

    public function test_apm_requires_agent_credentials(): void
    {
        $this->postJson('/api/v1/agent/apm', [
            'applications' => [
                [
                    'name' => 'orders-api',
                    'type' => 'laravel',
                    'metrics' => [
                        'request_count' => 10,
                        'error_count' => 0,
                        'response_time_avg' => 50,
                        'response_time_p95' => 80,
                    ],
                ],
            ],
        ])->assertUnauthorized();
    }
}
