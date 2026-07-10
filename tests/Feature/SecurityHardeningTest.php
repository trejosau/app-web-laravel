<?php

namespace Tests\Feature;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\SecurityAuditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
        $this->seed(RoleSeeder::class);
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_laravel_session_is_encrypted_and_named(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame('laravel_session', config('session.cookie'));
    }

    public function test_auth_forms_do_not_use_browser_required_attribute(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee(' required', false);

        $this->get('/register')
            ->assertOk()
            ->assertDontSee(' required', false);
    }

    public function test_login_username_regex_rejects_invalid_input(): void
    {
        $this->from('/login')->post('/login', [
            'username' => '../bad',
            'password' => 'StrongPass123!',
        ])->assertSessionHasErrors('username');
    }

    public function test_user_fillable_blocks_sensitive_mass_assignment(): void
    {
        $user = new User;
        $user->fill([
            'username' => 'mass_assignment',
            'email' => 'mass_assignment@example.test',
            'role_id' => (string) Str::uuid(),
            'status' => 'locked',
            'totp_secret' => 'SECRET',
        ]);

        $this->assertNull($user->role_id);
        $this->assertNull($user->status);
        $this->assertNull($user->totp_secret);
    }

    public function test_recaptcha_can_be_validated_when_enabled(): void
    {
        config()->set('recaptcha.enabled', true);
        config()->set('recaptcha.secret_key', 'test-secret');
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $this->post('/register', [
            'username' => 'captcha_user',
            'email' => 'captcha_user@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'g-recaptcha-response' => 'captcha-token',
        ])->assertRedirect('/');
    }

    public function test_audit_log_hash_chain_is_generated_when_columns_exist(): void
    {
        if (! Schema::hasColumn('security_audit_logs', 'current_hash')) {
            $this->markTestSkipped('Audit hash columns are pending migration in the configured database.');
        }

        app(SecurityAuditService::class)->log(request(), 'test.event.one');
        app(SecurityAuditService::class)->log(request(), 'test.event.two');

        $logs = SecurityAuditLog::query()->whereIn('event', ['test.event.one', 'test.event.two'])->orderBy('id')->get();

        $this->assertNotNull($logs[0]->current_hash);
        $this->assertSame($logs[0]->current_hash, $logs[1]->previous_hash);
    }

    public function test_error_dictionary_returns_safe_user_messages(): void
    {
        $error = config('security_errors.passkey.required');

        $this->assertSame(['code', 'userInfo', 'supportInfo', 'developerInfo'], array_keys($error));
        $this->assertSame('PASSKEY-003', $error['code']);
        $this->assertArrayNotHasKey('trace', $error);
        $this->assertStringNotContainsString('Exception', $error['userInfo']);
    }
}
