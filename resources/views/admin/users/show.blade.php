@extends('layouts.app', ['title' => 'Usuario'])

@section('content')
    <section class="panel">
        <h1>Usuario {{ $user->username }}</h1>
        <p>ID: {{ $user->id }}</p>
        <p>Correo: {{ $user->email ?? 'N/A' }}</p>
        <p>Rol: {{ $user->role?->name ?? 'N/A' }}</p>
        <p>Estado: {{ $user->status }}</p>
    </section>

@endsection
