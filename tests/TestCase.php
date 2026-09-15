<?php

namespace Tests;

use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Models\Agent;
use App\Models\Host;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Factories\AgentFactory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function seedPlatform(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    protected function createMember(RoleName $role, ?Organization $organization = null): User
    {
        $organization ??= Organization::factory()->create();

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
        ]);

        $this->attachToOrganization($user, $organization, $role);

        return $user->fresh(['memberships.role', 'organizations']);
    }

    protected function attachToOrganization(User $user, Organization $organization, RoleName $role): void
    {
        $roleId = Role::query()->where('name', $role)->value('id');

        $organization->users()->syncWithoutDetaching([
            $user->id => [
                'role_id' => $roleId,
                'status' => MembershipStatus::Active->value,
            ],
        ]);
    }

    protected function actingAsMember(User $user, Organization $organization): static
    {
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        return $this->actingAs($user)->withSession([
            'current_organization_id' => $organization->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $hostAttributes
     * @param  array<string, mixed>  $agentAttributes
     * @return array{host: Host, agent: Agent, api_key: string}
     */
    protected function createMonitoredHost(Organization $organization, array $hostAttributes = [], array $agentAttributes = []): array
    {
        $apiKey = $agentAttributes['api_key'] ?? AgentFactory::TEST_API_KEY;
        unset($agentAttributes['api_key']);

        $host = Host::factory()->create(array_merge([
            'organization_id' => $organization->id,
        ], $hostAttributes));

        $agent = Agent::factory()->withApiKey($apiKey)->create(array_merge([
            'organization_id' => $organization->id,
            'host_id' => $host->id,
            'name' => $host->hostname,
        ], $agentAttributes));

        $host->forceFill(['agent_id' => $agent->id])->save();

        return [
            'host' => $host->fresh(),
            'agent' => $agent,
            'api_key' => $apiKey,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function agentHeaders(Agent $agent, string $apiKey): array
    {
        return [
            'X-Agent-Id' => $agent->agent_uid,
            'Authorization' => 'Bearer '.$apiKey,
        ];
    }
}
