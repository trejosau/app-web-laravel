<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthService $authService): RedirectResponse
    {
        $result = $authService->login($request->validated(), $request);

        if (! $result['credentials_valid']) {
            return back()
                ->withInput($request->safe()->only('username'))
                ->withErrors($this->loginError($result['failure_reason']));
        }

        if ($result['mfa_pending']) {
            return redirect()->route('mfa.pending');
        }

        return redirect()->route('dashboard.guest')->with('status', 'Sesión iniciada.');
    }

    /**
     * Return a specific, user-facing error without exposing technical error codes.
     *
     * @return array<string, string>
     */
    private function loginError(?string $failureReason): array
    {
        return match ($failureReason) {
            'user_not_found' => ['username' => __('auth.login.user_not_found')],
            'invalid_password' => ['password' => __('auth.login.invalid_password')],
            'account_unavailable' => ['username' => __('auth.login.account_unavailable')],
            default => ['username' => __('auth.login.failed')],
        };
    }
}
