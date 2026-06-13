<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Login seguro' }}</title>
    <style>
        :root { color-scheme: light; --primary: #2563eb; --primary-dark: #1d4ed8; --surface: #ffffff; --muted: #64748b; --line: #dbe3ef; --danger: #b91c1c; --success: #047857; --warning: #b45309; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; background: linear-gradient(180deg, #f8fbff 0%, #edf3fb 100%); color: #111827; }
        .topbar { height: 64px; display: flex; justify-content: space-between; align-items: center; padding: 0 24px; background: rgba(255, 255, 255, .88); border-bottom: 1px solid var(--line); backdrop-filter: blur(12px); box-shadow: 0 8px 24px rgba(15, 23, 42, .05); }
        .topbar-home { color: #111827; font-weight: 700; text-decoration: none; }
        .topbar-home:hover { color: var(--primary-dark); }
        .profile-menu { position: relative; }
        .profile-menu summary { list-style: none; cursor: pointer; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 50%; background: var(--primary); color: #fff; font-weight: 700; box-shadow: 0 8px 18px rgba(37, 99, 235, .25); }
        .profile-menu summary::-webkit-details-marker { display: none; }
        .profile-menu[open] .dropdown { display: grid; }
        .dropdown { display: none; position: absolute; right: 0; top: 46px; min-width: 190px; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 16px 34px rgba(15, 23, 42, .12); z-index: 20; overflow: hidden; }
        .dropdown a, .dropdown span, .dropdown button { padding: 10px 12px; color: #111827; text-decoration: none; font-size: 14px; background: transparent; border: 0; font-weight: 400; text-align: left; cursor: pointer; }
        .dropdown a:hover, .dropdown button:hover { background: #f1f5f9; }
        .dropdown .muted { color: var(--muted); cursor: not-allowed; }
        .container { width: min(100% - 32px, 1040px); margin: 28px auto; }
        .panel { background: rgba(255, 255, 255, .94); border: 1px solid var(--line); border-radius: 12px; padding: 24px; margin-bottom: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, .07); }
        h1 { font-size: 24px; margin: 0 0 16px; }
        h2 { font-size: 18px; margin: 0 0 12px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; background: #fff; color: #111827; transition: border-color .15s ease, box-shadow .15s ease; }
        input:focus, select:focus { outline: 0; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, .16); }
        button, .button-link { display: inline-block; padding: 10px 14px; border: 0; border-radius: 8px; background: var(--primary); color: #fff; font-weight: 700; cursor: pointer; text-decoration: none; box-shadow: 0 8px 18px rgba(37, 99, 235, .18); }
        button:hover, .button-link:hover { background: var(--primary-dark); }
        button:disabled { opacity: .65; cursor: not-allowed; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        th, td { text-align: left; border-bottom: 1px solid #e5edf6; padding: 11px 10px; font-size: 14px; vertical-align: top; }
        th { color: #475569; background: #f8fafc; font-weight: 700; }
        .error { color: var(--danger); font-size: 14px; margin: -6px 0 12px; }
        .status { color: var(--success); background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; }
        .status--error { color: var(--danger); background: #fef2f2; border-color: #fecaca; }
        .warning { color: var(--warning); background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; }
        .badge { display: inline-block; padding: 3px 9px; background: #e2e8f0; border-radius: 999px; font-size: 12px; color: #334155; }
        .hint { color: #475569; font-size: 14px; margin: 0 0 16px; }
        .muted { color: var(--muted); font-size: 13px; }
        a { color: var(--primary); }
        .role-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .role-form--stacked { flex-direction: column; align-items: stretch; max-width: 220px; }
        .role-form select { width: auto; min-width: 160px; margin-bottom: 0; padding: 6px 8px; font-size: 13px; }
        .role-form--stacked select { width: 100%; }
        .role-form input[type="text"] { width: auto; min-width: 100px; margin-bottom: 0; padding: 6px 8px; font-size: 13px; }
        .role-form--stacked input[type="text"] { width: 100%; }
        .role-form button { padding: 6px 10px; font-size: 13px; white-space: nowrap; }
        .admin-links { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
    </style>
</head>
<body>
    @auth
        <nav class="topbar">
            <a class="topbar-home" href="{{ route('dashboard') }}">Panel</a>
            <details class="profile-menu">
                <summary aria-label="Perfil">{{ mb_strtoupper(mb_substr(auth()->user()->username, 0, 1)) }}</summary>
                <div class="dropdown">
                    <a href="{{ route('profile.show') }}">Perfil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Cerrar sesion</button>
                    </form>
                </div>
            </details>
        </nav>
    @endauth

    <main class="container">
        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="status status--error">{{ session('error') }}</p>
        @endif

        @yield('content')
    </main>
    @include('partials.safe-submit')
</body>
</html>
