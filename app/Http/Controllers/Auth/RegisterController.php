<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, AuthService $authService): RedirectResponse
    {
        try {
            $authService->register($request->validated(), $request);
        } catch (Throwable) {
            return back()
                ->withInput($request->safe()->only(['username', 'email']))
                ->withErrors(['username' => config('security_errors.auth.register_failed.code').': '.config('security_errors.auth.register_failed.userInfo')]);
        }

        return redirect('/')->with('status', 'Registro completado.');
    }
}
