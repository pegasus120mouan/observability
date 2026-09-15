<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_super_admin_role_cannot_be_assigned_to_a_membership(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('users.store'), [
                'name' => 'Bad Actor',
                'email' => 'bad@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => RoleName::SuperAdmin->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'bad@example.test']);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->delete(route('users.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_unexpected_is_super_admin_payload_is_ignored(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('users.store'), [
                'name' => 'Normal User',
                'email' => 'normal@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => RoleName::Viewer->value,
                'is_super_admin' => true,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertFalse(User::query()->where('email', 'normal@example.test')->firstOrFail()->isSuperAdmin());
    }
}
