<?php

namespace Database\Factories;

use App\Models\EnrollmentToken;
use App\Models\Organization;
use App\Support\AgentCredentials;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentToken>
 */
class EnrollmentTokenFactory extends Factory
{
    public const TEST_TOKEN = 'enroll_test_token_for_feature_tests';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Default enrollment',
            'token_hash' => AgentCredentials::hash(AgentCredentials::generateEnrollmentToken()),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function forPlainText(string $plainText): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => AgentCredentials::hash($plainText),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }
}
