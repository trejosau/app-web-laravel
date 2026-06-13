<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
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

    public function test_guest_can_access_basic_dashboard_only(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 1])->get('/dashboard')->assertRedirect(route('dashboard.guest'));
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 1])->get('/dashboard/guest')->assertOk()->assertSee('Panel guest');
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 1])->get('/dashboard/guess')->assertRedirect(route('dashboard.guest'));
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 1])->get('/dashboard/user')->assertForbidden();
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 1])->get('/dashboard/admin')->assertForbidden();
    }

    public function test_user_can_access_basic_and_user_dashboards_only(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 2])->get('/dashboard')->assertRedirect(route('dashboard.user'));
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 2])->get('/dashboard/guest')->assertOk();
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 2])->get('/dashboard/user')->assertOk()->assertSee('Panel user');
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 2])->get('/dashboard/admin')->assertForbidden();
    }

    public function test_admin_can_access_all_dashboards(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 3, 'admin_last_activity_at' => now()->timestamp])->get('/dashboard')->assertRedirect(route('dashboard.admin'));
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 3, 'admin_last_activity_at' => now()->timestamp])->get('/dashboard/guest')->assertOk();
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 3, 'admin_last_activity_at' => now()->timestamp])->get('/dashboard/user')->assertOk();
        $this->actingAs($user)->withSession(['auth_completed_mfa_level' => 3, 'admin_last_activity_at' => now()->timestamp])->get('/dashboard/admin')->assertOk()->assertSee('Panel admin');
    }

    public function test_user_without_completed_mfa_cannot_access_user_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get('/dashboard/user')->assertForbidden();
    }

    public function test_admin_without_completed_mfa_cannot_access_admin_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get('/dashboard/admin')->assertForbidden();
    }
}
