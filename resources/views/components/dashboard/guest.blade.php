<section class="panel">
    <h1>Panel guest</h1>
    <p>Acceso basico activo.</p>
    <p>Rol actual: {{ auth()->user()->role?->name ?? 'sin rol' }}</p>
    <p>MFA requerido: nivel {{ auth()->user()->role?->required_mfa_level ?? 1 }}</p>
</section>

<section class="panel">
    <h2>Cuenta</h2>
    <div class="admin-links">
        <a class="button-link" href="{{ route('profile.show') }}">Perfil</a>
    </div>
</section>
