<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/overview')->assertRedirect(route('login'));
    }

    public function test_login_page_is_visible(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(config('platform.name'));
    }

    public function test_valid_credentials_authenticate_and_redirect_to_overview(): void
    {
        $user = $this->createMember(RoleName::Admin);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('overview'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $user = $this->createMember(RoleName::Admin);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_users_cannot_authenticate(): void
    {
        $user = $this->createMember(RoleName::Admin);
        $user->update(['status' => UserStatus::Suspended]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_without_an_organization_cannot_authenticate(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->createMember(RoleName::Admin);

        $this->actingAsMember($user, $user->organizations()->first())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_successful_login_writes_an_audit_log(): void
    {
        $user = $this->createMember(RoleName::Admin);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login',
        ]);

        $this->assertNull(AuditLog::query()->where('user_id', $user->id)->value('new_values'));
    }
}
