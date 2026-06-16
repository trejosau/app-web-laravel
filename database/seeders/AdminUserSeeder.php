<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * @var array<string, array{email_env: string, password_env: string, role: string}>
     */
    public const USERS = [
        'jorgeibarra' => [
            'email_env' => 'SEED_USER_JORGEIBARRA_EMAIL',
            'password_env' => 'SEED_USER_JORGEIBARRA_PASSWORD',
            'role' => Role::ADMIN,
        ],
        'sautrejo' => [
            'email_env' => 'SEED_USER_SAUTREJO_EMAIL',
            'password_env' => 'SEED_USER_SAUTREJO_PASSWORD',
            'role' => Role::ADMIN,
        ],
        'saul' => [
            'email_env' => 'SEED_USER_SAUL_EMAIL',
            'password_env' => 'SEED_USER_SAUL_PASSWORD',
            'role' => Role::USER,
        ],
        'jesus' => [
            'email_env' => 'SEED_USER_JESUS_EMAIL',
            'password_env' => 'SEED_USER_JESUS_PASSWORD',
            'role' => Role::USER,
        ],
        'dani' => [
            'email_env' => 'SEED_USER_DANI_EMAIL',
            'password_env' => 'SEED_USER_DANI_PASSWORD',
            'role' => Role::GUEST,
        ],
        'aza' => [
            'email_env' => 'SEED_USER_AZA_EMAIL',
            'password_env' => 'SEED_USER_AZA_PASSWORD',
            'role' => Role::GUEST,
        ],
    ];

    /**
     * @return array<string, array{email: string|null, password: string, role: string}>
     */
    public static function accounts(): array
    {
        $accounts = [];

        foreach (self::USERS as $username => $data) {
            $accounts[$username] = [
                'email' => self::nullableEnv($data['email_env']),
                'password' => self::passwordFromEnv($data['password_env'], $username),
                'role' => $data['role'],
            ];
        }

        return $accounts;
    }

    /**
     * Seed the fixed login users.
     */
    public function run(): void
    {
        $roles = [];

        $accounts = self::accounts();

        foreach (array_unique(array_column($accounts, 'role')) as $role) {
            $roles[$role] = $this->roleFor($role);
        }

        foreach ($accounts as $username => $data) {
            $this->seedUser($roles[$data['role']], $username, $data['email'], $data['password']);
        }
    }

    private function roleFor(string $role): Role
    {
        $roles = [
            Role::GUEST => ['description' => 'Basic dashboard access', 'required_mfa_level' => 1],
            Role::USER => ['description' => 'User dashboard access with TOTP', 'required_mfa_level' => 2],
            Role::ADMIN => ['description' => 'Admin dashboard access with TOTP and WebAuthn', 'required_mfa_level' => 3],
        ];

        return Role::query()->updateOrCreate(['name' => $role], $roles[$role]);
    }

    private function seedUser(Role $role, string $username, ?string $email, string $password): void
    {
        $username = mb_strtolower(trim($username));
        $email = is_string($email) && $email !== '' ? mb_strtolower(trim($email)) : null;

        $user = User::query()
            ->withTrashed()
            ->where('username', $username)
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->first() ?? new User();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'username' => $username,
            'role_id' => $role->id,
            'email' => $email,
            'email_verified_at' => $email !== null ? ($user->email_verified_at ?? now()) : null,
            'password' => Hash::make($password),
            'status' => 'active',
            'locked_until' => null,
            'password_changed_at' => now(),
        ])->save();
    }

    private static function nullableEnv(string $key): ?string
    {
        $value = env($key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function passwordFromEnv(string $key, string $username): string
    {
        $password = env($key);

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException("Missing seeded password env variable for {$username}: {$key}.");
        }

        return $password;
    }
}
