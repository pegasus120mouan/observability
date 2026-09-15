<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\LogSourceStatus;
use App\Enums\RoleName;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_viewer_can_open_explorer_and_export(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'log-host']);
        $source = LogSource::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
        ]);

        LogEntry::factory()->forHost($host, $source)->create([
            'message' => 'exportable apache error',
            'level' => LogLevel::Error,
        ]);

        $this->actingAsMember($viewer, $organization)
            ->get(route('logs.index'))
            ->assertOk()
            ->assertSee('exportable apache error');

        $csv = $this->actingAsMember($viewer, $organization)
            ->get(route('logs.export', ['format' => 'csv']));

        $csv->assertOk();
        $this->assertStringContainsString('exportable apache error', $csv->streamedContent());

        $this->actingAsMember($viewer, $organization)
            ->get(route('logs.export', ['format' => 'json']))
            ->assertOk()
            ->assertJsonFragment(['message' => 'exportable apache error']);

        $this->actingAsMember($viewer, $organization)
            ->get(route('hosts.show', $host))
            ->assertOk()
            ->assertSee('Recent logs')
            ->assertSee('exportable apache error');
    }

    public function test_viewer_cannot_pause_a_log_source(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $source = LogSource::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
        ]);

        $this->actingAsMember($viewer, $organization)
            ->post(route('log-sources.pause', $source))
            ->assertForbidden();

        $this->assertSame(LogSourceStatus::Active, $source->fresh()->status);
    }

    public function test_admin_can_pause_a_log_source(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);
        $source = LogSource::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
        ]);

        $this->actingAsMember($admin, $organization)
            ->post(route('log-sources.pause', $source))
            ->assertRedirect();

        $this->assertSame(LogSourceStatus::Paused, $source->fresh()->status);
    }

    public function test_guest_is_redirected_from_logs(): void
    {
        $this->get(route('logs.index'))->assertRedirect(route('login'));
    }
}
