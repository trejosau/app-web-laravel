@extends('layouts.app', ['title' => 'Usuario'])

@section('content')
    <section class="panel">
        <h1>Usuario {{ $user->username }}</h1>
        <p>ID: {{ $user->id }}</p>
        <p>Correo: {{ $user->email ?? 'N/A' }}</p>
        <p>Rol: {{ $user->role?->name ?? 'N/A' }}</p>
        <p>Estado: {{ $user->status }}</p>
    </section>

    <section class="panel">
        <h2>Acciones sensibles</h2>
        <p class="hint">Estas acciones requieren una reautenticación de administrador reciente.</p>

        @can('block', $user)
            @if ($user->status === 'active')
                <form method="POST" action="{{ route('admin.users.block', $user) }}" data-safe-submit>
                    @csrf
                    @method('PUT')
                    <button type="submit">Bloquear acceso</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.activate', $user) }}" data-safe-submit>
                    @csrf
                    @method('PUT')
                    <button type="submit">Activar acceso</button>
                </form>
            @endif
        @endcan

    </section>
@endsection
