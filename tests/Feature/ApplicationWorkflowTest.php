<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_creating_an_application_records_an_audit(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $response = $this->actingAsMember($admin, $organization)
            ->post(route('applications.store'), [
                'name' => 'orders-api',
                'type' => 'laravel',
                'environment' => 'production',
                'endpoint' => 'https://api.acme.test/orders',
            ]);

        $application = Application::query()->withoutGlobalScopes()->where('name', 'orders-api')->first();

        $this->assertNotNull($application);
        $response->assertRedirect(route('applications.show', $application));
        $this->assertSame('orders-api', $application->slug);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationCreated->value,
            'resource_id' => $application->id,
        ]);
    }

    public function test_name_is_required_to_create_an_application(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->from(route('applications.create'))
            ->post(route('applications.store'), [
                'type' => 'laravel',
                'environment' => 'production',
            ])
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
    }

    public function test_show_renders_request_and_error_stats(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create(['name' => 'orders-api']);
        ApplicationMetric::factory()->forApplication($application)->create([
            'request_count' => 12542,
            'error_count' => 100,
            'response_time_avg' => 182,
            'response_time_p95' => 421,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('orders-api')
            ->assertSee('12,542')
            ->assertSee('182 ms')
            ->assertSee('421 ms')
            ->assertSee('Successful requests')
            ->assertSee('Failed requests')
            ->assertSee('metric-chart')
            ->assertSee('Latency')
            ->assertSee('"type":"bar"', false)
            ->assertSee('"unit":"count"', false)
            ->assertSee('"unit":"ms"', false);
    }

    public function test_deleting_an_application_removes_its_metrics(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create();
        ApplicationMetric::factory()->forApplication($application)->create();

        $this->actingAsMember($admin, $organization)
            ->delete(route('applications.destroy', $application))
            ->assertRedirect(route('applications.index'));

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationMetric::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationDeleted->value,
        ]);
    }

    public function test_application_show_escapes_name(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create([
            'name' => "<script>alert('xss')</script>",
        ]);

        $content = $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $content);
    }
}
