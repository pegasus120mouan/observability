<?php

namespace Tests\Feature;

use App\Enums\MetricType;
use App\Enums\RoleName;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_viewer_can_open_metrics_and_host_charts(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'chart-host']);

        MetricSample::factory()->forMetric(MetricType::Cpu, 'usage')->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'value' => 47.5,
            'collected_at' => now()->subMinutes(2),
        ]);

        $this->actingAsMember($viewer, $organization)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('chart-host');

        $this->actingAsMember($viewer, $organization)
            ->get(route('hosts.show', $host))
            ->assertOk()
            ->assertSee('CPU usage')
            ->assertSee('47.5');
    }

    public function test_guest_is_redirected_from_metrics(): void
    {
        $this->get(route('metrics.index'))->assertRedirect(route('login'));
    }
}
