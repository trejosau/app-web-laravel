<section class="panel">
    <h1>Panel admin</h1>
    <p>Acceso con TOTP y OTP por correo completado.</p>
    <p>Rol actual: {{ auth()->user()->role?->name ?? 'sin rol' }}</p>
    <p>MFA requerido: nivel {{ auth()->user()->role?->required_mfa_level ?? 3 }}</p>
</section>

<section class="panel">
    <h2>Administracion</h2>
    <div class="admin-links">
        <a class="button-link" href="{{ route('admin.users.index') }}">Usuarios</a>
        <a class="button-link" href="{{ route('admin.audit-logs.index') }}">Auditoria</a>
        <a class="button-link" href="{{ route('admin.error-catalog.index') }}">Catalogo de errores</a>
    </div>
</section>
