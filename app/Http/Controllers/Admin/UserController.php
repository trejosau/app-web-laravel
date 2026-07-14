<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => User::query()->with('role')->withTrashed()->orderBy('id')->paginate(20),
        ]);
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load('role'),
        ]);
    }

    public function block(Request $request, User $user, SecurityAuditService $auditService): RedirectResponse
    {
        $this->authorize('block', $user);

        $user->forceFill(['status' => 'locked', 'locked_until' => now()->addYears(10)])->save();
        $auditService->log($request, 'user.blocked', 'warning', 200, $user->id, ['actor_id' => $request->user()->id]);

        return back()->with('status', 'Usuario bloqueado.');
    }

    public function activate(Request $request, User $user, SecurityAuditService $auditService): RedirectResponse
    {
        $this->authorize('activate', $user);

        $user->forceFill(['status' => 'active', 'locked_until' => null])->save();
        $auditService->log($request, 'user.activated', 'info', 200, $user->id, ['actor_id' => $request->user()->id]);

        return back()->with('status', 'Usuario activado.');
    }

    public function resetPasskey(Request $request, User $user, SecurityAuditService $auditService): RedirectResponse
    {
        $this->authorize('resetPasskey', $user);

        $deletedCredentials = $user->webauthnCredentials()->delete();
        $user->forceFill(['webauthn_enabled_at' => null])->save();

        $auditService->log($request, 'user.passkey_reset', 'warning', 200, $user->id, [
            'actor_id' => $request->user()->id,
            'deleted_credentials' => $deletedCredentials,
        ]);

        return back()->with('status', 'Passkeys del usuario restablecidas.');
    }
}
