<?php

namespace Tests\Feature;

use App\Enums\HostEnvironment;
use App\Enums\RoleName;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    /**
     * @return array<string, array{0: RoleName}>
     */
    public static function rolesThatCanViewHosts(): array
    {
        return [
            'admin' => [RoleName::Admin],
            'analyst' => [RoleName::Analyst],
            'operator' => [RoleName::Operator],
            'viewer' => [RoleName::Viewer],
        ];
    }

    #[DataProvider('rolesThatCanViewHosts')]
    public function test_organization_roles_can_view_hosts(RoleName $role): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createMember($role, $organization);
        $this->createMonitoredHost($organization, ['hostname' => 'visible.acme.test']);

        $this->actingAsMember($user, $organization)
            ->get(route('hosts.index'))
            ->assertOk()
            ->assertSee('visible.acme.test');
    }

    public function test_host_index_renders_the_operating_system_logo(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createMember(RoleName::Admin, $organization);
        $this->createMonitoredHost($organization, [
            'hostname' => 'web-01.acme.test',
            'operating_system' => 'Ubuntu',
        ]);
        $this->createMonitoredHost($organization, [
            'hostname' => 'db-01.acme.test',
            'operating_system' => 'Debian',
        ]);

        $this->actingAsMember($user, $organization)
            ->get(route('hosts.index'))
            ->assertOk()
            ->assertSee('data-os="ubuntu"', false)
            ->assertSee('data-os="debian"', false)
            ->assertSee('bi-ubuntu', false);
    }

    public function test_viewer_cannot_update_a_host(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization, [
            'display_name' => 'Original',
        ]);

        $this->actingAsMember($viewer, $organization)
            ->put(route('hosts.update', $host), [
                'display_name' => 'Changed',
                'environment' => HostEnvironment::Staging->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('hosts', [
            'id' => $host->id,
            'display_name' => 'Original',
        ]);
    }

    public function test_admin_can_update_a_host(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['host' => $host] = $this->createMonitoredHost($organization);

        $this->actingAsMember($admin, $organization)
            ->put(route('hosts.update', $host), [
                'display_name' => 'Web frontend',
                'environment' => HostEnvironment::Staging->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hosts', [
            'id' => $host->id,
            'display_name' => 'Web frontend',
            'environment' => HostEnvironment::Staging->value,
        ]);
    }

    public function test_viewer_cannot_manage_agents(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);

        $this->actingAsMember($viewer, $organization)
            ->get(route('agents.index'))
            ->assertForbidden();

        $this->actingAsMember($viewer, $organization)
            ->post(route('agents.tokens.store'), [
                'name' => 'Should fail',
                'expires_in_days' => 7,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_an_enrollment_token(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('agents.tokens.store'), [
                'name' => 'Linux fleet',
                'expires_in_days' => 14,
            ])
            ->assertRedirect()
            ->assertSessionHas('enrollment_token_plain');

        $this->assertDatabaseHas('enrollment_tokens', [
            'organization_id' => $organization->id,
            'name' => 'Linux fleet',
        ]);
    }

    public function test_admin_can_rotate_and_revoke_an_agent(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        ['agent' => $agent, 'api_key' => $oldKey] = $this->createMonitoredHost($organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('agents.rotate', $agent))
            ->assertRedirect()
            ->assertSessionHas('rotated_api_key');

        $agent->refresh();
        $this->assertFalse($agent->apiKeyMatches($oldKey));

        $this->actingAsMember($admin, $organization)
            ->post(route('agents.revoke', $agent))
            ->assertRedirect();

        $this->assertTrue($agent->fresh()->isRevoked());
    }
}
