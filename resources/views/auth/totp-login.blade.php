@extends('layouts.app', ['title' => 'Codigo TOTP'])

@section('content')
    <section class="panel">
        <h1>Codigo TOTP</h1>

        <form method="POST" action="{{ route('totp.login.verify') }}">
            @csrf
            <label for="otp">TOTP</label>
            <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" autofocus>
            @error('otp')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Continuar</button>
        </form>

        <p class="muted"><a href="{{ route('mfa.recovery-code') }}">Perdi mi app de autenticacion</a></p>
    </section>
@endsection
