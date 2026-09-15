<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_creating_a_user_writes_an_audit_log_without_the_password(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->post(route('users.store'), [
                'name' => 'Audited User',
                'email' => 'audited@example.test',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'role' => RoleName::Operator->value,
            ])
            ->assertRedirect(route('users.index'));

        $log = AuditLog::query()->where('action', AuditAction::UserCreated)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($organization->id, $log->organization_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('audited@example.test', $log->new_values['email'] ?? null);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertStringNotContainsString('secret-password', json_encode($log->new_values));
    }
}
