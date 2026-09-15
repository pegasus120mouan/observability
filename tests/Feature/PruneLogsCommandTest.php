<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_prunes_entries_older_than_organization_retention(): void
    {
        $organization = Organization::factory()->create([
            'log_retention_days' => 7,
        ]);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $source = LogSource::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'syslog',
        ]);

        LogEntry::factory()->forHost($host, $source)->create([
            'logged_at' => now()->subDays(10),
            'message' => 'old entry',
            'level' => LogLevel::Warning,
        ]);
        LogEntry::factory()->forHost($host, $source)->create([
            'logged_at' => now()->subDay(),
            'message' => 'fresh entry',
            'level' => LogLevel::Info,
        ]);

        $this->artisan('logs:prune')
            ->expectsOutput('Pruned 1 log entries.')
            ->assertSuccessful();

        $this->assertSame(1, LogEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame('fresh entry', LogEntry::query()->withoutGlobalScopes()->value('message'));
    }
}
