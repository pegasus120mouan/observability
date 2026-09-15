<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\ApplicationRequest;
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
            ->assertSee('Requests')
            ->assertSee('Errors')
            ->assertSee('Recent requests')
            ->assertSee('metric-chart')
            ->assertSee('Latency')
            ->assertSee('"type":"bar"', false)
            ->assertSee('"unit":"count"', false)
            ->assertSee('"unit":"ms"', false);
    }

    public function test_show_renders_recent_http_requests_and_live_payload(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create([
            'name' => 'Apache',
            'type' => ApplicationType::Apache,
            'runtime_stats' => [
                'req_per_sec' => 4.2,
                'busy_workers' => 2,
                'idle_workers' => 8,
            ],
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(5),
            'method' => 'GET',
            'resource' => '/index.php',
            'status_code' => 200,
            'duration_us' => 2740,
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(3),
            'method' => 'POST',
            'resource' => '/login',
            'status_code' => 500,
            'duration_us' => 18400,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('data-live-application', false)
            ->assertSee('/index.php')
            ->assertSee('/login')
            ->assertSee('GET')
            ->assertSee('POST')
            ->assertSee('2.74 ms')
            ->assertSee('p50 / p75 / p90 / p95 / p99 / Max')
            ->assertSee('Requests / sec')
            ->assertSee('Busy workers')
            ->assertSee('4.20');

        $this->actingAsMember($admin, $organization)
            ->getJson(route('applications.live', ['application' => $application, 'range' => '15m']))
            ->assertOk()
            ->assertJsonPath('summary.request_count', 2)
            ->assertJsonPath('summary.error_count', 1)
            ->assertJsonPath('charts.requests.type', 'bar')
            ->assertJsonPath('charts.latency.series.0.label', 'p50')
            ->assertJsonPath('recent.0.resource', '/login')
            ->assertJsonPath('recent.0.status_code', 500)
            ->assertJsonPath('runtime.req_per_sec', 4.2)
            ->assertJsonPath('runtime.busy_workers', 2);
    }

    public function test_application_show_escapes_http_resource(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create(['name' => 'Apache']);
        ApplicationRequest::factory()->forApplication($application)->create([
            'resource' => "<script>alert('xss')</script>",
            'method' => 'GET',
            'status_code' => 200,
        ]);

        $content = $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $content);
    }

    public function test_deleting_an_application_removes_its_metrics(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create();
        ApplicationMetric::factory()->forApplication($application)->create();
        ApplicationRequest::factory()->forApplication($application)->create();

        $this->actingAsMember($admin, $organization)
            ->delete(route('applications.destroy', $application))
            ->assertRedirect(route('applications.index'));

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationMetric::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationRequest::query()->withoutGlobalScopes()->count());
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
