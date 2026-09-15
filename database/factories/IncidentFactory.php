<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Host;
use App\Models\Incident;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'host_id' => Host::factory(),
            'title' => 'Disk filling on database host',
            'description' => 'Disk usage crossed the alert threshold.',
            'severity' => AlertSeverity::High,
            'status' => IncidentStatus::Open,
            'priority' => IncidentPriority::P2,
            'detected_at' => now(),
        ];
    }

    public function forHost(Host $host): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $host->organization_id,
            'host_id' => $host->id,
        ]);
    }
}
