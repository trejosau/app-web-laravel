<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MfaPendingSessionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('PDO PostgreSQL driver is not available for feature tests.');
        }

        parent::setUp();
        $this->requirePostgresSchema();
    }

    public function test_mfa_pending_session_expires(): void
    {
        $this->withSession([
            'auth_pending_user_id' => (string) Str::uuid(),
            'auth_pending_level' => 2,
            'auth_pending_started_at' => now()->subMinutes(6)->timestamp,
        ]);

        $response = $this->get('/mfa/pending');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('auth_pending_user_id');
    }

    public function test_dashboard_is_blocked_when_mfa_is_pending(): void
    {
        $this->withSession([
            'auth_pending_user_id' => (string) Str::uuid(),
            'auth_pending_level' => 2,
            'auth_pending_started_at' => now()->timestamp,
        ]);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('mfa.pending'));
    }

    public function test_logout_clears_mfa_pending_session(): void
    {
        $this->withSession([
            'auth_pending_user_id' => (string) Str::uuid(),
            'auth_pending_level' => 2,
            'auth_pending_started_at' => now()->timestamp,
        ]);

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('auth_pending_user_id');
    }

    public function test_user_role_password_step_marks_credentials_valid_but_not_authenticated(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'mfa_contract',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->post('/login', [
            'username' => 'mfa_contract',
            'password' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('mfa.pending'));
        $this->assertGuest();
        $response->assertSessionHas('auth_pending_user_id', $user->id);
    }

    public function test_mfa_pending_redirects_to_totp_setup_when_totp_is_missing(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $response = $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 2,
            'auth_pending_started_at' => now()->timestamp,
        ])->get('/mfa/pending');

        $response->assertRedirect(route('totp.setup'));
    }

    public function test_mfa_pending_redirects_to_totp_verify_when_totp_exists(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'totp_secret' => 'TOTPSECRET',
            'totp_enabled_at' => now(),
        ]);

        $response = $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 2,
            'auth_pending_started_at' => now()->timestamp,
        ])->get('/mfa/pending');

        $response->assertRedirect(route('totp.login'));
    }

    public function test_mfa_pending_redirects_to_email_otp_after_totp(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'totp_secret' => 'TOTPSECRET',
            'totp_enabled_at' => now(),
        ]);

        $response = $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 3,
            'auth_pending_started_at' => now()->timestamp,
            'auth_pending_totp_verified_at' => now()->timestamp,
        ])->get('/mfa/pending');

        $response->assertRedirect(route('email-otp.show'));
    }

    public function test_email_otp_requires_totp_verified_pending_session(): void
    {
        $this->seed(RoleSeeder::class);
        Mail::fake();

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 3,
            'auth_pending_started_at' => now()->timestamp,
            'auth_pending_totp_verified_at' => now()->timestamp,
        ])->get(route('email-otp.show'))
            ->assertOk()
            ->assertSee('OTP por correo');
    }

    public function test_email_otp_is_not_available_without_totp_verified_pending_session(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'email_otp_admin',
        ]);

        $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 3,
            'auth_pending_started_at' => now()->timestamp,
        ])->get(route('email-otp.show'))->assertNotFound();
    }
}
