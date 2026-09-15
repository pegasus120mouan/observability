<?php

namespace Database\Factories;

use App\Enums\AgentPlatform;
use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Organization;
use App\Support\AgentCredentials;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public const TEST_API_KEY = 'saha_test_agent_key_for_feature_tests';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'agent-'.fake()->unique()->numerify('###'),
            'agent_uid' => AgentCredentials::generateAgentUid(),
            'api_key_hash' => AgentCredentials::hash(AgentCredentials::generateApiKey()),
            'version' => '0.1.0',
            'platform' => AgentPlatform::Linux,
            'architecture' => 'x86_64',
            'status' => AgentStatus::Online,
            'last_seen_at' => now(),
            'installed_at' => now(),
        ];
    }

    public function withApiKey(string $plainText): static
    {
        return $this->state(fn (array $attributes): array => [
            'api_key_hash' => AgentCredentials::hash($plainText),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AgentStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
