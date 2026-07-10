@extends('layouts.app', ['title' => 'Codigo de recuperacion'])

@section('content')
    <section class="panel">
        <h1>Usar codigo de recuperacion</h1>

        <form method="POST" action="{{ route('mfa.recovery-code.verify') }}">
            @csrf
            <label for="code">Codigo de recuperacion</label>
            <input id="code" name="code" autocomplete="one-time-code" autofocus>
            @error('code')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Verificar</button>
        </form>
    </section>
@endsection
