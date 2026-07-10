@extends('layouts.app', ['title' => 'Reauth admin'])

@section('content')
    <section class="panel">
        <h1>Reautenticacion de administrador</h1>
        <form method="POST" action="{{ route('admin.reauth.store') }}" data-safe-submit>
            @csrf
            <label for="password">Contraseña actual</label>
            <input id="password" name="password" type="password" autocomplete="current-password">

            <label for="otp">Codigo TOTP</label>
            <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code">

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
