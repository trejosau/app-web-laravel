<?php

namespace Tests\Feature;

use App\Models\RecoveryCode;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecoveryCodeLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
    }

    public function test_user_can_login_with_recovery_code_after_password(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'recovery_user',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $recoveryCode = $user->recoveryCodes()->create([
            'code_hash' => RecoveryCode::makeCodeHash('ABCDE-12345'),
        ]);

        $this->post('/login', [
            'username' => 'recovery_user',
            'password' => 'StrongPass123!',
        ]);

        $response = $this->post('/mfa/recovery-code', [
            'code' => 'ABCDE-12345',
        ]);

        $response->assertRedirect(route('dashboard.user'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($recoveryCode->fresh()->used_at);

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'recovery_code.used',
            'severity' => 'info',
            'status' => 200,
        ]);
    }
}
