<?php

namespace Database\Factories;

use App\Enums\LogLevel;
use App\Models\Host;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogEntry>
 */
class LogEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'host_id' => Host::factory(),
            'source_id' => LogSource::factory(),
            'logged_at' => now(),
            'level' => LogLevel::Info,
            'message' => fake()->sentence(),
            'source' => 'agent',
            'facility' => null,
            'event_id' => null,
            'ip_address' => fake()->ipv4(),
            'username' => fake()->optional()->userName(),
            'process' => fake()->optional()->randomElement(['sshd', 'apache', 'nginx', 'mysqld']),
            'metadata' => null,
        ];
    }

    public function forHost(Host $host, ?LogSource $source = null): static
    {
        return $this->state(function (array $attributes) use ($host, $source): array {
            $source ??= LogSource::factory()->create([
                'organization_id' => $host->organization_id,
                'host_id' => $host->id,
                'name' => $attributes['source'] ?? 'agent',
            ]);

            return [
                'organization_id' => $host->organization_id,
                'host_id' => $host->id,
                'source_id' => $source->id,
                'source' => $source->name,
            ];
        });
    }
}
