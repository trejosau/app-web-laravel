@php
    $reauthenticatedAt = (int) session(\App\Http\Middleware\EnsureAdminReauthenticated::SESSION_KEY, 0);
    $reauthenticatedUntil = $reauthenticatedAt > 0
        ? $reauthenticatedAt + (\App\Http\Middleware\EnsureAdminReauthenticated::TIMEOUT_MINUTES * 60)
        : 0;
@endphp

<dialog
    id="admin-reauth-dialog"
    class="reauth-dialog"
    data-admin-reauth-dialog
    data-endpoint="{{ route('admin.reauth.store') }}"
    data-reauthenticated-for="{{ max(0, $reauthenticatedUntil - now()->timestamp) }}"
    aria-labelledby="admin-reauth-title"
    aria-describedby="admin-reauth-description"
>
    <div class="reauth-dialog__content">
        <h2 id="admin-reauth-title">Confirma tu identidad</h2>
        <p id="admin-reauth-description" class="hint">
            Por seguridad, vuelve a ingresar tu contraseña y código TOTP para <span data-admin-action-label>continuar</span>.
        </p>

        <form method="POST" action="{{ route('admin.reauth.store') }}" data-admin-reauth-form>
            @csrf

            <label for="admin_reauth_password">Contraseña actual</label>
            <input id="admin_reauth_password" name="password" type="password" autocomplete="current-password" maxlength="128">

            <label for="admin_reauth_otp">Código TOTP</label>
            <input id="admin_reauth_otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ (int) config('totp.digits', 6) }}" data-sanitize="otp">

            <p class="reauth-dialog__error" data-admin-reauth-error role="alert" aria-live="assertive"></p>

            <div class="reauth-dialog__actions">
                <button class="reauth-dialog__cancel" type="button" data-admin-reauth-cancel>Cancelar</button>
                <button class="reauth-dialog__submit" type="submit" data-submit-label="Verificando...">Confirmar y continuar</button>
            </div>
        </form>
    </div>
</dialog>
