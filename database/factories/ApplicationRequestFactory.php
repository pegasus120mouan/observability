<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationRequest>
 */
class ApplicationRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'organization_id' => fn (array $attributes): mixed => Application::query()->find($attributes['application_id'])?->organization_id,
            'occurred_at' => now(),
            'method' => 'GET',
            'resource' => '/health',
            'status_code' => 200,
            'duration_us' => 2740,
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
