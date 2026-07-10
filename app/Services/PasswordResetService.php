<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        private readonly SecurityAuditService $auditService,
        private readonly UserSessionService $sessions
    ) {}

    public function request(string $email, Request $request): void
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->whereNotNull('email_verified_at')
            ->first();

        $this->auditService->log($request, 'password.reset.requested', 'info', 202, $user?->id);

        if ($user === null) {
            return;
        }

        $token = (string) random_int(100000, 999999);

        $values = [
            'token' => $this->tokenHash($token),
            'created_at' => now(),
        ];

        if ($this->hasColumn('user_id')) {
            $values['user_id'] = $user->id;
        }

        if ($this->hasColumn('expires_at')) {
            $values['expires_at'] = now()->addMinutes((int) config('auth.passwords.users.expire', 60));
        }

        if ($this->hasColumn('used_at')) {
            $values['used_at'] = null;
        }

        DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], $values);

        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        Mail::raw("PIN para restablecer tu contraseña: {$token}\n\nContinúa aquí:\n{$url}", function (Message $message) use ($user): void {
            $message->to($user->email)->subject('Restablece tu contraseña');
        });

        $this->auditService->log($request, 'token.issued', 'info', 202, $user->id, [
            'purpose' => 'password_reset',
            'token_fingerprint' => $this->tokenHash($token),
        ]);
    }

    /**
     * @param  array{email: string, token: string, password: string}  $data
     */
    public function reset(array $data, Request $request): string
    {
        $row = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->where('token', $this->tokenHash($data['token']))
            ->first();

        if ($row === null || (property_exists($row, 'used_at') && $row->used_at !== null)) {
            $this->auditService->log($request, 'token.invalid', 'warning', 422, null, [
                'purpose' => 'password_reset',
                'token_fingerprint' => $this->tokenHash($data['token']),
            ]);

            return 'invalid';
        }

        $expiresAt = property_exists($row, 'expires_at') && $row->expires_at !== null
            ? $row->expires_at
            : Carbon::parse($row->created_at)->addMinutes((int) config('auth.passwords.users.expire', 60));

        if (now()->greaterThan($expiresAt)) {
            $this->auditService->log($request, 'token.expired', 'warning', 422, property_exists($row, 'user_id') && $row->user_id ? (string) $row->user_id : null, [
                'purpose' => 'password_reset',
                'token_fingerprint' => $this->tokenHash($data['token']),
            ]);

            return 'expired';
        }

        $user = User::query()
            ->where('email', $data['email'])
            ->when(property_exists($row, 'user_id') && $row->user_id, fn ($query) => $query->whereKey($row->user_id))
            ->first();

        if ($user === null) {
            $this->auditService->log($request, 'token.invalid', 'warning', 422, null, [
                'purpose' => 'password_reset_user',
                'token_fingerprint' => $this->tokenHash($data['token']),
            ]);

            return 'invalid';
        }

        DB::transaction(function () use ($user, $data, $row, $request): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            if ($this->hasColumn('used_at')) {
                DB::table('password_reset_tokens')
                    ->where('email', $row->email)
                    ->update(['used_at' => now()]);
            }

            DB::table('password_reset_tokens')
                ->where('email', $row->email)
                ->delete();

            $this->sessions->deleteOtherSessions($user, $request);
            $this->auditService->log($request, 'password.reset.completed', 'info', 200, $user->id, [
                'token_fingerprint' => $this->tokenHash($data['token']),
            ]);
        });

        return 'completed';
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function hasColumn(string $column): bool
    {
        return Schema::hasColumn('password_reset_tokens', $column);
    }
}
