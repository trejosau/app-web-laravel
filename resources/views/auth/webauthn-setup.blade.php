@extends('layouts.app', ['title' => 'Agregar Passkey'])

@section('content')
    <x-auth.passkey-action
        title="Agregar Passkey"
        description="Usa el desbloqueo de tu dispositivo para registrar la Passkey."
        button-id="webauthn-register"
        button-label="Agregar Passkey"
        mode="register"
        :options-url="route('webauthn.register.options', [], false)"
        :submit-url="route('webauthn.register', [], false)"
        :auto-start="true"
    />
@endsection
