<?php

namespace Tests\Feature;

use App\Enums\MetricType;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_prunes_samples_older_than_organization_retention(): void
    {
        $organization = Organization::factory()->create([
            'metric_retention_days' => 7,
        ]);
        ['host' => $host] = $this->createMonitoredHost($organization);

        MetricSample::factory()->forMetric(MetricType::Cpu, 'usage')->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'collected_at' => now()->subDays(10),
            'value' => 11.25,
        ]);
        MetricSample::factory()->forMetric(MetricType::Cpu, 'usage')->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'collected_at' => now()->subDay(),
            'value' => 22.5,
        ]);

        $this->artisan('metrics:prune')
            ->expectsOutput('Pruned 1 metric samples.')
            ->assertSuccessful();

        $this->assertSame(1, MetricSample::query()->withoutGlobalScopes()->count());
        $this->assertEqualsWithDelta(22.5, (float) MetricSample::query()->withoutGlobalScopes()->value('value'), 0.01);
    }

    public function test_prunes_application_metrics_older_than_organization_retention(): void
    {
        $organization = Organization::factory()->create([
            'metric_retention_days' => 7,
        ]);
        $application = Application::factory()->forOrganization($organization)->create();

        ApplicationMetric::factory()->forApplication($application)->create([
            'collected_at' => now()->subDays(10),
            'request_count' => 10,
        ]);
        ApplicationMetric::factory()->forApplication($application)->create([
            'collected_at' => now()->subDay(),
            'request_count' => 20,
        ]);

        $this->artisan('metrics:prune')
            ->expectsOutput('Pruned 1 application metrics.')
            ->assertSuccessful();

        $this->assertSame(1, ApplicationMetric::query()->withoutGlobalScopes()->count());
        $this->assertSame(20, ApplicationMetric::query()->withoutGlobalScopes()->value('request_count'));
    }
}
