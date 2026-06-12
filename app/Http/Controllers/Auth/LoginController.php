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
                ->withErrors(['username' => config('security_errors.auth.failed.code').': '.config('security_errors.auth.failed.userInfo')]);
        }

        if ($result['mfa_pending']) {
            return redirect()->route('mfa.pending');
        }

        return redirect()->route('dashboard.guest')->with('status', 'Sesion iniciada.');
    }
}
