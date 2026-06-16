<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login seguro</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%); color: #111827; }
        main { width: min(100% - 32px, 430px); background: rgba(255,255,255,.96); border: 1px solid #dbe3ef; border-radius: 14px; padding: 28px; box-shadow: 0 18px 45px rgba(15,23,42,.10); }
        h1 { font-size: 25px; margin: 0 0 22px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input { box-sizing: border-box; width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; }
        input:focus { outline: 0; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.16); }
        button { width: 100%; padding: 11px 12px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        a { color: #2563eb; }
        .error { color: #b91c1c; font-size: 14px; margin: -6px 0 12px; }
    </style>
</head>
<body>
    <main>
        <h1>Login seguro</h1>

        <form method="POST" action="{{ route('login.store') }}" data-safe-submit>
            @csrf

            <label for="username">Usuario</label>
            <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus>
            @error('username')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            @include('partials.recaptcha')

            <button type="submit" data-submit-label="Entrando...">Entrar</button>
            <p><a href="{{ route('password.request') }}">Olvide mi contraseña</a></p>
        </form>
    </main>
    @include('partials.safe-submit')
</body>
</html>
