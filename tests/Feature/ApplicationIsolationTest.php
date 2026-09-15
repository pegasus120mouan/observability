<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_application_list_does_not_include_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        Application::factory()->forOrganization($acme)->create(['name' => 'acme unique application']);
        Application::factory()->forOrganization($globex)->create(['name' => 'globex unique application']);

        $this->actingAsMember($admin, $acme)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSee('acme unique application')
            ->assertDontSee('globex unique application');
    }

    public function test_cross_tenant_application_show_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $foreign = Application::factory()->forOrganization($globex)->create(['name' => 'secret application']);

        $this->actingAsMember($admin, $acme)
            ->get(route('applications.show', $foreign))
            ->assertNotFound();
    }

    public function test_cross_tenant_application_update_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $foreign = Application::factory()->forOrganization($globex)->create(['name' => 'globex app']);

        $this->actingAsMember($admin, $acme)
            ->put(route('applications.update', $foreign), [
                'name' => 'hijacked',
                'type' => 'laravel',
                'environment' => 'production',
            ])
            ->assertNotFound();

        $this->assertSame(
            'globex app',
            Application::query()->withoutGlobalScopes()->find($foreign->id)?->name,
        );
    }
}
