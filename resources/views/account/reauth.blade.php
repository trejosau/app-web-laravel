@extends('layouts.app', ['title' => 'Reautenticacion'])

@section('content')
    <section class="panel">
        <h1>Reautenticacion</h1>
        <form method="POST" action="{{ route('account.reauth.store') }}" data-safe-submit>
            @csrf

            <label for="password">Contraseña actual</label>
            <input id="password" name="password" type="password" autocomplete="current-password">

            @if ($requiresTotp)
                <label for="otp">Codigo TOTP</label>
                <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code">
            @endif

            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
            @error('otp')
                <p class="error">{{ $message }}</p>
            @enderror

            <button type="submit" data-submit-label="Verificando...">Reautenticar</button>
        </form>
    </section>
@endsection
