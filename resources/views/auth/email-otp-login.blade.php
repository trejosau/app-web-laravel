@extends('layouts.app', ['title' => 'OTP por correo'])

@section('content')
    <section class="panel">
        <h1>OTP por correo</h1>
        <p>Enviamos un código de 6 dígitos a {{ $email }}.</p>

        <form method="POST" action="{{ route('email-otp.verify') }}" data-safe-submit>
            @csrf
            <label for="otp">Código OTP</label>
            <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" autofocus>
            @error('otp')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Verificar</button>
        </form>

        <form method="POST" action="{{ route('email-otp.resend') }}" style="margin-top: 12px;">
            @csrf
            <button type="submit">Reenviar OTP</button>
        </form>
    </section>
@endsection
