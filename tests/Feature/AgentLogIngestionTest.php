<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\LogSourceStatus;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentLogIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_agent_can_ingest_log_entries(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/logs', [
            'timestamp' => now()->toIso8601String(),
            'logs' => [
                [
                    'timestamp' => now()->subMinute()->toIso8601String(),
                    'level' => 'ERROR',
                    'source' => 'apache',
                    'message' => 'AH00094: Command line: apache2 -D FOREGROUND',
                    'process' => 'apache2',
                    'user' => 'www-data',
                    'ip_address' => '10.0.1.40',
                    'metadata' => ['request_id' => 'abc-123'],
                ],
                [
                    'level' => 'info',
                    'source' => 'apache',
                    'message' => 'GET /health 200',
                ],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inserted', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.host_id', $host->id);

        $this->assertDatabaseHas('log_sources', [
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
        ]);
        $this->assertDatabaseHas('logs', [
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'level' => LogLevel::Error->value,
            'message' => 'AH00094: Command line: apache2 -D FOREGROUND',
            'username' => 'www-data',
        ]);
        $this->assertSame(2, LogEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, LogSource::query()->withoutGlobalScopes()->count());
    }

    public function test_paused_source_skips_new_entries(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        LogSource::factory()->paused()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
            'status' => LogSourceStatus::Paused,
        ]);

        $this->postJson('/api/v1/agent/logs', [
            'logs' => [
                ['level' => 'error', 'source' => 'apache', 'message' => 'should skip'],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertStatus(202)
            ->assertJsonPath('data.inserted', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(0, LogEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_unknown_level_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        ['agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($organization);

        $this->postJson('/api/v1/agent/logs', [
            'logs' => [
                ['level' => 'trace', 'message' => 'too verbose'],
            ],
        ], $this->agentHeaders($agent, $apiKey))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(0, LogEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_logs_require_agent_credentials(): void
    {
        $this->postJson('/api/v1/agent/logs', [
            'logs' => [
                ['level' => 'info', 'message' => 'hello'],
            ],
        ])->assertUnauthorized();
    }
}
