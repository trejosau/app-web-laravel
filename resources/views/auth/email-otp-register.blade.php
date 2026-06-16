<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar correo</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%); color: #111827; }
        main { width: min(100% - 32px, 430px); background: rgba(255,255,255,.96); border: 1px solid #dbe3ef; border-radius: 14px; padding: 28px; box-shadow: 0 18px 45px rgba(15,23,42,.10); }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input { box-sizing: border-box; width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; }
        button { width: 100%; padding: 11px 12px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        .error { color: #b91c1c; font-size: 14px; margin: -6px 0 12px; }
        .status { color: #047857; background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; }
    </style>
</head>
<body>
    <main>
        <h1>Verificar correo</h1>
        <p>Enviamos un OTP a tu correo. Revisa Mailtrap.</p>

        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('register.email-otp.verify') }}" data-safe-submit>
            @csrf
            <label for="otp">Código OTP</label>
            <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" autofocus>
            @error('otp')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Verificar</button>
        </form>

        <form method="POST" action="{{ route('register.email-otp.resend') }}" style="margin-top: 12px;">
            @csrf
            <button type="submit">Reenviar OTP</button>
        </form>
    </main>
    @include('partials.safe-submit')
</body>
</html>
