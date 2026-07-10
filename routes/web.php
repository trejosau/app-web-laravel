<?php

use App\Http\Controllers\Account\ReauthController as AccountReauthController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ErrorCatalogController;
use App\Http\Controllers\Admin\ReauthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Mfa\RecoveryCodeLoginController;
use App\Http\Controllers\Mfa\RecoveryCodesController;
use App\Http\Controllers\Mfa\TotpLoginController;
use App\Http\Controllers\Mfa\TotpSetupController;
use App\Http\Controllers\Mfa\WebauthnController;
use App\Http\Controllers\ProfileController;
use App\Models\Role;
use App\Services\MfaPendingSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->hasRole(Role::ADMIN)) {
        return redirect()->route('dashboard.admin');
    }

    if ($user->hasRole(Role::USER)) {
        return redirect()->route('dashboard.user');
    }

    return redirect()->route('dashboard.guest');
})->middleware(['mfa.complete', 'auth'])->name('dashboard');

Route::get('/home', function () {
    return redirect()->route('dashboard');
})->middleware(['mfa.complete', 'auth'])->name('home');

Route::get('/dashboard/guest', function () {
    return view('dashboard', ['dashboard' => 'guest']);
})->middleware(['mfa.complete', 'auth', 'role:guest,user,admin', 'mfa.level:1'])->name('dashboard.guest');

Route::get('/dashboard/guess', function () {
    return redirect()->route('dashboard.guest');
})->middleware(['mfa.complete', 'auth'])->name('dashboard.guess');

Route::get('/dashboard/user', function () {
    return view('dashboard', ['dashboard' => 'user']);
})->middleware(['mfa.complete', 'auth', 'role:user,admin', 'mfa.level:2'])->name('dashboard.user');

Route::get('/dashboard/admin', function () {
    return view('dashboard', ['dashboard' => 'admin']);
})->middleware(['mfa.complete', 'auth', 'role:admin', 'mfa.level:3', 'admin.session.timeout'])->name('dashboard.admin');

Route::middleware(['mfa.complete', 'auth'])->group(function () {
    Route::get('/account/reauth', [AccountReauthController::class, 'show'])->name('account.reauth');
    Route::post('/account/reauth', [AccountReauthController::class, 'store'])->middleware('throttle:admin-reauth')->name('account.reauth.store');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::put('/profile/email', [ProfileController::class, 'updateEmail'])->middleware('account.reauth')->name('profile.email.update');
    Route::post('/profile/email/verification', [ProfileController::class, 'sendEmailVerification'])->middleware(['account.reauth', 'throttle:email-resend'])->name('profile.email.verification.send');
    Route::get('/profile/email/verify/{user}/{hash}', [ProfileController::class, 'verifyEmail'])->middleware('signed')->name('profile.email.verify');
    Route::delete('/profile/sessions', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy');
    Route::post('/mfa/recovery-codes/regenerate', [RecoveryCodesController::class, 'regenerate'])->middleware(['mfa.level:2', 'account.reauth', 'throttle:recovery-codes'])->name('mfa.recovery-codes.regenerate');
    Route::delete('/mfa/webauthn/credentials/{credential}', [WebauthnController::class, 'destroy'])->middleware(['account.reauth', 'throttle:webauthn'])->name('webauthn.destroy');
});

Route::middleware(['mfa.complete', 'auth', 'role:admin', 'mfa.level:3', 'admin.session.timeout'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reauth', [ReauthController::class, 'show'])->name('reauth');
    Route::post('/reauth', [ReauthController::class, 'store'])->middleware('throttle:admin-reauth')->name('reauth.store');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::get('/error-catalog', ErrorCatalogController::class)->name('error-catalog.index');

    Route::middleware(['admin.reauth', 'throttle:admin-critical'])->group(function () {
        Route::put('/users/{user}/block', [AdminUserController::class, 'block'])->name('users.block');
        Route::put('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::delete('/users/{user}/passkey', [AdminUserController::class, 'resetPasskey'])->name('users.passkey.reset');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::get('/mfa/pending', function (Request $request, MfaPendingSessionService $mfaPendingSession) {
    abort_unless(session()->has('auth_pending_user_id'), 404);

    $user = $mfaPendingSession->pendingUser($request);
    abort_unless($user !== null, 404);

    if (! $request->session()->has('auth_pending_totp_verified_at')) {
        return $user->totp_enabled_at && filled($user->totp_secret)
            ? redirect()->route('totp.login')
            : redirect()->route('totp.setup');
    }

    return $user->webauthn_enabled_at && $user->webauthnCredentials()->exists()
        ? redirect()->route('webauthn.login')
        : redirect()->route('webauthn.setup');
})->name('mfa.pending');

Route::get('/mfa/totp/setup', [TotpSetupController::class, 'show'])->name('totp.setup');
Route::get('/two-factor/qr', [TotpSetupController::class, 'qr'])->name('two-factor.qr');
Route::post('/mfa/totp/setup', [TotpSetupController::class, 'confirm'])->middleware('throttle:totp')->name('totp.setup.confirm');
Route::get('/mfa/totp', [TotpLoginController::class, 'show'])->name('totp.login');
Route::post('/mfa/totp', [TotpLoginController::class, 'verify'])->middleware('throttle:totp')->name('totp.login.verify');
Route::get('/mfa/recovery-code', [RecoveryCodeLoginController::class, 'show'])->name('mfa.recovery-code');
Route::post('/mfa/recovery-code', [RecoveryCodeLoginController::class, 'verify'])->middleware('throttle:recovery-codes')->name('mfa.recovery-code.verify');
Route::get('/mfa/recovery-codes', RecoveryCodesController::class)->name('mfa.recovery-codes');
Route::get('/mfa/webauthn/setup', [WebauthnController::class, 'setup'])->middleware('throttle:webauthn')->name('webauthn.setup');
Route::get('/mfa/webauthn/login', [WebauthnController::class, 'login'])->middleware('throttle:webauthn')->name('webauthn.login');
Route::get('/mfa/webauthn/register/options', [WebauthnController::class, 'registerOptions'])->middleware('throttle:webauthn')->name('webauthn.register.options');
Route::post('/mfa/webauthn/register', [WebauthnController::class, 'register'])->middleware('throttle:webauthn')->name('webauthn.register');
Route::get('/mfa/webauthn/login/options', [WebauthnController::class, 'loginOptions'])->middleware('throttle:webauthn')->name('webauthn.login.options');
Route::post('/mfa/webauthn/login', [WebauthnController::class, 'authenticate'])->middleware('throttle:webauthn')->name('webauthn.authenticate');

Route::post('/logout', LogoutController::class)->name('logout');
