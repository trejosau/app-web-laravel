@extends('layouts.app', ['title' => 'Detalle auditoria'])

@section('content')
    <section class="panel">
        <h1>Log #{{ $auditLog->id }}</h1>
        <p>Evento: {{ $auditLog->event }}</p>
        <p>Accion: {{ $auditLog->action }}</p>
        <p>Estado: {{ $auditLog->state }}</p>
        <p>Severidad: {{ $auditLog->severity }}</p>
        <p>Usuario: {{ $auditLog->user?->username ?? 'N/A' }}</p>
        <p>Ruta: {{ $auditLog->route }}</p>
        <p>Metodo: {{ $auditLog->method }}</p>
        <p>Status: {{ $auditLog->status }}</p>
        <p>Previous hash: {{ $auditLog->previous_hash ?? 'N/A' }}</p>
        <p>Current hash: {{ $auditLog->current_hash ?? 'N/A' }}</p>
        <pre>{{ json_encode($auditLog->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
@endsection
