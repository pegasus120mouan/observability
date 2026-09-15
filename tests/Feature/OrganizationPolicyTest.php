<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_admin_can_update_their_organization_and_cannot_create_tenants(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        app(TenantContext::class)->set($organization);

        $this->assertTrue($admin->can('update', $organization));
        $this->assertFalse($admin->can('create', Organization::class));
        $this->assertFalse($admin->can('viewAny', Organization::class));
    }

    public function test_viewer_cannot_update_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);

        app(TenantContext::class)->set($organization);

        $this->assertTrue($viewer->can('view', $organization));
        $this->assertFalse($viewer->can('update', $organization));
    }

    public function test_super_admin_can_manage_organizations(): void
    {
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue($superAdmin->can('viewAny', Organization::class));
        $this->assertTrue($superAdmin->can('create', Organization::class));
        $this->assertTrue($superAdmin->can('delete', $organization));
    }
}
