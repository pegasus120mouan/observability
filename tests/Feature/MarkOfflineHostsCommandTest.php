<?php

namespace Tests\Feature;

use App\Enums\AgentStatus;
use App\Enums\HostStatus;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkOfflineHostsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_stale_hosts_and_agents_are_marked_offline(): void
    {
        $organization = Organization::factory()->create();

        ['host' => $staleHost, 'agent' => $staleAgent] = $this->createMonitoredHost($organization, [
            'hostname' => 'stale.acme.test',
            'status' => HostStatus::Online,
            'last_seen_at' => now()->subMinutes(10),
        ], [
            'status' => AgentStatus::Online,
            'last_seen_at' => now()->subMinutes(10),
        ]);

        ['host' => $freshHost, 'agent' => $freshAgent] = $this->createMonitoredHost($organization, [
            'hostname' => 'fresh.acme.test',
            'status' => HostStatus::Online,
            'last_seen_at' => now()->subMinute(),
        ], [
            'status' => AgentStatus::Online,
            'last_seen_at' => now()->subMinute(),
        ]);

        ['host' => $maintenanceHost] = $this->createMonitoredHost($organization, [
            'hostname' => 'maint.acme.test',
            'status' => HostStatus::Maintenance,
            'last_seen_at' => now()->subHour(),
        ]);

        $this->artisan('hosts:mark-offline')
            ->expectsOutput('Marked 1 hosts and 1 agents offline.')
            ->assertSuccessful();

        $this->assertDatabaseHas('hosts', [
            'id' => $staleHost->id,
            'status' => HostStatus::Offline->value,
        ]);
        $this->assertDatabaseHas('agents', [
            'id' => $staleAgent->id,
            'status' => AgentStatus::Offline->value,
        ]);
        $this->assertDatabaseHas('hosts', [
            'id' => $freshHost->id,
            'status' => HostStatus::Online->value,
        ]);
        $this->assertDatabaseHas('agents', [
            'id' => $freshAgent->id,
            'status' => AgentStatus::Online->value,
        ]);
        $this->assertDatabaseHas('hosts', [
            'id' => $maintenanceHost->id,
            'status' => HostStatus::Maintenance->value,
        ]);
    }
}
