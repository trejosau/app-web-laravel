<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        $roles = [
            ['name' => Role::GUEST, 'description' => 'Basic dashboard access', 'required_mfa_level' => 1],
            ['name' => Role::USER, 'description' => 'User dashboard access with TOTP', 'required_mfa_level' => 2],
            ['name' => Role::ADMIN, 'description' => 'Admin dashboard access with TOTP and email OTP', 'required_mfa_level' => 3],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
