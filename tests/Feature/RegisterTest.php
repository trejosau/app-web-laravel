<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
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

    public function test_register_form_can_be_viewed(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Minimo 12 caracteres')
            ->assertSee('Una mayuscula')
            ->assertSee('Una minuscula')
            ->assertSee('Un simbolo');
    }

    public function test_user_can_register_with_username_and_argon2id_password(): void
    {
        $this->seed(AdminUserSeeder::class);
        Mail::fake();

        $response = $this->post('/register', [
            'username' => 'Secure_User',
            'email' => 'secure_user@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('register.email-otp.show'));
        $this->assertGuest();
        Mail::assertSentCount(1);

        $user = User::query()->where('username', 'secure_user')->firstOrFail();

        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
        $this->assertTrue($user->hasRole(Role::GUEST));

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'user.registered',
            'severity' => 'info',
            'status' => 201,
        ]);

        $metadata = SecurityAuditLog::query()
            ->where('user_id', $user->id)
            ->where('event', 'user.registered')
            ->firstOrFail()
            ->metadata;

        $this->assertSame('guest', $metadata['actor']);
        $this->assertSame(Role::GUEST, $metadata['target_role']);
        $this->assertNotEmpty($metadata['registered_user_fingerprint']);
    }

    public function test_role_id_is_blocked_from_registration_request(): void
    {
        $this->seed(RoleSeeder::class);

        $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $response = $this->from('/register')->post('/register', [
            'username' => 'blocked_role',
            'email' => 'blocked_role@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role_id' => $adminRole->id,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', [
            'username' => 'blocked_role',
        ]);

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'register.failed',
            'severity' => 'warning',
            'status' => 422,
        ]);
    }

    public function test_is_admin_is_blocked_from_registration_request(): void
    {
        $this->seed(RoleSeeder::class);

        $response = $this->from('/register')->post('/register', [
            'username' => 'blocked_admin',
            'email' => 'blocked_admin@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'is_admin' => true,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('is_admin');

        $this->assertDatabaseMissing('users', [
            'username' => 'blocked_admin',
        ]);
    }

    public function test_duplicate_username_fails(): void
    {
        $this->seed(RoleSeeder::class);

        User::factory()->create([
            'username' => 'duplicate_user',
        ]);

        $response = $this->from('/register')->post('/register', [
            'username' => 'duplicate_user',
            'email' => 'duplicate_user_2@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('username');
    }

    public function test_weak_password_fails(): void
    {
        $this->seed(RoleSeeder::class);

        $response = $this->from('/register')->post('/register', [
            'username' => 'weak_password',
            'email' => 'weak_password@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
    }

    public function test_register_rate_limit_does_not_block_distinct_users_from_same_ip(): void
    {
        $this->seed(RoleSeeder::class);
        Mail::fake();

        for ($i = 0; $i < 4; $i++) {
            $this->from('/register')->post('/register', [
                'username' => 'rate_limited_'.$i,
                'email' => 'rate_limited_'.$i.'@example.test',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
            ])->assertRedirect(route('register.email-otp.show'));
        }

        Mail::assertSentCount(4);
    }

    public function test_register_rate_limit_is_applied_to_same_identity(): void
    {
        $this->seed(RoleSeeder::class);
        Mail::fake();

        $payload = [
            'username' => 'rate_limited_same',
            'email' => 'rate_limited_same@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->from('/register')->post('/register', $payload);
        }

        $response = $this->from('/register')->post('/register', $payload);

        $response->assertStatus(429);

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'rate_limit.blocked',
            'severity' => 'warning',
            'status' => 429,
        ]);
    }
}
