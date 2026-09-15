<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationMetric>
 */
class ApplicationMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'organization_id' => fn (array $attributes): mixed => Application::query()->find($attributes['application_id'])?->organization_id,
            'request_count' => 420,
            'error_count' => 3,
            'response_time_avg' => 182,
            'response_time_p95' => 421,
            'status_codes' => ['200' => 400, '404' => 17, '500' => 3],
            'collected_at' => now(),
            'created_at' => now(),
        ];
    }

    public function forApplication(Application $application): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $application->organization_id,
            'application_id' => $application->id,
        ]);
    }
}
