<?php

namespace Database\Factories;

use App\Enums\HostEnvironment;
use App\Enums\HostStatus;
use App\Models\Host;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Host>
 */
class HostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hostname = 'srv-'.fake()->unique()->bothify('??##');

        return [
            'organization_id' => Organization::factory(),
            'hostname' => $hostname,
            'display_name' => strtoupper($hostname),
            'ip_address' => fake()->ipv4(),
            'operating_system' => 'Ubuntu',
            'os_version' => '24.04',
            'architecture' => 'x86_64',
            'environment' => HostEnvironment::Production,
            'status' => HostStatus::Online,
            'last_seen_at' => now(),
            'registered_at' => now(),
        ];
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => HostStatus::Offline,
            'last_seen_at' => now()->subMinutes(30),
        ]);
    }
}
