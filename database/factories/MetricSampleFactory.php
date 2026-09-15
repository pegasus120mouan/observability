<?php

namespace Database\Factories;

use App\Enums\MetricType;
use App\Models\Host;
use App\Models\MetricSample;
use App\Models\Organization;
use App\Support\MetricCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricSample>
 */
class MetricSampleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = MetricType::Cpu;
        $name = 'usage';

        return [
            'organization_id' => Organization::factory(),
            'host_id' => Host::factory(),
            'metric_type' => $type,
            'metric_name' => $name,
            'value' => fake()->randomFloat(2, 5, 95),
            'unit' => MetricCatalog::unit($type, $name),
            'collected_at' => now(),
        ];
    }

    public function forMetric(MetricType $type, string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'metric_type' => $type,
            'metric_name' => $name,
            'unit' => MetricCatalog::unit($type, $name),
        ]);
    }
}
