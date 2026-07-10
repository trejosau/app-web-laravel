<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TotpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TotpFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
    }

    public function test_user_can_complete_totp_login(): void
    {
        $this->seed(RoleSeeder::class);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'totp_enabled_user',
            'password' => Hash::make('StrongPass123!'),
            'totp_secret' => $secret,
            'totp_enabled_at' => now(),
        ]);

        $this->post('/login', [
            'username' => 'totp_enabled_user',
            'password' => 'StrongPass123!',
        ]);

        $response = $this->post('/mfa/totp', [
            'otp' => $totp->codeAt($secret, time()),
        ]);

        $response->assertRedirect(route('dashboard.user'));
        $this->assertAuthenticatedAs($user);
        $response->assertSessionMissing('auth_pending_user_id');
    }

    public function test_totp_setup_shows_qr_image_and_qr_route_returns_svg(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'qr_user',
        ]);

        $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 2,
            'auth_pending_started_at' => now()->timestamp,
        ]);

        $setupResponse = $this->get('/mfa/totp/setup');

        $setupResponse->assertOk();
        $setupResponse->assertSee(route('two-factor.qr'), false);
        $setupResponse->assertDontSee('otpauth://', false);

        $qrResponse = $this->get(route('two-factor.qr'));

        $qrResponse->assertOk();
        $qrResponse->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $qrResponse->assertSee('<svg', false);
    }

    public function test_admin_sees_continue_to_passkey_after_totp_setup_recovery_codes(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $response = $this->withSession([
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 3,
            'auth_pending_started_at' => now()->timestamp,
            'auth_pending_totp_verified_at' => now()->timestamp,
            'recovery_codes' => ['ABCDE-12345'],
        ])->get(route('mfa.recovery-codes'));

        $response->assertOk();
        $response->assertSee('Continuar con Passkey');
    }
}
