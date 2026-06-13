@extends('layouts.app', ['title' => $isUpdate ? 'Actualizar TOTP' : 'Activar TOTP'])

@section('content')
    <section class="panel">
        @if (! session('error') && $errors->any())
            <p class="error">{{ $isUpdate ? 'No se pudo actualizar el TOTP.' : 'No se pudo activar el TOTP.' }}</p>
        @endif

        @if ($requiresUpdateVerification)
            <h1>Confirmar TOTP actual</h1>

            <form method="POST" action="{{ route('totp.setup.confirm') }}">
                @csrf
                <label for="current_password">Contrasena actual</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password">

                <label for="current_otp">Codigo TOTP actual</label>
                <input id="current_otp" name="current_otp" inputmode="numeric" autocomplete="one-time-code">

                @error('current_password')
                    <p class="error">{{ $message }}</p>
                @enderror
                @error('current_otp')
                    <p class="error">{{ $message }}</p>
                @enderror

                <button type="submit">Continuar</button>
            </form>
        @else
            <h1>{{ $isUpdate ? 'Configurar nuevo Google Authenticator' : 'Activar Google Authenticator' }}</h1>

            <p>Clave manual:</p>
            <pre>{{ $secret }}</pre>

            <div style="display: inline-block; padding: 12px; background: #fff; border: 1px solid #e5e7eb; margin-bottom: 12px;">
                <img src="{{ route('two-factor.qr', ['v' => session('totp_setup_started_at')]) }}" alt="QR Google Authenticator" width="280" height="280">
            </div>

            <form method="POST" action="{{ route('totp.setup.confirm') }}">
                @csrf
                <label for="otp">{{ $isUpdate ? 'Codigo del nuevo TOTP' : 'Codigo OTP' }}</label>
                <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code">
                @error('otp')
                    <p class="error">{{ $message }}</p>
                @enderror
                <button type="submit">Confirmar</button>
            </form>
        @endif
    </section>
@endsection
