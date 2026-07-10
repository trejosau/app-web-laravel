<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReauthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabaseSchema();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_browse_users_without_reauthentication(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->adminSession())
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_sensitive_user_action_requires_recent_reauthentication(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession($this->adminSession())
            ->put(route('admin.users.block', $target))
            ->assertRedirect(route('admin.reauth'));

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_recent_reauthentication_allows_blocking_another_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession($this->adminSession() + ['admin_reauthenticated_at' => now()->timestamp])
            ->put(route('admin.users.block', $target))
            ->assertRedirect();

        $this->assertSame('locked', $target->fresh()->status);
    }

    public function test_admin_reauthentication_request_validates_required_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->adminSession())
            ->from(route('admin.reauth'))
            ->post(route('admin.reauth.store'), [])
            ->assertRedirect(route('admin.reauth'))
            ->assertSessionHasErrors(['password', 'otp']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('name', Role::ADMIN)->value('id'),
            'password' => Hash::make('StrongPass123!'),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function adminSession(): array
    {
        return [
            'auth_completed_mfa_level' => 3,
            'admin_last_activity_at' => now()->timestamp,
        ];
    }
}
