@extends('layouts.app', ['title' => 'Perfil'])

@section('content')
    <section class="panel">
        <h1>Perfil</h1>
        <p><strong>Usuario:</strong> {{ $user->username }}</p>
        <p><strong>Rol:</strong> {{ $user->role?->name ?? 'sin rol' }}</p>
        <p><strong>Estado:</strong> {{ $user->status }}</p>
        <p>
            <strong>Correo:</strong>
            {{ $user->email ?? 'No vinculado' }}
            @if ($user->email_verified_at)
                <span class="badge">verificado</span>
            @else
                <span class="badge">sin verificar</span>
            @endif
        </p>
        @unless ($user->hasVerifiedRecoveryEmail())
            <p class="warning">Sin correo verificado no podras recuperar la cuenta si pierdes acceso.</p>
        @endunless
    </section>

    @if ($user->totp_enabled_at)
        <section class="panel">
            <h2>Actualizar TOTP</h2>
            <p><a class="button-link" href="{{ route('totp.setup') }}">Reemplazar TOTP</a></p>
        </section>
    @endif

    @if ($user->hasRole('admin'))
        <section class="panel">
            <h2>Passkeys</h2>
            <p><a class="button-link" href="{{ route('webauthn.setup') }}">Agregar Passkey</a></p>

            @if ($user->webauthnCredentials->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Ultimo uso</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($user->webauthnCredentials as $credential)
                            <tr>
                                <td>{{ $credential->name ?? 'Passkey' }}</td>
                                <td>{{ $credential->last_used_at?->diffForHumans() ?? 'N/A' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('webauthn.destroy', $credential) }}" data-safe-submit onsubmit="return confirm('Eliminar Passkey');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @error('passkey')
                    <p class="error">{{ $message }}</p>
                @enderror
            @endif
        </section>
    @endif

    <section class="panel">
        <h2>Cambiar contrasena</h2>
        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')

            <label for="current_password">Contrasena actual</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password">
            @error('current_password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password">Nueva contrasena</label>
            <input id="password" name="password" type="password" autocomplete="new-password">
            @include('partials.password-rules', ['target' => 'password'])
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password_confirmation">Confirmar nueva contrasena</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">

            <button type="submit">Actualizar contrasena</button>
        </form>
    </section>

    <section class="panel">
        <h2>Correo de recuperacion</h2>
        <form method="POST" action="{{ route('profile.email.update') }}">
            @csrf
            @method('PUT')

            <label for="email">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror

            <button type="submit">Guardar y enviar verificacion</button>
        </form>

        @if ($user->email && ! $user->email_verified_at)
            <form method="POST" action="{{ route('profile.email.verification.send') }}" style="margin-top: 12px;">
                @csrf
                <button type="submit">Reenviar verificacion</button>
            </form>
        @endif
    </section>

    <section class="panel">
        <h2>Sesiones abiertas</h2>
        @if (count($sessions) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>IP</th>
                        <th>Ultima actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                        <tr>
                            <td>
                                {{ $session['user_agent'] ?? 'Desconocido' }}
                                @if ($session['current'])
                                    <span class="badge">actual</span>
                                @endif
                            </td>
                            <td>{{ $session['ip_address'] ?? 'N/A' }}</td>
                            <td>{{ $session['last_activity']->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form method="POST" action="{{ route('profile.sessions.destroy') }}" style="margin-top: 12px;">
                @csrf
                @method('DELETE')
                <button type="submit">Cerrar todas las demas</button>
            </form>
        @else
            <p>No hay sesiones en base de datos.</p>
        @endif
    </section>
@endsection
