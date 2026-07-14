<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Login seguro' }}</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%); color: #111827; }
        main { width: min(100% - 32px, 960px); margin: 48px auto; }
        .panel { background: rgba(255,255,255,.96); border: 1px solid #dbe3ef; border-radius: 14px; padding: 28px; box-shadow: 0 18px 45px rgba(15,23,42,.10); }
        h1 { font-size: 25px; margin: 0 0 16px; }
        h2 { font-size: 20px; margin: 24px 0 12px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input, select { box-sizing: border-box; width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; background: #fff; }
        input:focus, select:focus { outline: 0; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.16); }
        button, .button-link { display: inline-block; padding: 11px 14px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; text-decoration: none; }
        button:hover, .button-link:hover { background: #1d4ed8; }
        button:disabled { opacity: .65; cursor: wait; }
        a { color: #2563eb; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .error { color: #b91c1c; font-size: 14px; margin: 8px 0 12px; }
        .warning { color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; }
        .muted, .hint { color: #64748b; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #e0ecff; color: #1e40af; font-size: 13px; font-weight: 700; }
        .nav { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .nav form { margin-left: auto; }
        .nav button { padding: 8px 10px; }
    </style>
</head>
<body>
    <main>
        @auth
            <nav class="nav" aria-label="Navegación principal">
                <a href="{{ route('dashboard') }}">Inicio</a>
                <a href="{{ route('profile.show') }}">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Cerrar sesión</button>
                </form>
            </nav>
        @endauth

        @if (session('status'))
            <section class="panel" style="margin-bottom: 16px;">
                {{ session('status') }}
            </section>
        @endif

        @yield('content')
    </main>
    @include('partials.safe-submit')
</body>
</html>
