<?php

namespace App\Actions;

use App\Enums\AlertMetric;
use App\Enums\AlertStatus;
use App\Enums\HostStatus;
use App\Enums\LogLevel;
use App\Jobs\SendAlertNotificationJob;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Host;
use App\Models\LogEntry;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Support\Collection;

class EvaluateAlertsAction
{
    /**
     * @return array{opened: int, resolved: int}
     */
    public function handle(?Organization $organization = null): array
    {
        $opened = 0;
        $resolved = 0;

        $organizations = $organization === null
            ? Organization::query()->orderBy('id')->get()
            : collect([$organization]);

        foreach ($organizations as $tenant) {
            $result = $this->evaluateOrganization($tenant);
            $opened += $result['opened'];
            $resolved += $result['resolved'];
        }

        return compact('opened', 'resolved');
    }

    /**
     * @return array{opened: int, resolved: int}
     */
    private function evaluateOrganization(Organization $organization): array
    {
        $opened = 0;
        $resolved = 0;

        $rules = AlertRule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('enabled', true)
            ->get();

        if ($rules->isEmpty()) {
            return compact('opened', 'resolved');
        }

        $hosts = Host::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            foreach ($hosts as $host) {
                if ($host->status === HostStatus::Maintenance) {
                    continue;
                }

                $evaluation = $this->evaluate($rule, $host);
                $active = $this->activeAlert($rule, $host);

                if ($evaluation['breached'] && $active === null) {
                    $alert = $this->open($rule, $host, $evaluation);
                    SendAlertNotificationJob::dispatch($alert->id);
                    $opened++;

                    continue;
                }

                if (! $evaluation['breached'] && $active !== null) {
                    $active->forceFill([
                        'status' => AlertStatus::Resolved,
                        'resolved_at' => now(),
                        'resolved_by' => null,
                    ])->save();
                    $resolved++;
                }
            }
        }

        return compact('opened', 'resolved');
    }

    /**
     * @return array{breached: bool, value: float|null}
     */
    private function evaluate(AlertRule $rule, Host $host): array
    {
        return match ($rule->metric_type) {
            AlertMetric::HostOffline => $this->evaluateOffline($rule, $host),
            AlertMetric::LogError => $this->evaluateLogErrors($rule, $host),
            default => $this->evaluateMetric($rule, $host),
        };
    }

    /**
     * @return array{breached: bool, value: float|null}
     */
    private function evaluateMetric(AlertRule $rule, Host $host): array
    {
        $metricType = $rule->metric_type->metricType();

        if ($metricType === null || $rule->threshold === null) {
            return ['breached' => false, 'value' => null];
        }

        $windowStart = now()->subMinutes(max(1, $rule->duration));

        /** @var Collection<int, MetricSample> $samples */
        $samples = MetricSample::query()
            ->withoutGlobalScopes()
            ->where('host_id', $host->id)
            ->where('metric_type', $metricType)
            ->where('metric_name', $rule->metric_type->metricName())
            ->where('collected_at', '>=', now()->subMinutes($rule->duration + 1))
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get();

        if ($samples->isEmpty()) {
            return ['breached' => false, 'value' => null];
        }

        $latest = (float) $samples->last()->value;
        $allMatch = $samples->every(
            fn (MetricSample $sample): bool => $rule->condition->matches((float) $sample->value, (float) $rule->threshold)
        );

        $durationMet = $samples->first()->collected_at?->lte($windowStart) ?? false;

        return [
            'breached' => $allMatch && $durationMet,
            'value' => $latest,
        ];
    }

    /**
     * @return array{breached: bool, value: float|null}
     */
    private function evaluateOffline(AlertRule $rule, Host $host): array
    {
        if ($host->last_seen_at === null) {
            return ['breached' => true, 'value' => null];
        }

        $minutes = $host->last_seen_at->diffInMinutes(now());

        return [
            'breached' => $host->last_seen_at->lte(now()->subMinutes($rule->duration)),
            'value' => (float) $minutes,
        ];
    }

    /**
     * @return array{breached: bool, value: float|null}
     */
    private function evaluateLogErrors(AlertRule $rule, Host $host): array
    {
        $from = now()->subMinutes(max(1, $rule->duration));
        $count = LogEntry::query()
            ->withoutGlobalScopes()
            ->where('host_id', $host->id)
            ->where('logged_at', '>=', $from)
            ->whereIn('level', [
                LogLevel::Error,
                LogLevel::Critical,
                LogLevel::Alert,
                LogLevel::Emergency,
            ])
            ->count();

        $threshold = (float) ($rule->threshold ?? 0);

        return [
            'breached' => $rule->condition->matches((float) $count, $threshold),
            'value' => (float) $count,
        ];
    }

    private function activeAlert(AlertRule $rule, Host $host): ?Alert
    {
        return Alert::query()
            ->withoutGlobalScopes()
            ->where('alert_rule_id', $rule->id)
            ->where('host_id', $host->id)
            ->whereIn('status', [AlertStatus::Open, AlertStatus::Acknowledged])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array{breached: bool, value: float|null}  $evaluation
     */
    private function open(AlertRule $rule, Host $host, array $evaluation): Alert
    {
        return Alert::query()->withoutGlobalScopes()->create([
            'organization_id' => $rule->organization_id,
            'host_id' => $host->id,
            'alert_rule_id' => $rule->id,
            'title' => $host->hostname.': '.$rule->name,
            'description' => $rule->description ?: $rule->summary(),
            'severity' => $rule->severity,
            'status' => AlertStatus::Open,
            'triggered_at' => now(),
            'metadata' => [
                'value' => $evaluation['value'],
                'threshold' => $rule->threshold,
                'condition' => $rule->condition->value,
                'duration' => $rule->duration,
                'metric_type' => $rule->metric_type->value,
            ],
        ]);
    }
}
