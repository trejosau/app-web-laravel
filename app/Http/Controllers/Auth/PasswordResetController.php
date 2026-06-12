<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function send(ForgotPasswordRequest $request, PasswordResetService $passwordReset): RedirectResponse
    {
        $passwordReset->request($request->validated('email'), $request);

        return back()->with('status', 'Si el correo existe y esta verificado, se enviara un PIN.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function update(ResetPasswordRequest $request, PasswordResetService $passwordReset): RedirectResponse
    {
        $result = $passwordReset->reset($request->validated(), $request);

        if ($result !== 'completed') {
            $key = $result === 'expired' ? 'token_expired' : 'token_invalid';

            return back()->withErrors([
                'email' => config('security_errors.auth.'.$key.'.code').': '.config('security_errors.auth.'.$key.'.userInfo'),
            ]);
        }

        return redirect()->route('login')->with('status', 'Contrasena actualizada.');
    }
}
