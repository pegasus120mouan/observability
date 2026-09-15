<?php

namespace Database\Factories;

use App\Enums\AlertCondition;
use App\Enums\AlertMetric;
use App\Enums\AlertSeverity;
use App\Models\AlertRule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'CPU high',
            'description' => 'CPU usage stayed above the threshold.',
            'metric_type' => AlertMetric::Cpu,
            'condition' => AlertCondition::Gt,
            'threshold' => 90,
            'duration' => 5,
            'severity' => AlertSeverity::Critical,
            'enabled' => true,
            'notification_channels' => [],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }
}
