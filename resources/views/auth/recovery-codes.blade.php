@extends('layouts.app', ['title' => 'Codigos de recuperacion'])

@section('content')
    <section class="panel">
        <h1>Codigos de recuperacion</h1>

        @if (count($recoveryCodes) > 0)
            <ul>
                @foreach ($recoveryCodes as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>
        @else
            <p>No hay codigos para mostrar.</p>
        @endif

        @if (session()->has('auth_pending_totp_verified_at'))
            <p><a href="{{ route('mfa.pending', [], false) }}">Continuar con Passkey</a></p>
        @endif

        @auth
            <form method="POST" action="{{ route('mfa.recovery-codes.regenerate') }}">
                @csrf
                <button type="submit">Regenerar codigos</button>
            </form>
        @endauth
    </section>
@endsection
