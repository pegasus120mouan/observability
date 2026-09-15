<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Host;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_host_list_does_not_include_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        $this->createMonitoredHost($acme, ['hostname' => 'acme-web-01']);
        $this->createMonitoredHost($globex, ['hostname' => 'globex-web-01']);

        $this->actingAsMember($admin, $acme)
            ->get(route('hosts.index'))
            ->assertOk()
            ->assertSee('acme-web-01')
            ->assertDontSee('globex-web-01');
    }

    public function test_cross_tenant_host_show_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex, ['hostname' => 'globex-secret']);

        $this->actingAsMember($admin, $acme)
            ->get(route('hosts.show', $foreignHost))
            ->assertNotFound();
    }

    public function test_cross_tenant_host_update_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        ['host' => $foreignHost] = $this->createMonitoredHost($globex);

        $this->actingAsMember($admin, $acme)
            ->put(route('hosts.update', $foreignHost), [
                'display_name' => 'Hijacked',
                'environment' => 'production',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('hosts', [
            'id' => $foreignHost->id,
            'display_name' => 'Hijacked',
        ]);
    }

    public function test_unscoped_queries_still_see_every_tenant_host(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $this->createMonitoredHost($acme, ['hostname' => 'acme-only']);
        $this->createMonitoredHost($globex, ['hostname' => 'globex-only']);

        $this->assertEqualsCanonicalizing(
            ['acme-only', 'globex-only'],
            Host::query()->withoutGlobalScopes()->orderBy('hostname')->pluck('hostname')->all()
        );
    }
}
