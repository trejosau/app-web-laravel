@extends('layouts.app', ['title' => 'Auditoria'])

@section('content')
    <section class="panel">
        <h1>Auditoria</h1>
        <form method="GET" action="{{ route('admin.audit-logs.index') }}">
            <input name="event" value="{{ request('event') }}" placeholder="evento">
            <input name="user_id" value="{{ request('user_id') }}" placeholder="user id">
            <input name="ip" value="{{ request('ip') }}" placeholder="ip">
            <input name="route" value="{{ request('route') }}" placeholder="ruta">
            <select name="severity" aria-label="Nivel">
                <option value="">nivel</option>
                @foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level)
                    <option value="{{ $level }}" @selected(request('severity') === $level)>{{ $level }}</option>
                @endforeach
            </select>
            <button type="submit">Filtrar</button>
        </form>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Evento</th>
                    <th>Accion</th>
                    <th>Estado</th>
                    <th>Severidad</th>
                    <th>Usuario</th>
                    <th>Ruta</th>
                    <th>Status</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td><a href="{{ route('admin.audit-logs.show', $log) }}">{{ $log->id }}</a></td>
                        <td>{{ $log->event }}</td>
                        <td>{{ $log->action }}</td>
                        <td><span class="badge">{{ $log->state }}</span></td>
                        <td>{{ $log->severity }}</td>
                        <td>{{ $log->user?->username ?? 'N/A' }}</td>
                        <td>{{ $log->route }}</td>
                        <td>{{ $log->status }}</td>
                        <td>{{ $log->created_at?->toDateTimeString() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $logs->links() }}
    </section>
@endsection
