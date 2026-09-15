<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
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
    public static function rolesThatCannotCreateUsers(): array
    {
        return [
            'analyst' => [RoleName::Analyst],
            'operator' => [RoleName::Operator],
            'viewer' => [RoleName::Viewer],
        ];
    }

    #[DataProvider('rolesThatCannotCreateUsers')]
    public function test_non_admin_roles_cannot_create_users(RoleName $role): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createMember($role, $organization);

        $this->actingAsMember($user, $organization)
            ->post(route('users.store'), [
                'name' => 'New User',
                'email' => 'new@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => RoleName::Viewer->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    public function test_admin_can_create_a_user_in_their_organization(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('users.store'), [
                'name' => 'New Analyst',
                'email' => 'analyst@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => RoleName::Analyst->value,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'analyst@example.test']);
        $this->assertTrue(
            User::query()->where('email', 'analyst@example.test')->firstOrFail()
                ->belongsToOrganization($organization)
        );
    }

    public function test_viewer_cannot_update_organization_settings(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);

        $this->actingAsMember($viewer, $organization)
            ->put(route('settings.organization.update'), [
                'name' => 'Hijacked',
                'slug' => $organization->slug,
                'status' => 'active',
                'timezone' => 'UTC',
                'metric_retention_days' => 30,
                'log_retention_days' => 90,
                'audit_retention_days' => 365,
            ])
            ->assertForbidden();

        $this->assertSame($organization->name, $organization->fresh()->name);
    }

    public function test_admin_cannot_list_all_organizations(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->get(route('organizations.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_list_organizations(): void
    {
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create([
            'current_organization_id' => $organization->id,
        ]);

        $this->actingAsMember($superAdmin, $organization)
            ->get(route('organizations.index'))
            ->assertOk();
    }
}
