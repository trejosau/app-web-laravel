<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
    }

    public function test_login_form_can_be_viewed(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guest_user_can_login_with_password_only(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'username' => 'guest_user',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->post('/login', [
            'username' => 'guest_user',
            'password' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('dashboard.guest'));
        $this->assertAuthenticatedAs($user);
        $response->assertSessionMissing('auth_pending_user_id');

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'login.success',
            'severity' => 'info',
            'status' => 200,
        ]);
    }

    public function test_user_role_goes_to_mfa_pending_after_password(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'totp_user',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->post('/login', [
            'username' => 'totp_user',
            'password' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('mfa.pending'));
        $this->assertGuest();
        $response->assertSessionHas('auth_pending_user_id', $user->id);
        $response->assertSessionHas('auth_pending_level', 2);
        $this->assertFalse(auth()->check());

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'login.mfa_required',
            'severity' => 'info',
            'status' => 202,
        ]);
    }

    public function test_seeded_login_users_accept_their_generated_passwords(): void
    {
        $this->seed(AdminUserSeeder::class);

        foreach (AdminUserSeeder::accounts() as $username => $account) {
            $user = User::query()->where('username', $username)->firstOrFail();

            $this->assertSame($account['email'], $user->email);
            $this->assertTrue($user->hasRole($account['role']));
            $this->assertTrue(Hash::check($account['password'], $user->password));

            $response = $this->from('/login')->post('/login', [
                'username' => $username,
                'password' => $account['password'],
            ]);

            if ($account['role'] === Role::GUEST) {
                $response->assertRedirect(route('dashboard.guest'));
                $this->assertAuthenticatedAs($user);
                $response->assertSessionMissing('auth_pending_user_id');
            } else {
                $response->assertRedirect(route('mfa.pending'));
                $this->assertGuest();
                $response->assertSessionHas('auth_pending_user_id', $user->id);
                $response->assertSessionHas('auth_pending_level', $account['role'] === Role::ADMIN ? 3 : 2);
            }

            auth()->logout();
            $this->flushSession();
        }
    }

    public function test_invalid_credentials_show_generic_error(): void
    {
        $this->seed(RoleSeeder::class);

        User::factory()->create([
            'username' => 'valid_user',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'valid_user',
            'password' => 'WrongPass123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('AUTH-001', session('errors')->first('username'));
        $this->assertGuest();

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'login.failed',
            'severity' => 'warning',
            'status' => 422,
        ]);
    }

    public function test_login_rate_limit_is_applied(): void
    {
        $this->seed(RoleSeeder::class);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'username' => 'missing_user',
                'password' => 'WrongPass123!',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'username' => 'missing_user',
            'password' => 'WrongPass123!',
        ]);

        $response->assertStatus(429);

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'rate_limit.blocked',
            'severity' => 'warning',
            'status' => 429,
        ]);
    }

    public function test_login_success_revokes_other_sessions_and_audits_fingerprints(): void
    {
        config()->set('session.driver', 'database');
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'username' => 'session_user',
            'password' => Hash::make('StrongPass123!'),
        ]);

        DB::table('sessions')->insert([
            'id' => 'other-browser-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Other Browser',
            'payload' => 'encrypted-payload',
            'last_activity' => now()->subMinute()->timestamp,
        ]);

        $this->post('/login', [
            'username' => 'session_user',
            'password' => 'StrongPass123!',
        ])->assertRedirect(route('dashboard.guest'));

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-browser-session',
        ]);

        $metadata = SecurityAuditLog::query()
            ->where('user_id', $user->id)
            ->where('event', 'login.success')
            ->latest('created_at')
            ->firstOrFail()
            ->metadata;

        $this->assertSame(1, $metadata['revoked_sessions']);
        $this->assertNotEmpty($metadata['current_session_fingerprint']);
        $this->assertNotSame('other-browser-session', $metadata['revoked_session_fingerprints'][0]);
    }

    public function test_logout_uses_post_and_invalidates_session(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['keep_me' => 'no'])
            ->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('keep_me');

        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'logout.success',
        ]);
    }

    public function test_get_logout_is_not_allowed(): void
    {
        $this->get('/logout')->assertStatus(405);
    }
}
