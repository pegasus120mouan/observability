<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_admin_can_manage_users_in_the_current_organization(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $member = $this->createMember(RoleName::Viewer, $organization);

        app(TenantContext::class)->set($organization);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $member));
        $this->assertTrue($admin->can('delete', $member));
        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_viewer_can_view_users_but_cannot_mutate_them(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        $member = $this->createMember(RoleName::Operator, $organization);

        app(TenantContext::class)->set($organization);

        $this->assertTrue($viewer->can('view', $member));
        $this->assertFalse($viewer->can('create', User::class));
        $this->assertFalse($viewer->can('update', $member));
        $this->assertFalse($viewer->can('delete', $member));
    }

    public function test_admin_cannot_update_a_user_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $adminA = $this->createMember(RoleName::Admin, $organizationA);
        $adminB = $this->createMember(RoleName::Admin, $organizationB);

        app(TenantContext::class)->set($organizationA);

        $this->assertFalse($adminA->can('update', $adminB));
    }
}
