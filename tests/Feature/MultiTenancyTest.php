<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_users_index_does_not_include_another_organization_member(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $adminA = $this->createMember(RoleName::Admin, $organizationA);
        $adminB = $this->createMember(RoleName::Admin, $organizationB);

        $this->actingAsMember($adminA, $organizationA)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($adminA->email)
            ->assertDontSee($adminB->email);
    }

    public function test_editing_a_user_from_another_organization_returns_404(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $adminA = $this->createMember(RoleName::Admin, $organizationA);
        $adminB = $this->createMember(RoleName::Admin, $organizationB);

        $this->actingAsMember($adminA, $organizationA)
            ->get(route('users.edit', $adminB))
            ->assertNotFound();
    }

    public function test_updating_a_user_from_another_organization_returns_404(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $adminA = $this->createMember(RoleName::Admin, $organizationA);
        $adminB = $this->createMember(RoleName::Admin, $organizationB);

        $this->actingAsMember($adminA, $organizationA)
            ->put(route('users.update', $adminB), [
                'name' => 'Hijacked',
                'email' => $adminB->email,
                'role' => RoleName::Viewer->value,
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->assertSame($adminB->name, $adminB->fresh()->name);
    }

    public function test_organization_names_are_escaped_in_the_users_page(): void
    {
        $organization = Organization::factory()->create([
            'name' => '<script>alert("xss")</script>',
        ]);
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }
}
