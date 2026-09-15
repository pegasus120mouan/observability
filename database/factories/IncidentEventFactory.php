<?php

namespace Database\Factories;

use App\Enums\IncidentEventType;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentEvent>
 */
class IncidentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'organization_id' => function (array $attributes): mixed {
                $incident = $attributes['incident_id'] ?? null;

                if ($incident instanceof Incident) {
                    return $incident->organization_id;
                }

                return Incident::query()->find($incident)?->organization_id
                    ?? Organization::factory();
            },
            'type' => IncidentEventType::Comment,
            'message' => fake()->sentence(),
        ];
    }
}
