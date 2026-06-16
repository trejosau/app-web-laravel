<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login seguro</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%); color: #111827; }
        main { width: min(100% - 32px, 460px); background: rgba(255,255,255,.96); border: 1px solid #dbe3ef; border-radius: 12px; padding: 28px; box-shadow: 0 18px 45px rgba(15,23,42,.10); }
        h1 { font-size: 25px; margin: 0 0 12px; }
        p { color: #475569; margin: 0 0 18px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        a { display: inline-block; padding: 10px 14px; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; text-decoration: none; }
        a.secondary { background: #e2e8f0; color: #111827; }
        a:hover { filter: brightness(.96); }
    </style>
</head>
<body>
    <main>
        <h1>Login seguro</h1>
        <p>Autenticación con roles, MFA, auditoria y sesiones cifradas.</p>
        <div class="actions">
            @auth
                <a href="{{ route('dashboard') }}">Ir al panel</a>
            @else
                <a href="{{ route('login') }}">Iniciar sesión</a>
                <a class="secondary" href="{{ route('register') }}">Crear cuenta</a>
            @endauth
        </div>
    </main>
</body>
</html>
