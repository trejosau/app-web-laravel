<?php

namespace Tests\Feature;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
        $this->seed(RoleSeeder::class);
    }

    public function test_password_reset_request_is_generic_and_audited(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'missing@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'password.reset.requested',
            'status' => 202,
        ]);
    }

    public function test_password_reset_valid_token_changes_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-valid@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('OldStrongPass123!'),
        ]);
        $token = '123456';
        $this->storeToken($user, $token);

        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewStrongPass123!', $user->fresh()->password));
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'password.reset.completed']);

        $metadata = SecurityAuditLog::query()
            ->where('event', 'password.reset.completed')
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->metadata;

        $this->assertSame(hash_hmac('sha256', $token, (string) config('app.key')), $metadata['token_fingerprint']);
        $this->assertNotContains($token, $metadata);
    }

    public function test_password_reset_rejects_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset-invalid@example.test']);

        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => '654321',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('security_audit_logs', ['event' => 'token.invalid']);
    }

    public function test_password_reset_rejects_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'reset-expired@example.test']);
        $token = '234567';
        $this->storeToken($user, $token, now()->subHours(2));

        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('security_audit_logs', ['event' => 'token.expired']);
    }

    public function test_password_reset_rejects_reused_token(): void
    {
        $user = User::factory()->create(['email' => 'reset-reused@example.test']);
        $token = '345678';
        $this->storeToken($user, $token);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ];

        $this->post('/reset-password', $payload)->assertRedirect(route('login'));
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_password_reset_rejects_token_for_another_user(): void
    {
        $owner = User::factory()->create(['email' => 'reset-owner@example.test']);
        $other = User::factory()->create(['email' => 'reset-other@example.test']);
        $token = '456789';
        $this->storeToken($owner, $token);

        $this->post('/reset-password', [
            'email' => $other->email,
            'token' => $token,
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])->assertSessionHasErrors('email');
    }

    private function storeToken(User $user, string $token, ?Carbon $createdAt = null): void
    {
        $createdAt ??= now();
        $values = [
            'email' => $user->email,
            'token' => hash_hmac('sha256', $token, (string) config('app.key')),
            'created_at' => $createdAt,
        ];

        if (Schema::hasColumn('password_reset_tokens', 'user_id')) {
            $values['user_id'] = $user->id;
        }

        if (Schema::hasColumn('password_reset_tokens', 'expires_at')) {
            $values['expires_at'] = $createdAt->copy()->addMinutes((int) config('auth.passwords.users.expire', 60));
        }

        if (Schema::hasColumn('password_reset_tokens', 'used_at')) {
            $values['used_at'] = null;
        }

        DB::table('password_reset_tokens')->insert($values);
    }
}
