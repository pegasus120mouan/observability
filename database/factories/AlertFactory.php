<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Host;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'host_id' => Host::factory(),
            'alert_rule_id' => AlertRule::factory(),
            'title' => 'srv-01: CPU high',
            'description' => 'CPU usage stayed above the threshold.',
            'severity' => AlertSeverity::Critical,
            'status' => AlertStatus::Open,
            'triggered_at' => now(),
            'metadata' => ['value' => 95.0],
        ];
    }

    public function forHost(Host $host, ?AlertRule $rule = null): static
    {
        return $this->state(function (array $attributes) use ($host, $rule): array {
            $rule ??= AlertRule::factory()->create([
                'organization_id' => $host->organization_id,
            ]);

            return [
                'organization_id' => $host->organization_id,
                'host_id' => $host->id,
                'alert_rule_id' => $rule->id,
                'title' => $host->hostname.': '.$rule->name,
                'severity' => $rule->severity,
            ];
        });
    }
}
