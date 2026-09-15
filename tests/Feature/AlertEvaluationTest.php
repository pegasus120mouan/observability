<?php

namespace Tests\Feature;

use App\Actions\EvaluateAlertsAction;
use App\Enums\AlertMetric;
use App\Enums\AlertStatus;
use App\Enums\HostStatus;
use App\Enums\LogLevel;
use App\Enums\MetricType;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_metric_rule_opens_an_alert_when_the_threshold_holds(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'cpu-hot']);
        $rule = AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'CPU critical',
            'metric_type' => AlertMetric::Cpu,
            'threshold' => 90,
            'duration' => 5,
        ]);

        $this->writeCpu($organization, $host->id, 95, now()->subMinutes(6));
        $this->writeCpu($organization, $host->id, 96, now()->subMinute());

        $result = app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertSame(1, $result['opened']);
        $this->assertDatabaseHas('alerts', [
            'alert_rule_id' => $rule->id,
            'host_id' => $host->id,
            'status' => AlertStatus::Open->value,
            'title' => 'cpu-hot: CPU critical',
        ]);
    }

    public function test_metric_rule_does_not_open_when_duration_is_not_met(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization);
        AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::Cpu,
            'threshold' => 90,
            'duration' => 5,
        ]);

        $this->writeCpu($organization, $host->id, 99, now()->subMinute());

        app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->count());
    }

    public function test_disabled_rule_is_skipped(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization);
        AlertRule::factory()->disabled()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::Cpu,
            'threshold' => 10,
            'duration' => 5,
        ]);

        $this->writeCpu($organization, $host->id, 99, now()->subMinutes(6));
        $this->writeCpu($organization, $host->id, 99, now()->subMinute());

        app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertSame(0, Alert::query()->withoutGlobalScopes()->count());
    }

    public function test_existing_open_alert_is_not_duplicated(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization);
        $rule = AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::Cpu,
            'threshold' => 90,
            'duration' => 5,
        ]);

        $this->writeCpu($organization, $host->id, 95, now()->subMinutes(6));
        $this->writeCpu($organization, $host->id, 96, now()->subMinute());

        app(EvaluateAlertsAction::class)->handle($organization);
        app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('alert_rule_id', $rule->id)->count());
    }

    public function test_cleared_condition_auto_resolves_an_open_alert(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization);
        $rule = AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::Cpu,
            'threshold' => 90,
            'duration' => 5,
        ]);

        $this->writeCpu($organization, $host->id, 95, now()->subMinutes(6));
        $this->writeCpu($organization, $host->id, 96, now()->subMinute());
        app(EvaluateAlertsAction::class)->handle($organization);

        MetricSample::query()->withoutGlobalScopes()->where('host_id', $host->id)->delete();
        $this->writeCpu($organization, $host->id, 20, now()->subMinutes(6));
        $this->writeCpu($organization, $host->id, 22, now()->subMinute());

        $result = app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertSame(1, $result['resolved']);
        $this->assertSame(AlertStatus::Resolved, Alert::query()->withoutGlobalScopes()->where('alert_rule_id', $rule->id)->firstOrFail()->status);
    }

    public function test_host_offline_rule_opens_an_alert(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization, [
            'hostname' => 'silent-host',
            'status' => HostStatus::Offline,
            'last_seen_at' => now()->subMinutes(12),
        ]);
        AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Host offline',
            'metric_type' => AlertMetric::HostOffline,
            'threshold' => null,
            'duration' => 5,
        ]);

        app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertDatabaseHas('alerts', [
            'host_id' => $host->id,
            'title' => 'silent-host: Host offline',
            'status' => AlertStatus::Open->value,
        ]);
    }

    public function test_log_error_rule_opens_when_count_exceeds_threshold(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization, ['hostname' => 'loggy']);
        $source = LogSource::factory()->create([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => 'apache',
        ]);
        AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Error burst',
            'metric_type' => AlertMetric::LogError,
            'threshold' => 3,
            'duration' => 15,
        ]);

        for ($i = 0; $i < 4; $i++) {
            LogEntry::factory()->forHost($host, $source)->create([
                'level' => LogLevel::Error,
                'message' => 'burst '.$i,
                'logged_at' => now()->subMinutes($i),
            ]);
        }

        app(EvaluateAlertsAction::class)->handle($organization);

        $this->assertDatabaseHas('alerts', [
            'host_id' => $host->id,
            'title' => 'loggy: Error burst',
        ]);
    }

    public function test_evaluate_command_dispatches_jobs_and_opens_alerts(): void
    {
        $organization = Organization::factory()->create();
        ['host' => $host] = $this->createMonitoredHost($organization, [
            'status' => HostStatus::Offline,
            'last_seen_at' => now()->subMinutes(20),
        ]);
        AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::HostOffline,
            'duration' => 5,
        ]);

        $this->artisan('alerts:evaluate')
            ->expectsOutput('Dispatched 1 alert evaluation jobs.')
            ->assertSuccessful();

        $this->assertSame(1, Alert::query()->withoutGlobalScopes()->where('host_id', $host->id)->count());
    }

    private function writeCpu(Organization $organization, int $hostId, float $value, $collectedAt): void
    {
        MetricSample::factory()->forMetric(MetricType::Cpu, 'usage')->create([
            'organization_id' => $organization->id,
            'host_id' => $hostId,
            'value' => $value,
            'collected_at' => $collectedAt,
        ]);
    }
}
