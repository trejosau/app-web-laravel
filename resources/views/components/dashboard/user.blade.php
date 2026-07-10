<section class="panel">
    <h1>Panel user</h1>
    <p>Acceso con TOTP completado.</p>
    <p>Rol actual: {{ auth()->user()->role?->name ?? 'sin rol' }}</p>
    <p>MFA requerido: nivel {{ auth()->user()->role?->required_mfa_level ?? 2 }}</p>
</section>

<section class="panel">
    <h2>Seguridad</h2>
    <div class="admin-links">
        <a class="button-link" href="{{ route('profile.show') }}">Perfil</a>
        <a class="button-link" href="{{ route('mfa.recovery-codes') }}">Códigos de recuperación</a>
    </div>
</section>
