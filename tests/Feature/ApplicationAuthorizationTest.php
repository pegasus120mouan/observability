<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_viewer_can_open_applications_but_cannot_create_one(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        Application::factory()->forOrganization($organization)->create(['name' => 'visible application']);

        $this->actingAsMember($viewer, $organization)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSee('visible application');

        $this->actingAsMember($viewer, $organization)
            ->get(route('applications.create'))
            ->assertForbidden();

        $this->actingAsMember($viewer, $organization)
            ->post(route('applications.store'), [
                'name' => 'Forbidden application',
                'type' => 'laravel',
                'environment' => 'production',
            ])
            ->assertForbidden();
    }

    public function test_operator_cannot_edit_an_application(): void
    {
        $organization = Organization::factory()->create();
        $operator = $this->createMember(RoleName::Operator, $organization);
        $application = Application::factory()->forOrganization($organization)->create();

        $this->actingAsMember($operator, $organization)
            ->get(route('applications.show', $application))
            ->assertOk();

        $this->actingAsMember($operator, $organization)
            ->get(route('applications.edit', $application))
            ->assertForbidden();

        $this->actingAsMember($operator, $organization)
            ->put(route('applications.update', $application), [
                'name' => 'Should not save',
                'type' => 'laravel',
                'environment' => 'production',
            ])
            ->assertForbidden();
    }

    public function test_analyst_cannot_create_an_application(): void
    {
        $organization = Organization::factory()->create();
        $analyst = $this->createMember(RoleName::Analyst, $organization);

        $this->actingAsMember($analyst, $organization)
            ->post(route('applications.store'), [
                'name' => 'Analyst app',
                'type' => 'laravel',
                'environment' => 'production',
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_applications(): void
    {
        $this->get(route('applications.index'))->assertRedirect(route('login'));
    }
}
