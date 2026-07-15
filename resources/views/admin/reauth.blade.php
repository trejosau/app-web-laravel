@extends('layouts.app', ['title' => 'Reauth admin'])

@section('content')
    <section class="panel">
        <h1>Reautenticación de administrador</h1>
        <p class="hint">Confirma tu identidad para continuar con la acción sensible. Al terminar volverás al área administrativa.</p>
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

            <button type="submit" data-submit-label="Verificando...">Confirmar identidad</button>
            <a href="{{ route('admin.users.index') }}" style="margin-left: 8px;">Cancelar y volver</a>
        </form>
    </section>
@endsection
