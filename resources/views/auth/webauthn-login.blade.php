@extends('layouts.app', ['title' => 'Verificar Passkey'])

@section('content')
    <x-auth.passkey-action
        title="Verificar Passkey"
        description="Confirma tu identidad con la Passkey registrada."
        button-id="webauthn-login"
        button-label="Verificar Passkey"
        mode="login"
        :options-url="route('webauthn.login.options', [], false)"
        :submit-url="route('webauthn.authenticate', [], false)"
        :auto-start="true"
    />
@endsection
