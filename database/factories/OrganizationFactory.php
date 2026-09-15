<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => OrganizationStatus::Active,
            'timezone' => 'UTC',
            'metric_retention_days' => 30,
            'log_retention_days' => 90,
            'audit_retention_days' => 365,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Suspended,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Trial,
        ]);
    }
}
