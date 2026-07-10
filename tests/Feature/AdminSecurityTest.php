<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TotpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('PDO PostgreSQL driver is not available for feature tests.');
        }

        parent::setUp();
        $this->requirePostgresSchema();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_reauth_with_password_and_totp(): void
    {
        [$admin, $secret] = $this->adminWithTotp();

        $this->actingAs($admin)
            ->withSession(['auth_completed_mfa_level' => 3])
            ->post(route('admin.reauth.store'), [
                'password' => 'StrongPass123!',
                'otp' => app(TotpService::class)->codeAt($secret, time()),
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.reauthenticated',
        ]);
        $this->assertNotNull($admin->fresh()->totp_last_used_counter);
    }

    public function test_admin_critical_action_without_reauth_is_blocked(): void
    {
        [$admin] = $this->adminWithTotp();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession(['auth_completed_mfa_level' => 3])
            ->put(route('admin.users.block', $target))
            ->assertRedirect(route('admin.reauth'))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_reauth_failure_shows_specific_credentials_error(): void
    {
        [$admin] = $this->adminWithTotp();

        $this->actingAs($admin)
            ->withSession(['auth_completed_mfa_level' => 3])
            ->from(route('admin.reauth'))
            ->post(route('admin.reauth.store'), [
                'password' => 'WrongPass123!',
                'otp' => '123456',
            ])
            ->assertRedirect(route('admin.reauth'))
            ->assertSessionHasErrors(['password' => 'RBAC-004: Contrasena o codigo TOTP incorrectos.']);
    }

    public function test_admin_critical_action_with_reauth_is_allowed_and_audited(): void
    {
        [$admin] = $this->adminWithTotp();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession([
                'auth_completed_mfa_level' => 3,
                'admin_reauthenticated_at' => now()->timestamp,
            ])
            ->put(route('admin.users.block', $target))
            ->assertRedirect();

        $this->assertSame('locked', $target->fresh()->status);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $target->id,
            'event' => 'user.blocked',
        ]);
    }

    public function test_admin_timeout_expires_idle_session(): void
    {
        [$admin] = $this->adminWithTotp();

        $this->actingAs($admin)
            ->withSession([
                'auth_completed_mfa_level' => 3,
                'admin_last_activity_at' => now()->subMinutes(16)->timestamp,
            ])
            ->get(route('dashboard.admin'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'session.expired']);
    }

    public function test_admin_error_catalog_is_visible_to_admin_users(): void
    {
        [$admin] = $this->adminWithTotp();

        $this->actingAs($admin)
            ->withSession($this->adminSession())
            ->get(route('admin.error-catalog.index'))
            ->assertOk()
            ->assertSee('AUTH-001')
            ->assertSee('Info desarrollador');
    }

    public function test_admin_error_catalog_is_not_visible_to_guest_or_normal_user(): void
    {
        $this->get(route('admin.error-catalog.index'))->assertRedirect(route('login'));

        $userRole = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create(['role_id' => $userRole->id]);

        $this->actingAs($user)
            ->withSession(['auth_completed_mfa_level' => 2])
            ->get(route('admin.error-catalog.index'))
            ->assertForbidden();
    }

    public function test_admin_error_catalog_search_by_code_works(): void
    {
        [$admin] = $this->adminWithTotp();

        $this->actingAs($admin)
            ->withSession($this->adminSession())
            ->get(route('admin.error-catalog.index', ['code' => 'MFA-001']))
            ->assertOk()
            ->assertSee('MFA-001')
            ->assertDontSee('AUTH-001');
    }

    public function test_admin_user_detail_shows_sensitive_actions_without_executing_them(): void
    {
        [$admin] = $this->adminWithTotp();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession($this->adminSession())
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('Acciones sensibles')
            ->assertSee('Bloquear acceso')
            ->assertDontSee('Eliminar usuario');
    }

    private function adminWithTotp(string $username = 'admin_user'): array
    {
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $secret = app(TotpService::class)->generateSecret();
        $admin = User::factory()->create([
            'username' => $username,
            'role_id' => $role->id,
            'password' => Hash::make('StrongPass123!'),
            'totp_secret' => $secret,
            'totp_enabled_at' => now(),
        ]);

        return [$admin, $secret];
    }

    private function adminSession(): array
    {
        return [
            'auth_completed_mfa_level' => 3,
            'admin_last_activity_at' => now()->timestamp,
            'admin_reauthenticated_at' => now()->timestamp,
        ];
    }
}
