<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contraseña</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%); color: #111827; }
        main { width: min(100% - 32px, 460px); background: rgba(255,255,255,.96); border: 1px solid #dbe3ef; border-radius: 14px; padding: 28px; box-shadow: 0 18px 45px rgba(15,23,42,.10); }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input { box-sizing: border-box; width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; }
        input:focus { outline: 0; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.16); }
        button { width: 100%; padding: 11px 12px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { color: #b91c1c; font-size: 14px; margin: -6px 0 12px; }
    </style>
</head>
<body>
    <main>
        <h1>Restablecer contraseña</h1>
        <form method="POST" action="{{ route('password.update') }}" data-safe-submit>
            @csrf
            <input type="hidden" name="email" value="{{ old('email', $email) }}">

            <label for="token">PIN del correo</label>
            <input id="token" name="token" type="text" value="{{ old('token', $token) }}" inputmode="numeric" autocomplete="one-time-code">

            <label for="password">Nueva contraseña</label>
            <input id="password" name="password" type="password" autocomplete="new-password">
            @include('partials.password-rules', ['target' => 'password'])
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
            @include('partials.recaptcha')
            <button type="submit" data-submit-label="Actualizando...">Actualizar contraseña</button>
        </form>
    </main>
    @include('partials.safe-submit')
</body>
</html>
