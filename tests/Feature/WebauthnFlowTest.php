<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WebauthnFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
        $this->seed(RoleSeeder::class);
        config(['webauthn.rp_id' => 'localhost']);
    }

    public function test_registration_view_exposes_accessible_relative_configuration(): void
    {
        $user = $this->admin();

        $response = $this->withSession($this->pendingSession($user))
            ->get(route('webauthn.setup'));

        $response->assertOk()
            ->assertSee('data-webauthn-button', false)
            ->assertSee('data-mode="register"', false)
            ->assertSee('data-options-url="/mfa/webauthn/register/options"', false)
            ->assertSee('data-submit-url="/mfa/webauthn/register"', false)
            ->assertSee('data-auto-start="true"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('role="status"', false)
            ->assertSee('aria-describedby=', false);
    }

    public function test_registration_options_create_a_session_challenge(): void
    {
        $user = $this->admin();

        $response = $this->withSession($this->pendingSession($user))
            ->getJson(route('webauthn.register.options'));

        $response->assertOk()
            ->assertJsonStructure([
                'publicKey' => [
                    'challenge',
                    'rp' => ['id', 'name'],
                    'user' => ['id', 'name', 'displayName'],
                ],
            ])
            ->assertSessionHas('webauthn_register_challenge')
            ->assertSessionHas('webauthn_register_challenge_at');
    }

    public function test_invalid_registration_payload_returns_controlled_error_and_audits_failure(): void
    {
        $user = $this->admin();
        $this->withSession($this->pendingSession($user))
            ->getJson(route('webauthn.register.options'))
            ->assertOk();

        $this->postJson(route('webauthn.register'), [
            'response' => [],
        ])->assertUnprocessable()
            ->assertJsonStructure(['code', 'message']);

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'webauthn.failed',
            'severity' => 'warning',
            'status' => 422,
        ]);
    }

    public function test_login_options_and_view_are_available_after_totp_for_registered_passkey(): void
    {
        $user = $this->admin(withPasskey: true);
        $session = $this->pendingSession($user);

        $this->withSession($session)
            ->get(route('webauthn.login'))
            ->assertOk()
            ->assertSee('data-mode="login"', false)
            ->assertSee('data-options-url="/mfa/webauthn/login/options"', false)
            ->assertSee('data-submit-url="/mfa/webauthn/login"', false)
            ->assertSee('data-auto-start="true"', false);

        $this->withSession($session)
            ->getJson(route('webauthn.login.options'))
            ->assertOk()
            ->assertJsonStructure(['publicKey' => ['challenge', 'allowCredentials']])
            ->assertSessionHas('webauthn_login_challenge')
            ->assertSessionHas('webauthn_login_challenge_at');
    }

    public function test_email_otp_endpoints_are_not_registered(): void
    {
        $this->get('/mfa/email-otp')->assertNotFound();
        $this->get('/register/email-otp')->assertNotFound();
    }

    private function admin(bool $withPasskey = false): User
    {
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'totp_enabled_at' => now(),
        ]);

        if (! $withPasskey) {
            return $user;
        }

        $user->webauthnCredentials()->create([
            'name' => 'Test passkey',
            'credential_id_hash' => hash('sha512', 'credential-id'),
            'credential_id' => 'credential-id',
            'public_key' => 'public-key',
            'sign_count' => 0,
        ]);
        $user->forceFill(['webauthn_enabled_at' => now()])->save();

        return $user;
    }

    /**
     * @return array<string, int|string>
     */
    private function pendingSession(User $user): array
    {
        return [
            'auth_pending_user_id' => $user->id,
            'auth_pending_level' => 3,
            'auth_pending_started_at' => now()->timestamp,
            'auth_pending_totp_verified_at' => now()->timestamp,
        ];
    }
}
