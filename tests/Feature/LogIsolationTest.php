<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\RoleName;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_log_explorer_does_not_list_another_organization_entry(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $acmeHost] = $this->createMonitoredHost($acme, ['hostname' => 'acme-logs']);
        ['host' => $globexHost] = $this->createMonitoredHost($globex, ['hostname' => 'globex-logs']);

        $acmeSource = LogSource::factory()->create([
            'organization_id' => $acme->id,
            'host_id' => $acmeHost->id,
            'name' => 'apache',
        ]);
        $globexSource = LogSource::factory()->create([
            'organization_id' => $globex->id,
            'host_id' => $globexHost->id,
            'name' => 'nginx',
        ]);

        LogEntry::factory()->forHost($acmeHost, $acmeSource)->create([
            'level' => LogLevel::Error,
            'message' => 'acme unique failure',
        ]);
        LogEntry::factory()->forHost($globexHost, $globexSource)->create([
            'level' => LogLevel::Error,
            'message' => 'globex unique failure',
        ]);

        $this->actingAsMember($admin, $acme)
            ->get(route('logs.index'))
            ->assertOk()
            ->assertSee('acme unique failure')
            ->assertDontSee('globex unique failure');
    }

    public function test_log_entries_are_stored_against_the_agent_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        ['host' => $acmeHost, 'agent' => $agent, 'api_key' => $apiKey] = $this->createMonitoredHost($acme);
        ['host' => $globexHost] = $this->createMonitoredHost($globex);

        $this->postJson('/api/v1/agent/logs', [
            'logs' => [
                ['level' => 'error', 'source' => 'apache', 'message' => 'tenant bound'],
            ],
        ], $this->agentHeaders($agent, $apiKey))->assertStatus(202);

        $this->assertSame(1, LogEntry::query()->withoutGlobalScopes()->where('host_id', $acmeHost->id)->count());
        $this->assertSame(0, LogEntry::query()->withoutGlobalScopes()->where('host_id', $globexHost->id)->count());
        $this->assertSame($acme->id, LogEntry::query()->withoutGlobalScopes()->firstOrFail()->organization_id);
    }

    public function test_cross_tenant_log_source_pause_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);
        $foreignSource = LogSource::factory()->create([
            'organization_id' => $globex->id,
            'host_id' => $foreignHost->id,
            'name' => 'secret-source',
        ]);

        $this->actingAsMember($admin, $acme)
            ->post(route('log-sources.pause', $foreignSource))
            ->assertNotFound();
    }

    public function test_keyword_filter_matches_message(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'filter-host']);
        $source = LogSource::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
        ]);

        LogEntry::factory()->forHost($host, $source)->create([
            'message' => 'upstream connection refused',
            'level' => LogLevel::Error,
        ]);
        LogEntry::factory()->forHost($host, $source)->create([
            'message' => 'health check ok',
            'level' => LogLevel::Info,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('logs.index', ['q' => 'refused']))
            ->assertOk()
            ->assertSee('upstream connection refused')
            ->assertDontSee('health check ok');
    }
}
