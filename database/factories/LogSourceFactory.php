<?php

namespace Database\Factories;

use App\Enums\LogSourceStatus;
use App\Enums\LogSourceType;
use App\Models\Host;
use App\Models\LogSource;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogSource>
 */
class LogSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'host_id' => Host::factory(),
            'name' => fake()->unique()->lexify('src-????'),
            'type' => LogSourceType::Agent,
            'configuration' => [],
            'status' => LogSourceStatus::Active,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LogSourceStatus::Paused,
        ]);
    }
}
