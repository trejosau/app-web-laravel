<?php

namespace Tests\Feature;

use App\Models\RecoveryCode;
use App\Models\Role;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\TotpService;
use App\Services\WebauthnService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    public function test_profile_page_shows_global_navbar(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'username' => 'profile_user',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Perfil')
            ->assertSee('Cerrar sesion')
            ->assertSee('profile_user');
    }

    public function test_user_can_change_password(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'StrongPass123!',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('NewStrongPass123!', $user->fresh()->password));

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'password.changed',
            'severity' => 'info',
            'status' => 200,
        ]);
    }

    public function test_totp_cannot_be_disabled(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertFalse(Route::has('totp.destroy'));
        $this->delete('/mfa/totp')->assertStatus(405);
    }

    public function test_user_can_update_totp_with_password_and_current_code(): void
    {
        $this->seed(RoleSeeder::class);

        $totp = app(TotpService::class);
        $oldSecret = $totp->generateSecret();
        $newSecret = $totp->generateSecret();
        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('StrongPass123!'),
            'totp_secret' => $oldSecret,
            'totp_enabled_at' => now(),
        ]);
        $user->recoveryCodes()->create(['code_hash' => RecoveryCode::makeCodeHash('ABCDE-12345')]);

        $this->actingAs($user)
            ->post(route('totp.setup.confirm'), [
                'current_password' => 'StrongPass123!',
                'current_otp' => $totp->codeAt($oldSecret, time()),
            ])
            ->assertRedirect(route('totp.setup'))
            ->assertSessionHas('status', 'Verificacion actual confirmada. Configura el nuevo TOTP.');

        $this->actingAs($user)
            ->withSession([
                'totp_update_verified_at' => now()->timestamp,
                'totp_setup_secret' => $newSecret,
                'totp_setup_started_at' => now()->timestamp,
            ])
            ->post(route('totp.setup.confirm'), [
                'otp' => $totp->codeAt($newSecret, time()),
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', config('security_errors.mfa.totp_updated.userInfo'));

        $fresh = $user->fresh();
        $this->assertSame($newSecret, $fresh->totp_secret);
        $this->assertNotNull($fresh->totp_enabled_at);
        $this->assertSame(8, $fresh->recoveryCodes()->count());
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'totp.updated']);
    }

    public function test_totp_update_shows_current_verification_before_new_qr(): void
    {
        $this->seed(RoleSeeder::class);

        $totp = app(TotpService::class);
        $oldSecret = $totp->generateSecret();
        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('StrongPass123!'),
            'totp_secret' => $oldSecret,
            'totp_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('totp.setup'))
            ->assertOk()
            ->assertSee('Confirmar TOTP actual')
            ->assertDontSee('Clave manual')
            ->assertDontSee(route('two-factor.qr'), false);
    }

    public function test_totp_update_reports_reused_current_code(): void
    {
        $this->seed(RoleSeeder::class);

        $totp = app(TotpService::class);
        $oldSecret = $totp->generateSecret();
        $usedCounter = intdiv(time(), (int) config('totp.period'));
        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('StrongPass123!'),
            'totp_secret' => $oldSecret,
            'totp_enabled_at' => now(),
            'totp_last_used_counter' => $usedCounter,
        ]);

        $this->actingAs($user)
            ->from(route('totp.setup'))
            ->post(route('totp.setup.confirm'), [
                'current_password' => 'StrongPass123!',
                'current_otp' => $totp->codeAt($oldSecret, time()),
            ])
            ->assertRedirect(route('totp.setup'))
            ->assertSessionHas('error', 'No se pudo actualizar el TOTP.')
            ->assertSessionHasErrors(['current_otp' => 'MFA-008: Ese codigo TOTP ya fue usado. Espera el siguiente codigo.']);
    }

    public function test_last_passkey_cannot_be_removed(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'webauthn_enabled_at' => now(),
        ]);
        $credential = $this->createPasskey($user, 'only-passkey');

        $this->actingAs($user)
            ->withSession([
                'auth_completed_mfa_level' => 3,
                'account_reauthenticated_at' => now()->timestamp,
            ])
            ->delete(route('webauthn.destroy', $credential))
            ->assertRedirect()
            ->assertSessionHasErrors('passkey');

        $this->assertSame(1, $user->webauthnCredentials()->count());
    }

    public function test_additional_passkey_can_be_added(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'webauthn_enabled_at' => now(),
        ]);
        $this->createPasskey($user, 'existing-passkey');

        $this->mock(WebauthnService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('register')->once()->andReturnUsing(function (User $target, Request $request, array $payload) use ($user): void {
                $this->assertTrue($target->is($user));
                $this->createPasskey($target, 'new-passkey');
            });
        });

        $this->actingAs($user)
            ->withSession([
                'auth_completed_mfa_level' => 3,
                'account_reauthenticated_at' => now()->timestamp,
            ])
            ->post(route('webauthn.register'), [])
            ->assertOk()
            ->assertJsonPath('redirect', route('profile.show'));

        $this->assertSame(2, $user->webauthnCredentials()->count());
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'webauthn.registered']);
    }

    public function test_non_admin_cannot_add_passkey(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->withSession([
                'auth_completed_mfa_level' => 2,
                'account_reauthenticated_at' => now()->timestamp,
            ])
            ->get(route('webauthn.setup'))
            ->assertForbidden();
    }

    public function test_passkey_setup_requires_account_reauth(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->withSession(['auth_completed_mfa_level' => 3])
            ->get(route('webauthn.setup'))
            ->assertRedirect(route('account.reauth'));
    }

    public function test_recovery_codes_regenerate_requires_account_reauth(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'totp_secret' => app(TotpService::class)->generateSecret(),
            'totp_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['auth_completed_mfa_level' => 2])
            ->post(route('mfa.recovery-codes.regenerate'))
            ->assertRedirect(route('account.reauth'));
    }

    public function test_recovery_email_update_requires_account_reauth(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth_completed_mfa_level' => 1])
            ->put(route('profile.email.update'), [
                'email' => 'new_recovery@example.test',
            ])
            ->assertRedirect(route('account.reauth'));
    }

    private function createPasskey(User $user, string $credentialId): WebauthnCredential
    {
        return $user->webauthnCredentials()->create([
            'name' => 'Passkey',
            'credential_id_hash' => WebauthnCredential::makeCredentialIdHash($credentialId),
            'credential_id' => $credentialId,
            'public_key' => 'public-key',
            'sign_count' => 0,
            'transports' => [],
            'last_used_at' => now(),
        ]);
    }
}
